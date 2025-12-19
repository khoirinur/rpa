<?php

namespace App\Filament\Admin\Resources\ProductImports;

use App\Filament\Admin\Resources\ProductImports\Pages\CreateProductImport;
use App\Filament\Admin\Resources\ProductImports\Pages\EditProductImport;
use App\Filament\Admin\Resources\ProductImports\Pages\ListProductImports;
use App\Filament\Admin\Resources\ProductImports\Schemas\ProductImportForm;
use App\Filament\Admin\Resources\ProductImports\Tables\ProductImportsTable;
use App\Models\ProductImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductImportResource extends Resource
{
    protected static ?string $model = ProductImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquareStack;

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getNavigationLabel(): string
    {
        return 'Import Produk';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import Produk';
    }

    public static function getModelLabel(): string
    {
        return 'Import Produk';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductImports::route('/'),
            'create' => CreateProductImport::route('/create'),
            'edit' => EditProductImport::route('/{record}/edit'),
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
