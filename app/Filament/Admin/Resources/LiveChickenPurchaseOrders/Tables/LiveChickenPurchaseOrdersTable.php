<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Tables;

use App\Models\LiveChickenPurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LiveChickenPurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('No. PO')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('destinationWarehouse.name')
                    ->label('Gudang Tujuan')
                    ->toggleable()
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => [LiveChickenPurchaseOrder::STATUS_DRAFT],
                        'warning' => [LiveChickenPurchaseOrder::STATUS_SUBMITTED],
                        'primary' => [LiveChickenPurchaseOrder::STATUS_APPROVED],
                        'success' => [LiveChickenPurchaseOrder::STATUS_COMPLETED],
                    ])
                    ->formatStateUsing(fn (?string $state): string => LiveChickenPurchaseOrder::statusOptions()[$state] ?? '—')
                    ->sortable(),
                TextColumn::make('order_date')
                    ->label('Tanggal PO')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('delivery_date')
                    ->label('Tanggal Kirim')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('total_quantity_ea')
                    ->label('Total Ekor')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('total_weight_kg')
                    ->label('Berat (Kg)')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . ' Kg')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('grand_total')
                    ->label('Total Akhir')
                    ->money('IDR')
                    ->sortable(),
                IconColumn::make('deleted_at')
                    ->label('Terhapus?')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(LiveChickenPurchaseOrder::statusOptions()),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                SelectFilter::make('destination_warehouse_id')
                    ->label('Gudang Tujuan')
                    ->relationship('destinationWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('order_date')
                    ->label('Rentang Tanggal PO')
                    ->form([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('order_date', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('order_date', '<=', $date));
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim PO untuk persetujuan?')
                    ->visible(fn (LiveChickenPurchaseOrder $record): bool => $record->status === LiveChickenPurchaseOrder::STATUS_DRAFT)
                    ->action(fn (LiveChickenPurchaseOrder $record): bool => $record->update(['status' => LiveChickenPurchaseOrder::STATUS_SUBMITTED])),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui PO ini?')
                    ->visible(fn (LiveChickenPurchaseOrder $record): bool => $record->status === LiveChickenPurchaseOrder::STATUS_SUBMITTED)
                    ->action(fn (LiveChickenPurchaseOrder $record): bool => $record->update(['status' => LiveChickenPurchaseOrder::STATUS_APPROVED])),
                Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (LiveChickenPurchaseOrder $record): string => route('live-chicken-purchase-orders.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => auth()->user()?->can('view_live_chicken_purchase_order') ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
