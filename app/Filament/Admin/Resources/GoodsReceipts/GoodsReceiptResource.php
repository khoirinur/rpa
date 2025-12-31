<?php

namespace App\Filament\Admin\Resources\GoodsReceipts;

use App\Filament\Admin\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Filament\Admin\Resources\GoodsReceipts\Pages\EditGoodsReceipt;
use App\Filament\Admin\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Admin\Resources\GoodsReceipts\Schemas\GoodsReceiptForm;
use App\Filament\Admin\Resources\GoodsReceipts\Tables\GoodsReceiptsTable;
use App\Models\GoodsReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'receipt_number';

    public static function getNavigationLabel(): string
    {
        return 'Penerimaan Barang';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Pembelian';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Penerimaan Barang';
    }

    public static function getModelLabel(): string
    {
        return 'Penerimaan';
    }

    public static function form(Schema $schema): Schema
    {
        return GoodsReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoodsReceiptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
            'create' => CreateGoodsReceipt::route('/create'),
            'edit' => EditGoodsReceipt::route('/{record}/edit'),
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
