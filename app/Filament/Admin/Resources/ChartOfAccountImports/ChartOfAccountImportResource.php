<?php

namespace App\Filament\Admin\Resources\ChartOfAccountImports;

use App\Filament\Admin\Resources\ChartOfAccountImports\Pages\CreateChartOfAccountImport;
use App\Filament\Admin\Resources\ChartOfAccountImports\Pages\EditChartOfAccountImport;
use App\Filament\Admin\Resources\ChartOfAccountImports\Pages\ListChartOfAccountImports;
use App\Filament\Admin\Resources\ChartOfAccountImports\Schemas\ChartOfAccountImportForm;
use App\Filament\Admin\Resources\ChartOfAccountImports\Tables\ChartOfAccountImportsTable;
use App\Models\ChartOfAccountImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChartOfAccountImportResource extends Resource
{
    protected static ?string $model = ChartOfAccountImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquareStack;

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function getNavigationLabel(): string
    {
        return 'Import COA';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import Chart of Accounts';
    }

    public static function getModelLabel(): string
    {
        return 'Import COA';
    }

    public static function form(Schema $schema): Schema
    {
        return ChartOfAccountImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChartOfAccountImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChartOfAccountImports::route('/'),
            'create' => CreateChartOfAccountImport::route('/create'),
            'edit' => EditChartOfAccountImport::route('/{record}/edit'),
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
