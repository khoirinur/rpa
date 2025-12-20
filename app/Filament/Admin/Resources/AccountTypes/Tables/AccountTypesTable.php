<?php

namespace App\Filament\Admin\Resources\AccountTypes\Tables;

use App\Models\AccountType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AccountTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Tipe')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('category')
                    ->label('Kategori')
                    ->colors([
                        'gray' => ['akumulasi_penyusutan'],
                        'primary' => [
                            'aset_lainnya',
                            'aset_lancar_lainnya',
                            'aset_tetap',
                            'kas_bank',
                            'persediaan',
                            'piutang_usaha',
                        ],
                        'danger' => [
                            'beban',
                            'beban_lainnya',
                            'beban_pokok_penjualan',
                        ],
                        'success' => ['pendapatan', 'pendapatan_lainnya'],
                        'warning' => [
                            'liabilitas_jangka_panjang',
                            'liabilitas_jangka_pendek',
                            'utang_usaha',
                        ],
                        'info' => ['modal'],
                    ])
                    ->formatStateUsing(fn (string $state): string => AccountType::categoryOptions()[$state] ?? ucfirst($state)),
                TextColumn::make('defaultWarehouse.name')
                    ->label('Gudang Default')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(AccountType::categoryOptions()),
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
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
