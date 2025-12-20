<?php

namespace App\Filament\Admin\Resources\ChartOfAccountImports\Tables;

use App\Models\ChartOfAccountImport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ChartOfAccountImportsTable
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
                        'warning' => [ChartOfAccountImport::STATUS_PENDING],
                        'primary' => [ChartOfAccountImport::STATUS_PROCESSING],
                        'success' => [ChartOfAccountImport::STATUS_COMPLETED],
                        'danger' => [ChartOfAccountImport::STATUS_FAILED],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ChartOfAccountImport::STATUS_PENDING => 'Menunggu',
                        ChartOfAccountImport::STATUS_PROCESSING => 'Diproses',
                        ChartOfAccountImport::STATUS_COMPLETED => 'Selesai',
                        ChartOfAccountImport::STATUS_FAILED => 'Gagal',
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
                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('System')
                    ->toggleable(),
                IconColumn::make('deleted_at')
                    ->label('Terhapus?')
                    ->boolean()
                    ->trueIcon('heroicon-m-trash')
                    ->falseIcon('heroicon-m-check')
                    ->falseColor('success')
                    ->trueColor('danger')
                    ->visible(false),
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
                        ChartOfAccountImport::STATUS_PENDING => 'Menunggu',
                        ChartOfAccountImport::STATUS_PROCESSING => 'Diproses',
                        ChartOfAccountImport::STATUS_COMPLETED => 'Selesai',
                        ChartOfAccountImport::STATUS_FAILED => 'Gagal',
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
