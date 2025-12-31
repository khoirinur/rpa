<?php

namespace App\Services;

use App\Models\LiveChickenPurchaseOrder;
use App\Models\PurchaseOrderOutput;
use Illuminate\Support\Collection;

class PurchaseOrderOutputDocumentBuilder
{
    public function build(PurchaseOrderOutput $output): array
    {
        $output->loadMissing([
            'purchaseOrder.supplier',
            'purchaseOrder.destinationWarehouse',
            'purchaseOrder.product',
            'warehouse',
            'printedBy',
        ]);

        $purchaseOrder = $output->purchaseOrder;

        $metadata = $this->resolveMetadata($output, $purchaseOrder);
        $lineItems = $this->mapLineItems($purchaseOrder);
        $summary = $this->buildSummary($purchaseOrder);
        $attachments = $output->attachments ?? [];

        return [
            'output' => $output,
            'purchaseOrder' => $purchaseOrder,
            'metadata' => $metadata,
            'sections' => $this->buildSections(
                $output->document_sections ?? [],
                $metadata,
                $lineItems,
                $summary,
                $attachments
            ),
            'lineItems' => $lineItems,
            'summary' => $summary,
            'attachments' => $attachments,
        ];
    }

    protected function resolveMetadata(PurchaseOrderOutput $output, ?LiveChickenPurchaseOrder $purchaseOrder): array
    {
        $base = array_filter([
            'document_title' => $output->document_title,
            'document_number' => $output->document_number,
            'document_date' => optional($output->document_date)->format('d/m/Y'),
            'document_status' => ucfirst($output->status ?? ''),
            'layout_template' => $output->layout_template,
            'warehouse_name' => $output->warehouse?->name,
            'printed_by' => $output->printedBy?->name,
            'printed_at' => optional($output->printed_at)->format('d/m/Y H:i'),
            'notes_internal' => $output->notes,
        ], fn ($value) => filled($value));

        if ($purchaseOrder) {
            $poMeta = array_filter([
                'po_number' => $purchaseOrder->po_number,
                'order_date' => optional($purchaseOrder->order_date)->format('d/m/Y'),
                'delivery_date' => optional($purchaseOrder->delivery_date)->format('d/m/Y'),
                'supplier_name' => $purchaseOrder->supplier?->name,
                'shipping_address' => $purchaseOrder->shipping_address,
                'payment_term' => $purchaseOrder->payment_term,
                'payment_term_description' => $purchaseOrder->payment_term_description,
                'destination_warehouse' => $purchaseOrder->destinationWarehouse?->name,
                'po_notes' => $purchaseOrder->notes,
                'status_label' => LiveChickenPurchaseOrder::statusOptions()[$purchaseOrder->status] ?? ucfirst($purchaseOrder->status ?? ''),
            ], fn ($value) => filled($value));

            $base = array_merge($poMeta, $base);
        }

        return array_merge($base, $output->metadata ?? []);
    }

