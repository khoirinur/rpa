<?php

namespace App\Filament\Admin\Resources\ChartOfAccounts\Tables;

use App\Models\ChartOfAccount;
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

class ChartOfAccountsTable
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
                    ->label('Nama Akun')
                    ->description(fn (ChartOfAccount $record): ?string => $record->parent?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ChartOfAccount::typeOptions()[$state] ?? 'Tidak Ditandai'),
                TextColumn::make('normal_balance')
                    ->label('Saldo Normal')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ChartOfAccount::normalBalanceOptions()[$state] ?? '—'),
                TextColumn::make('defaultWarehouse.name')
                    ->label('Gudang Default')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('opening_balance')
                    ->label('Saldo Awal')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 2, ',', '.')),
                IconColumn::make('is_summary')
                    ->label('Ringkasan')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe Akun')
                    ->options(ChartOfAccount::typeOptions()),
                SelectFilter::make('normal_balance')
                    ->label('Saldo Normal')
                    ->options(ChartOfAccount::normalBalanceOptions()),
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
