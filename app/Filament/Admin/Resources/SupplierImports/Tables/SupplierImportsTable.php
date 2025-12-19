<?php

namespace App\Filament\Admin\Resources\SupplierImports\Tables;

use App\Models\SupplierImport;
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

class SupplierImportsTable
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
                        'warning' => [SupplierImport::STATUS_PENDING],
                        'primary' => [SupplierImport::STATUS_PROCESSING],
                        'success' => [SupplierImport::STATUS_COMPLETED],
                        'danger' => [SupplierImport::STATUS_FAILED],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SupplierImport::STATUS_PENDING => 'Menunggu',
                        SupplierImport::STATUS_PROCESSING => 'Diproses',
                        SupplierImport::STATUS_COMPLETED => 'Selesai',
                        SupplierImport::STATUS_FAILED => 'Gagal',
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
                TextColumn::make('fallbackCategory.name')
                    ->label('Kategori Bawaan')
                    ->placeholder('—')
                    ->toggleable(),
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
                        SupplierImport::STATUS_PENDING => 'Menunggu',
                        SupplierImport::STATUS_PROCESSING => 'Diproses',
                        SupplierImport::STATUS_COMPLETED => 'Selesai',
                        SupplierImport::STATUS_FAILED => 'Gagal',
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
