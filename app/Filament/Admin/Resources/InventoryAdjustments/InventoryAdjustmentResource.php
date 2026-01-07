<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments;

use App\Filament\Admin\Resources\InventoryAdjustments\Pages\CreateInventoryAdjustment;
use App\Filament\Admin\Resources\InventoryAdjustments\Pages\EditInventoryAdjustment;
use App\Filament\Admin\Resources\InventoryAdjustments\Pages\ListInventoryAdjustments;
use App\Filament\Admin\Resources\InventoryAdjustments\Schemas\InventoryAdjustmentForm;
use App\Filament\Admin\Resources\InventoryAdjustments\Tables\InventoryAdjustmentsTable;
use App\Models\InventoryAdjustment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryAdjustmentResource extends Resource
{
    protected static ?string $model = InventoryAdjustment::class;

    protected static ?string $slug = 'inventory-adjustments';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static ?string $recordTitleAttribute = 'adjustment_number';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Penyesuaian Persediaan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Penyesuaian Persediaan';
    }

    public static function getModelLabel(): string
    {
        return 'Penyesuaian';
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryAdjustmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryAdjustmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryAdjustments::route('/'),
            'create' => CreateInventoryAdjustment::route('/create'),
            'edit' => EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
