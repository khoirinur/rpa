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

class LiveChickenPurchaseOrderResource extends Resource
{
    protected static ?string $model = LiveChickenPurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'po_number';

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
}
