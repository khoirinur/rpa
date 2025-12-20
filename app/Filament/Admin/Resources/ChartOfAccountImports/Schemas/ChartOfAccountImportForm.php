<?php

namespace App\Filament\Admin\Resources\ChartOfAccountImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChartOfAccountImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Berkas Import')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Berkas CSV')
                            ->required()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->directory('imports/coa')
                            ->disk('public')
                            ->preserveFilenames()
                            ->storeFileNamesIn('file_name')
                            ->helperText('Gunakan template daftar akun perkiraan.csv. Pastikan kolom sesuai urutan: Kode, Nama, Tipe, Induk.')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->dehydrated(fn ($state, ?string $operation): bool => $operation === 'create')
                            ->disabledOn('edit'),
                    ])
                    ->columns(2),
                Section::make('Ringkasan Proses')
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
