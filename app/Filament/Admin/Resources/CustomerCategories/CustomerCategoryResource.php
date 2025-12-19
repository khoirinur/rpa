<?php

namespace App\Filament\Admin\Resources\CustomerCategories;

use App\Filament\Admin\Resources\CustomerCategories\Pages\CreateCustomerCategory;
use App\Filament\Admin\Resources\CustomerCategories\Pages\EditCustomerCategory;
use App\Filament\Admin\Resources\CustomerCategories\Pages\ListCustomerCategories;
use App\Filament\Admin\Resources\CustomerCategories\Schemas\CustomerCategoryForm;
use App\Filament\Admin\Resources\CustomerCategories\Tables\CustomerCategoriesTable;
use App\Models\CustomerCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerCategoryResource extends Resource
{
    protected static ?string $model = CustomerCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Kategori Customer';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Master Kategori Customer';
    }

    public static function getModelLabel(): string
    {
        return 'Kategori Customer';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) CustomerCategory::query()->where('is_active', true)->count();
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerCategories::route('/'),
            'create' => CreateCustomerCategory::route('/create'),
            'edit' => EditCustomerCategory::route('/{record}/edit'),
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
