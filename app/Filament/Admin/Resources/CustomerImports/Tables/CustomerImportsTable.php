<?php

namespace App\Filament\Admin\Resources\CustomerImports\Tables;

use App\Models\CustomerImport;
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

class CustomerImportsTable
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
                        'warning' => [CustomerImport::STATUS_PENDING],
                        'primary' => [CustomerImport::STATUS_PROCESSING],
                        'success' => [CustomerImport::STATUS_COMPLETED],
                        'danger' => [CustomerImport::STATUS_FAILED],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        CustomerImport::STATUS_PENDING => 'Menunggu',
                        CustomerImport::STATUS_PROCESSING => 'Diproses',
                        CustomerImport::STATUS_COMPLETED => 'Selesai',
                        CustomerImport::STATUS_FAILED => 'Gagal',
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
                        CustomerImport::STATUS_PENDING => 'Menunggu',
                        CustomerImport::STATUS_PROCESSING => 'Diproses',
                        CustomerImport::STATUS_COMPLETED => 'Selesai',
                        CustomerImport::STATUS_FAILED => 'Gagal',
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
