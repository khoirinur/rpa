<?php

namespace App\Filament\Admin\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                $user = Auth::user();

                if (! $user || $user->hasRole('super_admin')) {
                    return;
                }

                $defaultWarehouseId = $user->default_warehouse_id ?? null;

                if ($defaultWarehouseId) {
                    $query->where(function (Builder $builder) use ($defaultWarehouseId): void {
                        $builder
                            ->whereNull('warehouse_id')
                            ->orWhere('warehouse_id', $defaultWarehouseId);
                    });
                }
            })
            ->defaultSort('performed_at', 'desc')
            ->columns([
                TextColumn::make('performed_at')
                    ->label('Tanggal & Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->grow(false),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Sistem'),
                TextColumn::make('module')
                    ->label('Modul')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('action_type')
                    ->label('Tipe Aktivitas')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted', 'force_deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ActivityLog::formatActionType($state)),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->placeholder('Semua Gudang')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('reverted_at')
                    ->label('Status Pembatalan')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-uturn-left')
                    ->falseIcon('heroicon-o-check-circle')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->relationship('user', 'name')
                    ->preload()
                    ->searchable()
                    ->native(false),
                SelectFilter::make('action_type')
                    ->label('Tipe Aktivitas')
                    ->options(fn () => ActivityLog::actionTypeOptions())
                    ->searchable()
                    ->native(false),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->preload()
                    ->native(false),
                Filter::make('performed_at')
                    ->label('Rentang Tanggal')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari'),
                        DatePicker::make('until')
                            ->label('Sampai'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, $date) => $builder->whereDate('performed_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, $date) => $builder->whereDate('performed_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Mulai: ' . $data['from'];
                        }

                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Selesai: ' . $data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make()
                    ->label('Kelola'),
            ])
            ->bulkActions([])
            ->headerActions([
                ExportAction::make('export')
                    ->label('Export Log')
                    ->color('gray')
                    ->exports([
                        ExcelExport::make('activity-logs-csv')
                            ->fromTable()
                            ->withWriterType(Excel::CSV)
                            ->withFilename(fn () => 'activity-logs-' . now()->format('Ymd_His')),
                    ]),
            ]);
    }
}
