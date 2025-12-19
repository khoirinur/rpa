<?php

namespace App\Filament\Admin\Resources\CustomerCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Kategori Customer')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Kategori')
                            ->required()
                            ->maxLength(20)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Contoh: C-LAMA, RETAIL, MBG.'),
                        TextInput::make('name')
                            ->label('Nama Kategori')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),
                        Select::make('default_warehouse_id')
                            ->label('Gudang Default')
                            ->relationship('defaultWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Opsional: gudang utama yang menangani customer kategori ini.')
                            ->nullable(),
                    ])
                    ->columns(2),
                Section::make('Status & Catatan')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                        Textarea::make('description')
                            ->label('Catatan')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
