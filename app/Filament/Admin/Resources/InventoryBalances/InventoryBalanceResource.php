<?php

namespace App\Filament\Admin\Resources\InventoryBalances;

use App\Filament\Admin\Resources\InventoryBalances\Pages\ListInventoryBalances;
use App\Filament\Admin\Resources\InventoryBalances\Pages\ViewInventoryBalance;
use App\Filament\Admin\Resources\InventoryBalances\Schemas\InventoryBalanceInfolist;
use App\Models\InventoryBalance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\S\chemas\Components\Section;
use Filament\S\chemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

class InventoryBalanceResource extends Resource
{
    protected static ?string $model = InventoryBalance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationLabel(): string
    {
        return 'Saldo Persediaan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Saldo Persediaan';
    }

    public static function getModelLabel(): string
    {
        return 'Saldo Persediaan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Saldo Persediaan')
                    ->schema([
                        Placeholder::make('product_display')
                            ->label('Produk')
                            ->content(fn (SchemaGet $get): string => self::formatProductDisplay($get)),
                        Placeholder::make('warehouse_display')
                            ->label('Gudang')
                            ->content(fn (SchemaGet $get): string => self::formatWarehouseDisplay($get)),
                        Placeholder::make('unit_display')
                            ->label('Satuan')
                            ->content(fn (SchemaGet $get): string => self::formatUnitDisplay($get)),
                    ])
                    ->columns(3),
                Section::make('Ringkasan Kuantitas')
                    ->schema([
                        Placeholder::make('on_hand_quantity')
                            ->label('On Hand')
                            ->content(fn (SchemaGet $get): string => self::formatQuantity($get('on_hand_quantity'))),
                        Placeholder::make('available_quantity')
                            ->label('Tersedia')
                            ->content(fn (SchemaGet $get): string => self::formatQuantity($get('available_quantity'))),
                        Placeholder::make('incoming_quantity')
                            ->label('Sedang Masuk')
                            ->content(fn (SchemaGet $get): string => self::formatQuantity($get('incoming_quantity'))),
                        Placeholder::make('reserved_quantity')
                            ->label('Reservasi')
                            ->content(fn (SchemaGet $get): string => self::formatQuantity($get('reserved_quantity'))),
                        Placeholder::make('average_cost')
                            ->label('Biaya Rata-rata')
                            ->content(fn (SchemaGet $get): string => self::formatCurrency($get('average_cost'))),
                        Placeholder::make('last_transaction_at')
                            ->label('Transaksi Terakhir')
                            ->content(fn (SchemaGet $get): string => self::formatDatetime($get('last_transaction_at'))),
                    ])
                    ->columns(3),
                Section::make('Catatan')
                    ->schema([
                        Placeholder::make('last_source')
                            ->label('Sumber Perubahan')
                            ->content(fn (SchemaGet $get): string => self::formatSourceFromSchema($get)),
                    ])
                    ->columns(1),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryBalanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.code')
                    ->label('Kode Produk')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('product.name')
                    ->label('Nama Produk')
                    ->description(fn (InventoryBalance $record): ?string => $record->product?->type)
                    ->sortable()
                    ->searchable()
                    ->wrap(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('unit.name')
                    ->label('Satuan')
                    ->badge()
                    ->placeholder('Unit'),
                TextColumn::make('on_hand_quantity')
                    ->label('On Hand')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::formatQuantity($state)),
                TextColumn::make('available_quantity')
                    ->label('Tersedia')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => self::formatQuantity($state)),
                TextColumn::make('reserved_quantity')
                    ->label('Reservasi')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => self::formatQuantity($state)),
                TextColumn::make('incoming_quantity')
                    ->label('Sedang Masuk')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => self::formatQuantity($state)),
                TextColumn::make('average_cost')
                    ->label('Biaya Rata-rata')
                    ->money('IDR', true)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('unit_id')
                    ->label('Satuan')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Action::make('view')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (InventoryBalance $record): string => static::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryBalances::route('/'),
            'view' => ViewInventoryBalance::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product', 'warehouse', 'unit']);
    }

    public static function formatQuantity($value): string
    {
        $number = (float) ($value ?? 0);
        $formatted = number_format($number, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',') ?: '0';
    }

    public static function formatCurrency($value): string
    {
        $number = (float) ($value ?? 0);

        return 'Rp ' . number_format($number, 2, ',', '.');
    }

    public static function formatDatetime($value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return optional(Carbon::make($value))
                ?->timezone(config('app.timezone'))
                ?->format('d M Y H:i')
                ?? '-';
        } catch (Throwable) {
            return (string) $value;
        }
    }

    public static function formatSource(?InventoryBalance $record): string
    {
        if (! $record) {
            return '-';
        }

        $type = $record->last_source_type;
        $id = $record->last_source_id;

        if (! $type && ! $id) {
            return 'Tidak ada catatan.';
        }

        return trim(sprintf('%s #%s', class_basename((string) $type), $id ?: '-'));
    }

    protected static function formatProductDisplay(SchemaGet $get): string
    {
        $code = trim((string) ($get('product.code') ?? ''));
        $name = trim((string) ($get('product.name') ?? $get('product_name') ?? ''));

        if ($code && $name) {
            return sprintf('%s — %s', $code, $name);
        }

        return $name ?: ($code ?: '-');
    }

    protected static function formatWarehouseDisplay(SchemaGet $get): string
    {
        $name = $get('warehouse.name') ?? $get('warehouse_name');
        $code = $get('warehouse.code') ?? $get('warehouse_code');

        if ($name && $code) {
            return sprintf('%s (%s)', $name, $code);
        }

        return $name ?: ($code ?: '-');
    }

    protected static function formatUnitDisplay(SchemaGet $get): string
    {
        return $get('unit.name') ?? $get('unit_id') ?? 'Unit';
    }

    protected static function formatSourceFromSchema(SchemaGet $get): string
    {
        $type = $get('last_source_type');
        $id = $get('last_source_id');

        if (! $type && ! $id) {
            return 'Tidak ada catatan.';
        }

        return trim(sprintf('%s #%s', class_basename((string) $type), $id ?: '-'));
    }
}
