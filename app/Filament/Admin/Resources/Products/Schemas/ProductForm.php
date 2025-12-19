<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Produk')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Produk')
                            ->required()
                            ->maxLength(20)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Kode mengikuti format P-XXXX dan harus unik.')
                            ->suffixAction(
                                Action::make('generate_code')
                                    ->label('Generate')
                                    ->icon('heroicon-m-sparkles')
                                    ->action(function (Set $set): void {
                                        $set('code', sprintf('P-%04d', random_int(1, 9999)));
                                    }),
                            ),
                        TextInput::make('name')
                            ->label('Nama Produk')
                            ->required()
                            ->maxLength(150),
                        Select::make('type')
                            ->label('Jenis Produk')
                            ->options(Product::typeOptions())
                            ->required()
                            ->default('persediaan')
                            ->native(false)
                            ->helperText('Gunakan jenis Persediaan/Jasa/Non-Persediaan sesuai kebutuhan akuntansi.'),
                        Select::make('unit_id')
                            ->label('Satuan')
                            ->relationship('unit', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Opsional — gunakan jika produk memiliki satuan baku dari Master Units.'),
                        Select::make('product_category_id')
                            ->label('Kategori Produk')
                            ->relationship('productCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->helperText('Data diambil dari Master Kategori Produk.'),
                    ])
                    ->columns(2),
                Section::make('Gudang & Status')
                    ->schema([
                        Select::make('default_warehouse_id')
                            ->label('Gudang Default')
                            ->relationship('defaultWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Digunakan saat transaksi tidak memilih gudang secara eksplisit.'),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
                Section::make('Catatan')
                    ->schema([
                        Textarea::make('description')
                            ->label('Catatan Tambahan')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
