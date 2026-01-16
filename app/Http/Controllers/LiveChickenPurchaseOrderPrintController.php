<?php

namespace App\Http\Controllers;

use App\Models\LiveChickenPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LiveChickenPurchaseOrderPrintController extends Controller
{
    public function __invoke(LiveChickenPurchaseOrder $liveChickenPurchaseOrder, Request $request): View
    {
        $user = $request->user();
        $canView = $user && (
            $user->can('View:LiveChickenPurchaseOrder') ||
            $user->can('ViewAny:LiveChickenPurchaseOrder')
        );

        abort_unless($canView, 403);

        $liveChickenPurchaseOrder->loadMissing(['supplier', 'destinationWarehouse']);

        $metadata = $this->buildMetadata($liveChickenPurchaseOrder, $user?->name);
        $lineItems = $this->mapLineItems($liveChickenPurchaseOrder);
        $summary = $this->buildSummary($liveChickenPurchaseOrder);

        return view('documents.live-chicken-purchase-order-print', [
            'purchaseOrder' => $liveChickenPurchaseOrder,
            'metadata' => $metadata,
            'lineItems' => $lineItems,
            'summary' => $summary,
        ]);
    }

    protected function buildMetadata(LiveChickenPurchaseOrder $purchaseOrder, ?string $generatedBy): array
    {
        $statusOptions = LiveChickenPurchaseOrder::statusOptions();

        return array_filter([
            'title' => sprintf('Print %s', $purchaseOrder->po_number),
            'company_name' => config('app.company_name', 'PT Surya Kencana Slaughterhouse'),
            'company_address' => config('app.company_address', 'Jl. Totok Kerot, Suko, Menang, Kec. Pagu Kab. Kediri Jawa Timur 64183 Indonesia'),
            'company_city' => config('app.company_city', 'Kediri'),
            'company_phone' => config('app.company_phone', '+62 812 1579 9522'),
            'po_number' => $purchaseOrder->po_number,
            'status_label' => $statusOptions[$purchaseOrder->status] ?? ucfirst($purchaseOrder->status ?? ''),
            'order_date' => optional($purchaseOrder->order_date)?->translatedFormat('d F Y'),
            'delivery_date' => optional($purchaseOrder->delivery_date)?->translatedFormat('d F Y'),
            'supplier_name' => $purchaseOrder->supplier?->name,
            'supplier_address' => $purchaseOrder->supplier?->address_line,
            'warehouse_name' => $purchaseOrder->destinationWarehouse?->name,
            'shipping_address' => $purchaseOrder->shipping_address,
            'payment_term' => $this->formatPaymentTerm($purchaseOrder->payment_term, $purchaseOrder->payment_term_description),
            'notes' => $purchaseOrder->notes,
            'document_generated_at' => now()->translatedFormat('d F Y H:i'),
            'generated_by' => $generatedBy,
        ], fn ($value) => filled($value));
    }

    protected function mapLineItems(LiveChickenPurchaseOrder $purchaseOrder): array
    {
        $items = data_get($purchaseOrder->metadata, 'line_items', []);

        return Collection::make($items)
            ->map(function (array $item, int $index): array {
                $quantity = $this->sanitizeNumber($item['quantity'] ?? 0);
                $unitPrice = $this->sanitizeNumber($item['unit_price'] ?? 0);
                $discountValue = $this->sanitizeNumber($item['discount_value'] ?? 0);
                $discountType = $item['discount_type'] ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT;
                $total = $this->calculateLineTotal($quantity, $unitPrice, $discountValue, $discountType);

                return [
                    'index' => $index + 1,
                    'item_code' => $item['item_code'] ?? null,
                    'item_name' => $item['item_name'] ?? '-',
                    'notes' => $item['notes'] ?? null,
                    'quantity_display' => $this->formatQuantity($quantity),
                    'unit' => strtoupper($item['unit'] ?? 'KG'),
                    'unit_price_display' => $this->formatCurrency($unitPrice),
                    'discount_label' => $this->formatDiscountLabel($discountValue, $discountType),
                    'tax_label' => ! empty($item['apply_tax']) ? 'PPN 11%' : 'Non PPN',
                    'line_total_display' => $this->formatCurrency($total),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildSummary(LiveChickenPurchaseOrder $purchaseOrder): array
    {
        return Collection::make([
            ['Subtotal', $purchaseOrder->subtotal, 'currency'],
            ['Diskon', $purchaseOrder->global_discount_value, 'currency'],
            ['Total Pajak', $purchaseOrder->tax_total, 'currency'],
            ['Total Akhir', $purchaseOrder->grand_total, 'currency'],
            ['Total Berat (Kg)', $purchaseOrder->total_weight_kg, 'quantity'],
            ['Total Ekor', $purchaseOrder->total_quantity_ea, 'quantity'],
        ])->map(function (array $row): array {
            [$label, $value, $type] = $row;

            return [
                'label' => $label,
                'value' => $value,
                'formatted' => $type === 'currency'
                    ? $this->formatCurrency($this->sanitizeNumber($value))
                    : $this->formatQuantity($this->sanitizeNumber($value)),
            ];
        })->all();
    }

    protected function formatPaymentTerm(?string $term, ?string $description): ?string
    {
        $options = [
            'manual' => 'Manual',
            'cod' => '0 Hari (COD)',
            'net_7' => 'Net 7',
            'net_15' => 'Net 15',
            'net_30' => 'Net 30',
            'net_45' => 'Net 45',
            'net_60' => 'Net 60',
        ];

        $label = $options[$term] ?? $term;

        if (blank($label) && blank($description)) {
            return null;
        }

        return trim(collect([$label, $description])->filter()->implode(' – '));
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

        $discountAmount = $discountType === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE
            ? min($discountValue, 100) / 100 * $gross
            : min($discountValue, $gross);

        return max($gross - $discountAmount, 0);
    }

    protected function formatCurrency(float $value): string
    {
        return number_format($value, 0, ',', '.');
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
