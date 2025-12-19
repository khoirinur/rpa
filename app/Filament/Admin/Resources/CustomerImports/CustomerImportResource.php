<?php

namespace App\Filament\Admin\Resources\CustomerImports;

use App\Filament\Admin\Resources\CustomerImports\Pages\CreateCustomerImport;
use App\Filament\Admin\Resources\CustomerImports\Pages\EditCustomerImport;
use App\Filament\Admin\Resources\CustomerImports\Pages\ListCustomerImports;
use App\Filament\Admin\Resources\CustomerImports\Schemas\CustomerImportForm;
use App\Filament\Admin\Resources\CustomerImports\Tables\CustomerImportsTable;
use App\Models\CustomerImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerImportResource extends Resource
{
    protected static ?string $model = CustomerImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquareStack;

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getNavigationLabel(): string
    {
        return 'Import Customer';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import Customer';
    }

    public static function getModelLabel(): string
    {
        return 'Import Customer';
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerImports::route('/'),
            'create' => CreateCustomerImport::route('/create'),
            'edit' => EditCustomerImport::route('/{record}/edit'),
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
