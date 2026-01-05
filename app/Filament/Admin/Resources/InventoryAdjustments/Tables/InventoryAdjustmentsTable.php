<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Tables;

use App\Models\InventoryAdjustment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryAdjustmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adjustment_number')
                    ->label('No. Penyesuaian')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('adjustment_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('defaultWarehouse.name')
                    ->label('Gudang Default')
                    ->placeholder('Gudang belum diatur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('adjustmentAccount.name')
                    ->label('Akun Penyesuaian')
                    ->formatStateUsing(fn ($state, InventoryAdjustment $record): string => $record->adjustmentAccount
                        ? sprintf('%s — %s', $record->adjustmentAccount->code, $record->adjustmentAccount->name)
                        : 'Belum diatur')
                    ->wrap(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Jumlah Item')
                    ->sortable(),
                TextColumn::make('total_addition_value')
                    ->label('Total Biaya Penambahan')
                    ->money('IDR', true)
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('adjustment_date', 'desc')
            ->filters([
                Filter::make('adjustment_date')
                    ->label('Rentang Tanggal')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Sampai')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('adjustment_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('adjustment_date', '<=', $date));
                    }),
                SelectFilter::make('default_warehouse_id')
                    ->label('Gudang')
                    ->relationship('defaultWarehouse', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('adjustment_account_id')
                    ->label('Akun Penyesuaian')
                    ->relationship('adjustmentAccount', 'name')
                    ->searchable()
                    ->preload(),
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
