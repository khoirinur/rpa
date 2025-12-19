<?php

namespace App\Filament\Admin\Resources\SupplierImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Berkas Import Supplier')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Berkas supplier.csv')
                            ->required()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->directory('imports/suppliers')
                            ->disk('public')
                            ->preserveFilenames()
                            ->storeFileNamesIn('file_name')
                            ->visibility('public')
                            ->helperText('Kolom: Kode, NPWP, Tipe, Nama, Pemilik, Kontak, Atas Nama, Bank, Rekening, Alamat.')
                            ->openable()
                            ->downloadable()
                            ->dehydrated(fn ($state, ?string $operation): bool => $operation === 'create')
                            ->disabledOn('edit'),
                        Select::make('fallback_supplier_category_id')
                            ->label('Kategori Bawaan (Opsional)')
                            ->relationship('fallbackCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable()
                            ->helperText('Dipakai jika kolom tipe di CSV kosong/tidak terkenal.'),
                        Select::make('default_warehouse_id')
                            ->label('Gudang Default (Opsional)')
                            ->relationship('defaultWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable()
                            ->helperText('Digunakan jika kategori tidak punya gudang bawaan.'),
                    ])
                    ->columns(2),
                Section::make('Ringkasan Import')
                    ->schema([
                        TextInput::make('status')
                            ->label('Status')
                            ->disabled(),
                        TextInput::make('total_rows')
                            ->label('Total Baris')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('imported_rows')
                            ->label('Berhasil')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('failed_rows')
                            ->label('Gagal')
                            ->numeric()
                            ->disabled(),
                        Textarea::make('log')
                            ->label('Log Import')
                            ->rows(6)
                            ->columnSpanFull()
                            ->disabled()
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'Belum ada log.';
                                }

                                if (is_array($state)) {
                                    return implode(PHP_EOL, $state);
                                }

                                return (string) $state;
                            }),
                    ])
                    ->columns(3),
            ]);
    }
}
