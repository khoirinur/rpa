<?php

namespace App\Filament\Admin\Resources\PurchaseOrderOutputs;

use App\Filament\Admin\Resources\PurchaseOrderOutputs\Pages\CreatePurchaseOrderOutput;
use App\Filament\Admin\Resources\PurchaseOrderOutputs\Pages\EditPurchaseOrderOutput;
use App\Filament\Admin\Resources\PurchaseOrderOutputs\Pages\ListPurchaseOrderOutputs;
use App\Filament\Admin\Resources\PurchaseOrderOutputs\Schemas\PurchaseOrderOutputForm;
use App\Filament\Admin\Resources\PurchaseOrderOutputs\Tables\PurchaseOrderOutputsTable;
use App\Models\PurchaseOrderOutput;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseOrderOutputResource extends Resource
{
    protected static ?string $model = PurchaseOrderOutput::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentDuplicate;

    protected static ?string $recordTitleAttribute = 'document_number';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Output PO';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Pembelian';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Output PO';
    }

    public static function getModelLabel(): string
    {
        return 'Output PO';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_any_purchase_order_output') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PurchaseOrderOutput::query()
            ->where('status', PurchaseOrderOutput::STATUS_READY)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderOutputForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrderOutputsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrderOutputs::route('/'),
            'create' => CreatePurchaseOrderOutput::route('/create'),
            'edit' => EditPurchaseOrderOutput::route('/{record}/edit'),
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
