<?php

namespace App\Filament\Admin\Resources\InventoryBalances\Schemas;

use App\Filament\Admin\Resources\InventoryBalances\InventoryBalanceResource;
use App\Models\InventoryBalance;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryBalanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Saldo Persediaan')
                    ->schema([
                        TextEntry::make('product.code')
                            ->label('Kode Produk')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ?: '-'),
                        TextEntry::make('product.name')
                            ->label('Nama Produk')
                            ->wrap()
                            ->formatStateUsing(fn ($state): string => $state ?: '-'),
                        TextEntry::make('warehouse.name')
                            ->label('Gudang')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ?: '-'),
                        TextEntry::make('unit.name')
                            ->label('Satuan')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ?: 'Unit'),
                    ])
                    ->columns(2),
                Section::make('Ringkasan Kuantitas')
                    ->schema([
                        TextEntry::make('on_hand_quantity')
                            ->label('On Hand')
                            ->formatStateUsing(fn ($state): string => InventoryBalanceResource::formatQuantity($state)),
                        TextEntry::make('available_quantity')
                            ->label('Tersedia')
                            ->formatStateUsing(fn ($state): string => InventoryBalanceResource::formatQuantity($state)),
                        TextEntry::make('incoming_quantity')
                            ->label('Sedang Masuk')
                            ->formatStateUsing(fn ($state): string => InventoryBalanceResource::formatQuantity($state)),
                        TextEntry::make('reserved_quantity')
                            ->label('Reservasi')
                            ->formatStateUsing(fn ($state): string => InventoryBalanceResource::formatQuantity($state)),
                        TextEntry::make('average_cost')
                            ->label('Biaya Rata-rata')
                            ->formatStateUsing(fn ($state): string => InventoryBalanceResource::formatCurrency($state)),
                        TextEntry::make('last_transaction_at')
                            ->label('Transaksi Terakhir')
                            ->formatStateUsing(fn ($state): string => InventoryBalanceResource::formatDatetime($state)),
                    ])
                    ->columns(3),
                Section::make('Catatan')
                    ->schema([
                        TextEntry::make('last_source_type')
                            ->label('Sumber Perubahan')
                            ->formatStateUsing(fn ($state, ?InventoryBalance $record): string => InventoryBalanceResource::formatSource($record)),
                    ])
                    ->columns(1),
            ]);
    }
}
