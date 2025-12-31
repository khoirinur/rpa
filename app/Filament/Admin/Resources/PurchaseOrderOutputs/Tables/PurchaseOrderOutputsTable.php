<?php

namespace App\Filament\Admin\Resources\PurchaseOrderOutputs\Tables;

use App\Models\PurchaseOrderOutput;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PurchaseOrderOutputsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No. Dokumen')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Ayam Hidup')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('document_title')
                    ->label('Judul')
                    ->limit(40)
                    ->tooltip(fn (PurchaseOrderOutput $record): ?string => $record->document_title)
                    ->searchable(),
                TextColumn::make('document_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => fn ($state): bool => $state === PurchaseOrderOutput::STATUS_DRAFT,
                        'info' => fn ($state): bool => $state === PurchaseOrderOutput::STATUS_READY,
                        'success' => fn ($state): bool => $state === PurchaseOrderOutput::STATUS_PUBLISHED,
                    ])
                    ->sortable(),
                TextColumn::make('layout_template')
                    ->label('Template')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('printedBy.name')
                    ->label('Operator Cetak')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('printed_at')
                    ->label('Dicetak Pada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('document_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PurchaseOrderOutput::statusOptions()),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                SelectFilter::make('layout_template')
                    ->label('Template')
                    ->options(PurchaseOrderOutput::layoutTemplateOptions())
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
                Action::make('previewDocument')
                    ->label('Pratinjau')
                    ->icon('heroicon-o-eye')
                    ->url(fn (PurchaseOrderOutput $record): string => route('purchase-order-outputs.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => auth()->user()?->can('view_purchase_order_output') ?? false),
                Action::make('downloadPdf')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (PurchaseOrderOutput $record): string => route('purchase-order-outputs.print', [
                        'purchaseOrderOutput' => $record,
                        'format' => 'pdf',
                        'download' => true,
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => auth()->user()?->can('view_purchase_order_output') ?? false),
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
