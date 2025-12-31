<?php

namespace App\Filament\Admin\Resources\GoodsReceipts\Tables;

use App\Models\GoodsReceipt;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class GoodsReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('No. Penerimaan')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('No. PO')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('destinationWarehouse.name')
                    ->label('Gudang Tujuan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => GoodsReceipt::statusOptions()[$state] ?? 'Draft')
                    ->colors([
                        'primary' => GoodsReceipt::STATUS_DRAFT,
                        'warning' => GoodsReceipt::STATUS_INSPECTED,
                        'success' => GoodsReceipt::STATUS_POSTED,
                    ]),
                TextColumn::make('total_received_weight_kg')
                    ->label('Berat Masuk (Kg)')
                    ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2, ',', '.')),
                TextColumn::make('received_at')
                    ->label('Tanggal Terima')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                SelectFilter::make('destination_warehouse_id')
                    ->label('Gudang Tujuan')
                    ->relationship('destinationWarehouse', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status Penerimaan')
                    ->options(GoodsReceipt::statusOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
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
