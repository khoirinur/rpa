<?php

namespace App\Filament\Admin\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Customer')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Customer')
                            ->required()
                            ->maxLength(20)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Gunakan format C-XXXX agar konsisten.')
                            ->suffixAction(
                                Action::make('generate_code')
                                    ->label('Generate')
                                    ->icon('heroicon-m-sparkles')
                                    ->action(function (Set $set): void {
                                        $set('code', sprintf('C-%04d', random_int(1, 9999)));
                                    }),
                            ),
                        TextInput::make('name')
                            ->label('Nama Customer')
                            ->required()
                            ->maxLength(150),
                        Select::make('customer_category_id')
                            ->label('Kategori Customer')
                            ->relationship('customerCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->helperText('Kategori berasal dari Master Customer Categories.'),
                    ])
                    ->columns(2),
                Section::make('Kontak & Gudang')
                    ->schema([
                        TagsInput::make('contact_phone')
                            ->label('Nomor Telepon')
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
                            ->nullable()
                            ->columnSpan(2),
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
                            ->helperText('Gunakan saat transaksi tidak memilih gudang secara eksplisit.'),
                        Textarea::make('address_line')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label('Kota')
                            ->maxLength(120),
                        TextInput::make('province')
                            ->label('Provinsi')
                            ->maxLength(120),
                    ])
                    ->columns(2),
                Section::make('Catatan & Status')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}
