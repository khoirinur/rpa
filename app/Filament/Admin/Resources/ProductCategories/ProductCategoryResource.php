<?php

namespace App\Filament\Admin\Resources\ProductCategories;

use App\Filament\Admin\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Admin\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Admin\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Admin\Resources\ProductCategories\Schemas\ProductCategoryForm;
use App\Filament\Admin\Resources\ProductCategories\Tables\ProductCategoriesTable;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Kategori Produk';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Master Kategori Produk';
    }

    public static function getModelLabel(): string
    {
        return 'Kategori Produk';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ProductCategory::query()->where('is_active', true)->count();
    }

    public static function form(Schema $schema): Schema
    {
        return ProductCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCategories::route('/'),
            'create' => CreateProductCategory::route('/create'),
            'edit' => EditProductCategory::route('/{record}/edit'),
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