    protected function mapLineItems(?LiveChickenPurchaseOrder $purchaseOrder): array
    {
        $items = data_get($purchaseOrder?->metadata, 'line_items', []);

        return Collection::make($items)
            ->map(function (array $item): array {
                $quantity = $this->sanitizeNumber($item['quantity'] ?? 0);
                $unitPrice = $this->sanitizeNumber($item['unit_price'] ?? 0);
                $discountValue = $this->sanitizeNumber($item['discount_value'] ?? 0);
                $discountType = $item['discount_type'] ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT;
                $total = $this->calculateLineTotal($quantity, $unitPrice, $discountValue, $discountType);

                return [
                    'item_code' => $item['item_code'] ?? null,
                    'item_name' => $item['item_name'] ?? '-',
                    'quantity' => $quantity,
                    'quantity_display' => $this->formatQuantity($quantity),
                    'unit' => strtoupper($item['unit'] ?? ''),
                    'unit_price' => $unitPrice,
                    'unit_price_display' => $this->formatCurrency($unitPrice),
                    'discount_label' => $this->formatDiscountLabel($discountValue, $discountType),
                    'apply_tax' => (bool) ($item['apply_tax'] ?? false),
                    'notes' => $item['notes'] ?? null,
                    'line_total' => $total,
                    'line_total_display' => $this->formatCurrency($total),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildSummary(?LiveChickenPurchaseOrder $purchaseOrder): array
    {
        if (! $purchaseOrder) {
            return [];
        }

        return Collection::make([
            'subtotal' => ['Subtotal', $purchaseOrder->subtotal],
            'discount_total' => ['Total Diskon', $purchaseOrder->discount_total],
            'tax_total' => ['Total Pajak', $purchaseOrder->tax_total],
            'grand_total' => ['Total Akhir', $purchaseOrder->grand_total],
            'total_weight_kg' => ['Total Berat (Kg)', $purchaseOrder->total_weight_kg],
            'total_quantity_ea' => ['Total Ekor', $purchaseOrder->total_quantity_ea],
        ])
            ->map(fn (array $row): array => [
                'label' => $row[0],
                'value' => $row[1],
                'formatted' => is_numeric($row[1])
                    ? ($row[0] === 'Total Berat (Kg)' || $row[0] === 'Total Ekor'
                        ? $this->formatQuantity((float) $row[1])
                        : $this->formatCurrency((float) $row[1]))
                    : (string) $row[1],
                'is_currency' => ! in_array($row[0], ['Total Berat (Kg)', 'Total Ekor'], true),
            ])
            ->values()
            ->all();
    }

    protected function buildSections(array $sections, array $metadata, array $lineItems, array $summary, array $attachments): array
    {
        if (empty($sections)) {
            $sections = [[
                'title' => 'Ringkasan PO',
                'layout' => 'full',
                'content' => 'PO {{po_number}} untuk {{supplier_name}} di gudang {{destination_warehouse}}.',
            ]];
        }

        return Collection::make($sections)
            ->map(function (array $section) use ($metadata, $lineItems, $summary, $attachments): array {
                return [
                    'title' => $section['title'] ?? 'Seksi',
                    'layout' => $section['layout'] ?? 'full',
                    'blocks' => $this->buildBlocks(
                        (string) ($section['content'] ?? ''),
                        $metadata,
                        $lineItems,
                        $summary,
                        $attachments
                    ),
                ];
            })
            ->filter(fn (array $section) => ! empty($section['blocks']))
            ->values()
            ->all();
    }

    protected function buildBlocks(
        string $content,
        array $metadata,
        array $lineItems,
        array $summary,
        array $attachments
    ): array {
        $lines = preg_split('/\r?\n/', $content) ?: [];

        return Collection::make($lines)
            ->map(function (string $line) use ($metadata, $lineItems, $summary, $attachments) {
                $trimmed = trim($line);

                if ($trimmed === '') {
                    return null;
                }

                if ($trimmed === '[[TABEL_BARANG]]') {
                    return ['type' => 'table', 'data' => $lineItems];
                }

                if ($trimmed === '[[RINGKASAN_BIAYA]]') {
                    return ['type' => 'summary', 'data' => $summary];
                }

                if ($trimmed === '[[LAMPIRAN]]') {
                    return ['type' => 'attachments', 'data' => $attachments];
                }

                $rendered = preg_replace_callback('/{{\s*([^}]+)\s*}}/', function (array $matches) use ($metadata) {
                    $key = $matches[1];
                    return (string) data_get($metadata, $key, '');
                }, $line);

                return ['type' => 'text', 'data' => trim($rendered)];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function sanitizeNumber(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $numericString = preg_replace('/[^0-9,.-]/', '', (string) $value);

        if ($numericString === '' || $numericString === '-') {
            return 0.0;
        }

        $sign = str_starts_with($numericString, '-') ? -1 : 1;
        $numericString = ltrim($numericString, '-');

        $decimalSeparator = null;
        $lastDot = strrpos($numericString, '.');
        $lastComma = strrpos($numericString, ',');

        if ($lastDot !== false && $lastComma !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $decimalSeparator = ',';
        } elseif ($lastDot !== false) {
            $decimalSeparator = '.';
        }

        if ($decimalSeparator) {
            $integerPart = str_replace(['.', ','], '', substr($numericString, 0, strrpos($numericString, $decimalSeparator)));
            $fractionPart = substr($numericString, strrpos($numericString, $decimalSeparator) + 1);
            $numericString = $integerPart . '.' . $fractionPart;
        }

        return $sign * (float) $numericString;
    }

    protected function calculateLineTotal(float $quantity, float $unitPrice, float $discountValue, string $discountType): float
    {
        $gross = $quantity * $unitPrice;

        if ($gross <= 0) {
            return 0;
        }

        $discountAmount = 0;

        if ($discountType === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE) {
            $discountAmount = min($discountValue, 100) / 100 * $gross;
        } else {
            $discountAmount = min($discountValue, $gross);
        }

        return max($gross - $discountAmount, 0);
    }

    protected function formatCurrency(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    protected function formatQuantity(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }

    protected function formatDiscountLabel(float $value, ?string $type): string
    {
        if ($value <= 0) {
            return '—';
        }

        if ($type === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE) {
            return number_format(min($value, 100), 2, ',', '.') . '%';
        }

        return $this->formatCurrency($value);
    }
}
