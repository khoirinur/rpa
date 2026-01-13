<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices\Tables;

use App\Models\PurchaseInvoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('No. Faktur')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('destinationWarehouse.name')
                    ->label('Gudang Tujuan')
                    ->placeholder('Gudang belum diatur')
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->label('Tanggal Faktur')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (PurchaseInvoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('grand_total')
                    ->label('Total Faktur')
                    ->money('IDR', true)
                    ->sortable(),
                TextColumn::make('paid_total')
                    ->label('Total Pembayaran')
                    ->money('IDR', true)
                    ->sortable(),
                TextColumn::make('balance_due')
                    ->label('Sisa Bayar')
                    ->money('IDR', true)
                    ->color(fn (?string $state): ?string => (float) $state > 0 ? 'warning' : 'success')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Jumlah Item')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => [PurchaseInvoice::STATUS_DRAFT],
                        'warning' => [PurchaseInvoice::STATUS_REVIEW],
                        'success' => [PurchaseInvoice::STATUS_APPROVED, PurchaseInvoice::STATUS_POSTED],
                        'danger' => [PurchaseInvoice::STATUS_VOID],
                    ])
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Status Pembayaran')
                    ->badge()
                    ->colors([
                        'danger' => [PurchaseInvoice::PAYMENT_STATUS_UNPAID],
                        'warning' => [PurchaseInvoice::PAYMENT_STATUS_PARTIALLY_PAID],
                        'success' => [PurchaseInvoice::PAYMENT_STATUS_PAID],
                    ])
                    ->sortable(),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->filters([
                Filter::make('invoice_date')
                    ->label('Tanggal Faktur')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Sampai')
                            ->native(false),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('invoice_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('invoice_date', '<=', $date));
                    }),
                Filter::make('due_date')
                    ->label('Tanggal Jatuh Tempo')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Sampai')
                            ->native(false),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('due_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('due_date', '<=', $date));
                    }),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('destination_warehouse_id')
                    ->label('Gudang Tujuan')
                    ->relationship('destinationWarehouse', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status Faktur')
                    ->options(PurchaseInvoice::statusOptions()),
                SelectFilter::make('payment_status')
                    ->label('Status Pembayaran')
                    ->options(PurchaseInvoice::paymentStatusOptions()),
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
