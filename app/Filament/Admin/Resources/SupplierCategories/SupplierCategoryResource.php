<?php

namespace App\Filament\Admin\Resources\SupplierCategories;

use App\Filament\Admin\Resources\SupplierCategories\Pages\CreateSupplierCategory;
use App\Filament\Admin\Resources\SupplierCategories\Pages\EditSupplierCategory;
use App\Filament\Admin\Resources\SupplierCategories\Pages\ListSupplierCategories;
use App\Filament\Admin\Resources\SupplierCategories\Schemas\SupplierCategoryForm;
use App\Filament\Admin\Resources\SupplierCategories\Tables\SupplierCategoriesTable;
use App\Models\SupplierCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierCategoryResource extends Resource
{
    protected static ?string $model = SupplierCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Kategori Supplier';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Master Kategori Supplier';
    }

    public static function getModelLabel(): string
    {
        return 'Kategori Supplier';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) SupplierCategory::query()->where('is_active', true)->count();
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierCategories::route('/'),
            'create' => CreateSupplierCategory::route('/create'),
            'edit' => EditSupplierCategory::route('/{record}/edit'),
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
