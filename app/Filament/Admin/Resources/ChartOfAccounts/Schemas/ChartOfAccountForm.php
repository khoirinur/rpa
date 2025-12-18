<?php

namespace App\Filament\Admin\Resources\ChartOfAccounts\Schemas;

use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChartOfAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Akun')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Akun')
                            ->required()
                            ->maxLength(20)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Contoh: 1101, 5101, atau kode gabungan huruf-angka.'),
                        TextInput::make('name')
                            ->label('Nama Akun')
                            ->required()
                            ->maxLength(150),
                        Select::make('type')
                            ->label('Tipe Akun')
                            ->options(ChartOfAccount::typeOptions())
                            ->required()
                            ->native(false),
                        Select::make('normal_balance')
                            ->label('Saldo Normal')
                            ->options(ChartOfAccount::normalBalanceOptions())
                            ->required()
                            ->native(false)
                            ->helperText('Pastikan sesuai standar akuntansi untuk laporan HPP.'),
                    ])
                    ->columns(2),
                Section::make('Struktur & Gudang')
                    ->schema([
                        Select::make('parent_id')
                            ->label('Akun Induk')
                            ->relationship('parent', 'name', fn ($query) => $query->where('is_summary', true)->orderBy('code'))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Gunakan akun induk bertipe summary agar struktur parent-child rapi.')
                            ->nullable(),
                        Select::make('default_warehouse_id')
                            ->label('Gudang Default')
                            ->relationship('defaultWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Pastikan multi-gudang tercatat pada akun biaya/pendapatan yang relevan.')
                            ->nullable(),
                        Toggle::make('is_summary')
                            ->label('Hanya Ringkasan (Tidak Bisa Posting)')
                            ->helperText('Aktifkan jika akun ini hanya sebagai grup induk.')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Saldo Awal & Catatan')
                    ->schema([
                        TextInput::make('opening_balance')
                            ->label('Saldo Awal')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->helperText('Saldo awal per gudang bisa diatur menggunakan jurnal pembukaan jika berbeda.'),
                        Textarea::make('description')
                            ->label('Catatan Internal')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
