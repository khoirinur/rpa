<?php

namespace App\Filament\Admin\Resources\ProductImports\Tables;

use App\Models\ProductImport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')
                    ->label('Nama Berkas')
                    ->searchable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => [ProductImport::STATUS_PENDING],
                        'primary' => [ProductImport::STATUS_PROCESSING],
                        'success' => [ProductImport::STATUS_COMPLETED],
                        'danger' => [ProductImport::STATUS_FAILED],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ProductImport::STATUS_PENDING => 'Menunggu',
                        ProductImport::STATUS_PROCESSING => 'Diproses',
                        ProductImport::STATUS_COMPLETED => 'Selesai',
                        ProductImport::STATUS_FAILED => 'Gagal',
                        default => ucfirst($state),
                    }),
                TextColumn::make('total_rows')
                    ->label('Total')
                    ->sortable(),
                TextColumn::make('imported_rows')
                    ->label('Berhasil')
                    ->sortable(),
                TextColumn::make('failed_rows')
                    ->label('Gagal')
                    ->sortable(),
                TextColumn::make('defaultWarehouse.name')
                    ->label('Gudang Default')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('System')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        ProductImport::STATUS_PENDING => 'Menunggu',
                        ProductImport::STATUS_PROCESSING => 'Diproses',
                        ProductImport::STATUS_COMPLETED => 'Selesai',
                        ProductImport::STATUS_FAILED => 'Gagal',
                    ]),
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
