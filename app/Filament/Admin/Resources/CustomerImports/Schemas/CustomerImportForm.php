<?php

namespace App\Filament\Admin\Resources\CustomerImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Berkas Import Customer')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Berkas customer.csv')
                            ->required()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->directory('imports/customers')
                            ->disk('public')
                            ->preserveFilenames()
                            ->storeFileNamesIn('file_name')
                            ->visibility('public')
                            ->helperText('Kolom wajib: Kode Customer, Nama, Nomor Telepon, Alamat, Tipe (Baru/Lama/MBG/Partai/Retail).')
                            ->openable()
                            ->downloadable()
                            ->dehydrated(fn ($state, ?string $operation): bool => $operation === 'create')
                            ->disabledOn('edit'),
                        Select::make('fallback_customer_category_id')
                            ->label('Kategori Bawaan (Opsional)')
                            ->relationship('fallbackCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable()
                            ->helperText('Digunakan ketika kolom tipe di CSV kosong / tidak dikenali.'),
                        Select::make('default_warehouse_id')
                            ->label('Gudang Default (Opsional)')
                            ->relationship('defaultWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Dipakai apabila kategori customer tidak memiliki gudang bawaan.')
                            ->nullable(),
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
