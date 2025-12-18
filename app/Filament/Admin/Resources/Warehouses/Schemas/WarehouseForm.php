<?php

namespace App\Filament\Admin\Resources\Warehouses\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Gudang')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Gudang')
                            ->required()
                            ->maxLength(10)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Gunakan kode singkat seperti PBRK, PGU, TNJG.'),
                        TextInput::make('name')
                            ->label('Nama Gudang')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),
                        TextInput::make('location')
                            ->label('Lokasi')
                            ->placeholder('Kota / Kecamatan')
                            ->maxLength(120),
                        TextInput::make('capacity_kg')
                            ->label('Kapasitas (Kg)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('Kg'),
                    ])
                    ->columns(2),
                Section::make('Kontak & Status')
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Penanggung Jawab')
                            ->maxLength(120),
                        TextInput::make('contact_phone')
                            ->label('Kontak')
                            ->tel()
                            ->maxLength(25)
                            ->helperText('Isi nomor WhatsApp / telepon yang aktif.'),
                        Toggle::make('is_default')
                            ->label('Jadikan Gudang Default?')
                            ->default(false)
                            ->inline(false)
                            ->helperText('Gudang default dipakai sebagai fallback ketika transaksi tidak memilih gudang.'),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan Internal')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
