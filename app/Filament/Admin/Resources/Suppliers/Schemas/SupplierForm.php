<?php

namespace App\Filament\Admin\Resources\Suppliers\Schemas;

use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Grid::make()
                    ->columns(1)
                    ->schema([
                        Section::make('Identitas Supplier')
                            ->schema([
                                TextInput::make('code')
                                    ->label('Kode Pemasok')
                                    ->required()
                                    ->maxLength(20)
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Gunakan format S-XXXX agar konsisten.')
                                    ->suffixAction(
                                        Action::make('generate_code')
                                            ->label('Generate')
                                            ->icon('heroicon-m-sparkles')
                                            ->action(function (Set $set): void {
                                                $set('code', sprintf('S-%04d', random_int(1, 9999)));
                                            }),
                                    ),
                                TextInput::make('name')
                                    ->label('Nama Supplier')
                                    ->required()
                                    ->maxLength(150),
                                Select::make('supplier_category_id')
                                    ->label('Kategori Supplier')
                                    ->relationship('supplierCategory', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Wajib pilih kategori sesuai Master Supplier Categories.'),
                                TextInput::make('npwp')
                                    ->label('NPWP')
                                    ->maxLength(30)
                                    ->mask('99.999.999.9-999.999')
                                    ->placeholder('00.000.000.0-000.000')
                                    ->helperText('Optional jika pemasok belum memiliki NPWP.'),
                                Toggle::make('is_active')
                                    ->label('Status Aktif')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->columns(2),
                        Section::make('Informasi Bank')
                            ->schema([
                                TextInput::make('bank_account_name')
                                    ->label('Atas Nama')
                                    ->maxLength(120),
                                TextInput::make('bank_name')
                                    ->label('Nama Bank')
                                    ->maxLength(80),
                                TextInput::make('bank_account_number')
                                    ->label('Nomor Rekening')
                                    ->maxLength(60),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpan([
                        'default' => 12,
                        'lg' => 6,
                    ]),
                Section::make('Kontak & Lokasi')
                    ->schema([
                        TextInput::make('owner_name')
                            ->label('Nama Pemilik')
                            ->maxLength(120),
                        TagsInput::make('contact_phone')
                            ->label('Nomor Kontak')
                            ->placeholder('0811-1111-111')
                            ->helperText('Tambahkan beberapa nomor, sistem menyimpan dengan pemisah ; (contoh: 0811;0812).')
                            ->afterStateHydrated(function (TagsInput $component, $state): void {
                                if (is_string($state)) {
                                    $component->state(array_values(array_filter(array_map('trim', explode(';', $state)))));
                                } elseif (is_array($state)) {
                                    $component->state(array_values(array_filter($state)));
                                }
                            })
                            ->dehydrateStateUsing(function (?array $state): ?string {
                                $phones = collect($state ?? [])
                                    ->map(fn ($phone) => preg_replace('/[^0-9+]/', '', (string) $phone))
                                    ->filter()
                                    ->unique()
                                    ->values();

                                return $phones->isEmpty() ? null : $phones->implode(';');
                            })
                            ->nullable(),
                        TextInput::make('contact_email')
                            ->label('Email Kontak')
                            ->email()
                            ->maxLength(120),
                        Select::make('default_warehouse_id')
                            ->label('Gudang Default')
                            ->relationship('defaultWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Digunakan ketika transaksi tidak memilih gudang secara eksplisit.'),
                        Textarea::make('address_line')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan Tambahan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan([
                        'default' => 12,
                        'lg' => 6,
                    ]),
            ]);
    }
}