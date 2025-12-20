<?php

namespace App\Filament\Admin\Resources\AccountTypes\Schemas;

use App\Models\AccountType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AccountTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Tipe Akun')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Tipe')
                            ->maxLength(40)
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Gunakan format huruf besar, contoh: KAS-BANK.')
                            ->suffixAction(
                                Action::make('generate_code')
                                    ->label('Generate')
                                    ->icon('heroicon-m-sparkles')
                                    ->action(function (Set $set, $state): void {
                                        $prefix = substr((string) $state, 0, 3) ?: 'ACC';
                                        $set('code', strtoupper(sprintf('%s-%04d', $prefix, random_int(1, 9999))));
                                    }),
                            ),
                        TextInput::make('name')
                            ->label('Nama Tipe')
                            ->maxLength(150)
                            ->required(),
                        Select::make('category')
                            ->label('Kategori COA')
                            ->options(AccountType::categoryOptions())
                            ->required()
                            ->native(false),
                        Select::make('default_warehouse_id')
                            ->label('Gudang Default')
                            ->relationship('defaultWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Opsional, pilih gudang yang mewakili saldo utama untuk tipe akun ini.')
                            ->nullable(),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
