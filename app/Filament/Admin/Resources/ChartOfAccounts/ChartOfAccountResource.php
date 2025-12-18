<?php

namespace App\Filament\Admin\Resources\ChartOfAccounts;

use App\Filament\Admin\Resources\ChartOfAccounts\Pages\CreateChartOfAccount;
use App\Filament\Admin\Resources\ChartOfAccounts\Pages\EditChartOfAccount;
use App\Filament\Admin\Resources\ChartOfAccounts\Pages\ListChartOfAccounts;
use App\Filament\Admin\Resources\ChartOfAccounts\Schemas\ChartOfAccountForm;
use App\Filament\Admin\Resources\ChartOfAccounts\Tables\ChartOfAccountsTable;
use App\Models\ChartOfAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = ChartOfAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'COA';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Chart of Accounts';
    }

    public static function getModelLabel(): string
    {
        return 'Akun';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ChartOfAccount::query()->where('is_active', true)->count();
    }

    public static function form(Schema $schema): Schema
    {
        return ChartOfAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChartOfAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChartOfAccounts::route('/'),
            'create' => CreateChartOfAccount::route('/create'),
            'edit' => EditChartOfAccount::route('/{record}/edit'),
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
