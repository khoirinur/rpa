<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use function normalize_item_name;

class GoodsReceiptPrintController extends Controller
{
    public function __invoke(GoodsReceipt $goodsReceipt, Request $request): View
    {
        $user = $request->user();
        $canView = $user && (
            $user->can('view_goods_receipt') ||
            $user->can('view_any_goods_receipt')
        );

        abort_unless($canView, 403);

        $goodsReceipt->loadMissing([
            'supplier',
            'destinationWarehouse',
            'purchaseOrder',
            'items' => fn ($query) => $query->with('warehouse')->orderBy('id'),
        ]);

        $metadata = $this->buildMetadata($goodsReceipt, $user?->name);
        $lineItems = $this->mapLineItems($goodsReceipt);
        $summary = $this->buildSummary($goodsReceipt);

        return view('documents.goods-receipt-print', [
            'goodsReceipt' => $goodsReceipt,
            'metadata' => $metadata,
            'lineItems' => $lineItems,
            'summary' => $summary,
        ]);
    }

    protected function buildMetadata(GoodsReceipt $receipt, ?string $generatedBy): array
    {
        $companyAddress = config('app.company_address', 'Jl. Totok Kerot, Suko, Menang, Kec. Pagu Kab. Kediri Jawa Timur 64183 Indonesia');
        $addressLines = array_values(array_filter(preg_split("/(\r\n|\r|\n)/", trim($companyAddress)) ?: []));

        return array_filter([
            'title' => sprintf('Print %s', $receipt->receipt_number ?? 'Penerimaan Barang'),
            'company_name' => config('app.company_name', 'PT Surya Kencana Slaughterhouse'),
            'company_address' => $companyAddress,
            'company_city' => config('app.company_city', 'Kediri'),
            'company_phone' => config('app.company_phone', '+62 812 1579 9522'),
            'company_address_lines' => $addressLines,
            'supplier_name' => $receipt->supplier?->name,
            'supplier_address' => $receipt->supplier?->address_line,
            'warehouse_name' => $receipt->destinationWarehouse?->name,
            'form_number' => $receipt->receipt_number,
            'invoice_number' => $receipt->supplier_delivery_note_number,
            'received_date' => optional($receipt->received_at)?->translatedFormat('d M Y'),
            'vehicle_plate' => $receipt->vehicle_plate_number,
            'arrival_inspector_name' => $receipt->arrival_inspector_name,
            'arrival_notes' => $receipt->arrival_notes ?? $receipt->notes,
            'document_generated_at' => now()->translatedFormat('d M Y, H:i'),
            'generated_by' => $generatedBy,
            'po_number' => $receipt->purchaseOrder?->po_number,
        ], fn ($value) => filled($value));
    }

    protected function mapLineItems(GoodsReceipt $receipt): array
    {
        $items = Collection::make($receipt->items)->values();

        return $items->map(function (GoodsReceiptItem $item, int $index) use ($receipt): array {
            $unit = strtoupper($item->unit ?? 'KG');
            $quantity = $this->resolveQuantityValue($item);

            return [
                'index' => $index + 1,
                'po_number' => $receipt->purchaseOrder?->po_number,
                'warehouse' => $item->warehouse?->name ?? $receipt->destinationWarehouse?->name ?? '—',
                'item_code' => $item->item_code ?: '—',
                'item_name' => normalize_item_name($item->item_name) ?? 'Item tanpa nama',
                'quantity_display' => $this->formatDecimal($quantity, 3),
                'unit' => $unit,
            ];
        })->all();
    }

    protected function buildSummary(GoodsReceipt $receipt): array
    {
        $rows = Collection::make([
            ['Total Item', $receipt->total_item_count, 'integer'],
            ['Qty Diterima (Ekor)', $receipt->total_received_quantity_ea, 'quantity'],
            ['Berat Diterima (Kg)', $receipt->total_received_weight_kg, 'quantity'],
            ['Susut (Kg)', $receipt->loss_weight_kg, 'quantity'],
            ['Susut (%)', $receipt->loss_percentage, 'percentage'],
        ]);

        return $rows->map(function (array $row): array {
            [$label, $value, $type] = $row;
            $number = $this->sanitizeNumber($value);

            return [
                'label' => $label,
                'value' => $number,
                'formatted' => match ($type) {
                    'percentage' => $this->formatDecimal($number, 2) . ' %',
                    default => $this->formatDecimal($number, $type === 'integer' ? 0 : 3),
                },
            ];
        })->all();
    }

    protected function resolveQuantityValue(GoodsReceiptItem $item): float
    {
        $unit = strtolower((string) ($item->unit ?? ''));
        $quantity = $this->sanitizeNumber($item->received_quantity ?? 0);
        $weight = $this->sanitizeNumber($item->received_weight_kg ?? 0);

        if (in_array($unit, ['kg', 'kilogram', 'kilograms', 'kilogram(s)'])) {
            return $weight > 0 ? $weight : $quantity;
        }

        return $quantity > 0 ? $quantity : $weight;
    }

    protected function sanitizeNumber(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);

        if ($clean === '' || $clean === '-') {
            return 0.0;
        }

        $sign = str_starts_with($clean, '-') ? -1 : 1;
        $clean = ltrim($clean, '-');

        $decimalSeparator = null;
        $lastDot = strrpos($clean, '.');
        $lastComma = strrpos($clean, ',');

        if ($lastDot !== false && $lastComma !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $decimalSeparator = ',';
        } elseif ($lastDot !== false) {
            $decimalSeparator = '.';
        }

        if ($decimalSeparator) {
            $integerPart = str_replace(['.', ','], '', substr($clean, 0, strrpos($clean, $decimalSeparator)));
            $fractionPart = substr($clean, strrpos($clean, $decimalSeparator) + 1);
            $clean = $integerPart . '.' . $fractionPart;
        }

        return $sign * (float) $clean;
    }

    protected function formatDecimal(float $value, int $decimals = 2): string
    {
        $formatted = number_format($value, $decimals, ',', '.');

        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted === '' ? '0' : $formatted;
    }
}
