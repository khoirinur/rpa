<?php

namespace App\Filament\Admin\Resources\SupplierImports;

use App\Filament\Admin\Resources\SupplierImports\Pages\CreateSupplierImport;
use App\Filament\Admin\Resources\SupplierImports\Pages\EditSupplierImport;
use App\Filament\Admin\Resources\SupplierImports\Pages\ListSupplierImports;
use App\Filament\Admin\Resources\SupplierImports\Schemas\SupplierImportForm;
use App\Filament\Admin\Resources\SupplierImports\Tables\SupplierImportsTable;
use App\Models\SupplierImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierImportResource extends Resource
{
    protected static ?string $model = SupplierImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquareStack;

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getNavigationLabel(): string
    {
        return 'Import Supplier';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import Supplier';
    }

    public static function getModelLabel(): string
    {
        return 'Import Supplier';
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierImports::route('/'),
            'create' => CreateSupplierImport::route('/create'),
            'edit' => EditSupplierImport::route('/{record}/edit'),
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
