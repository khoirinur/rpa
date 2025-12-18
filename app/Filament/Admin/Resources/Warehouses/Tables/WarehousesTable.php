<?php

namespace App\Filament\Admin\Resources\Warehouses\Tables;

use App\Models\Warehouse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama Gudang')
                    ->description(fn (Warehouse $record): ?string => $record->location ?: '—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('capacity_kg')
                    ->label('Kapasitas (Kg)')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2) . ' Kg' : '—'),
                TextColumn::make('contact_name')
                    ->label('Penanggung Jawab')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('contact_phone')
                    ->label('Kontak')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-m-star')
                    ->falseIcon('heroicon-m-minus-small')
                    ->trueColor('warning'),
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
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
                SelectFilter::make('is_default')
                    ->label('Default')
                    ->options([
                        '1' => 'Default',
                        '0' => 'Bukan Default',
                    ]),
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
