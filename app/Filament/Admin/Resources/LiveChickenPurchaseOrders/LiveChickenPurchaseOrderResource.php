<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders;

use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Pages\CreateLiveChickenPurchaseOrder;
use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Pages\EditLiveChickenPurchaseOrder;
use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Pages\ListLiveChickenPurchaseOrders;
use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Schemas\LiveChickenPurchaseOrderForm;
use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Tables\LiveChickenPurchaseOrdersTable;
use App\Models\LiveChickenPurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class LiveChickenPurchaseOrderResource extends Resource
{
    protected static ?string $model = LiveChickenPurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'po_number';

    public static function getNavigationLabel(): string
    {
        return 'Pembelian Ayam Hidup';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Pembelian';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pembelian Ayam Hidup';
    }

    public static function getModelLabel(): string
    {
        return 'Pembelian Ayam Hidup';
    }

    public static function form(Schema $schema): Schema
    {
        return LiveChickenPurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LiveChickenPurchaseOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLiveChickenPurchaseOrders::route('/'),
            'create' => CreateLiveChickenPurchaseOrder::route('/create'),
            'edit' => EditLiveChickenPurchaseOrder::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function hydrateLineItemsFromMetadata(array $data): array
    {
        $metadata = $data['metadata'] ?? [];

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $lineItems = data_get($metadata, 'line_items', []);

        if (! is_array($lineItems)) {
            $lineItems = [];
        }

        $lineItemsState = [];

        foreach ($lineItems as $item) {
            $lineItemsState[(string) Str::uuid()] = $item;
        }

        $data['line_items'] = $lineItemsState;

        return $data;
    }

    public static function persistLineItemsIntoMetadata(array $data): array
    {
        $lineItemsState = $data['line_items'] ?? [];

        if (! is_array($lineItemsState)) {
            $lineItemsState = [];
        }

        $lineItems = array_values($lineItemsState);
        unset($data['line_items']);

        $metadata = $data['metadata'] ?? [];

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['line_items'] = $lineItems;

        $data['metadata'] = empty($metadata) ? null : $metadata;

        return $data;
    }
}
