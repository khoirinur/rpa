<?php

namespace App\Filament\Admin\Resources\Units\Tables;

use App\Models\Unit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Satuan')
                    ->description(fn (Unit $record): ?string => match ($record->measurement_type) {
                        'weight' => 'Berbasis Berat',
                        'count' => 'Berbasis Jumlah',
                        'package' => 'Berbasis Kemasan',
                        default => null,
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('decimal_places')
                    ->label('Desimal')
                    ->alignCenter(),
                IconColumn::make('is_decimal')
                    ->label('Angka Desimal')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('measurement_type')
                    ->label('Jenis Pengukuran')
                    ->options([
                        'weight' => 'Berat',
                        'count' => 'Jumlah',
                        'package' => 'Kemasan',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->placeholder('Semua'),
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
