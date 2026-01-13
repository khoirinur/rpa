<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices;

use App\Filament\Admin\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Admin\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Admin\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Admin\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Filament\Admin\Resources\PurchaseInvoices\Tables\PurchaseInvoicesTable;
use App\Models\PurchaseInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentDuplicate;

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pembelian';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static ?string $slug = 'purchase-invoices';

    public static function getNavigationLabel(): string
    {
        return 'Faktur Pembelian';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Faktur Pembelian';
    }

    public static function getModelLabel(): string
    {
        return 'Faktur Pembelian';
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
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
