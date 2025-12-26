<?php

namespace App\Filament\Admin\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Satuan')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Satuan')
                            ->required()
                            ->maxLength(10)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Contoh: KG, EKR, PCK.'),
                        TextInput::make('name')
                            ->label('Nama Satuan')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),
                        Select::make('measurement_type')
                            ->label('Jenis Pengukuran')
                            ->options([
                                'weight' => 'Berat (Kg, Ton)',
                                'count' => 'Jumlah (Ekor, Unit)',
                                'package' => 'Kemasan (Pack, Karung)',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Pastikan jenis sesuai supaya multi-gudang mencatat stok dengan benar.')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Catatan Tambahan')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Konfigurasi Presisi & Status')
                    ->schema([
                        TextInput::make('decimal_places')
                            ->label('Jumlah Desimal')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(6)
                            ->default(3)
                            ->helperText('Atur presisi perhitungan stok antar gudang.'),
                        Toggle::make('is_decimal')
                            ->label('Gunakan Angka Desimal?')
                            ->default(true),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])
                    ->columns(3),
            ]);
    }
}
