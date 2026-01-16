<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices;

use App\Filament\Admin\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Admin\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Admin\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Admin\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Filament\Admin\Resources\PurchaseInvoices\Tables\PurchaseInvoicesTable;
use App\Models\PurchaseInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use function normalize_item_name;
use function sanitize_decimal;
use function sanitize_positive_decimal;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentDuplicate;

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pembelian';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static ?string $slug = 'purchase-invoices';

    public static function getNavigationLabel(): string
    {
        return 'Faktur Pembelian';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Faktur Pembelian';
    }

    public static function getModelLabel(): string
    {
        return 'Faktur Pembelian';
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function buildMetadata(array $data): array
    {
        $lineItems = Collection::make($data['items'] ?? [])
            ->map(function (array $item) use ($data): array {
                $unit = strtolower((string) ($item['unit'] ?? 'kg'));

                return [
                    'product_id' => $item['product_id'] ?? null,
                    'item_code' => $item['item_code'] ?? null,
                    'item_name' => normalize_item_name($item['item_name'] ?? null),
                    'unit' => $unit,
                    'quantity' => sanitize_positive_decimal($item['quantity'] ?? 0, 3),
                    'unit_price' => sanitize_decimal($item['unit_price'] ?? 0),
                    'discount_type' => $item['discount_type'] ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT,
                    'discount_value' => sanitize_decimal($item['discount_value'] ?? 0),
                    'discount_percentage' => isset($item['discount_percentage'])
                        ? sanitize_decimal($item['discount_percentage'], 4)
                        : null,
                    'apply_tax' => (bool) ($item['apply_tax'] ?? false),
                    'tax_rate' => sanitize_decimal($item['tax_rate'] ?? ($data['tax_rate'] ?? 0), 2),
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            ...($data['metadata'] ?? []),
            'line_items' => $lineItems,
        ];
    }
}
