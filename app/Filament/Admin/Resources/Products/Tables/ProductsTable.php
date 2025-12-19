<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->description(fn (Product $record): ?string => $record->type ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Product::typeOptions()[$state] ?? 'Tidak Ditandai'),
                TextColumn::make('unit.name')
                    ->label('Satuan')
                    ->badge()
                    ->placeholder('Belum diatur'),
                TextColumn::make('productCategory.name')
                    ->label('Kategori')
                    ->badge()
                    ->placeholder('Belum diatur'),
                TextColumn::make('defaultWarehouse.name')
                    ->label('Gudang Default')
                    ->placeholder('Belum diatur')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('product_category_id')
                    ->label('Kategori')
                    ->relationship('productCategory', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('unit_id')
                    ->label('Satuan')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label('Jenis Produk')
                    ->options(Product::typeOptions()),
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->placeholder('Semua'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
