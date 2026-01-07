<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Schemas;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Components\Utilities\Set as SchemaSet;
use Filament\Schemas\Schema;
use Closure;
use Filament\Support\RawJs;
use Illuminate\Support\Str;
use Throwable;

class InventoryAdjustmentForm
{
    private const ALLOWED_ACCOUNT_CODES = ['300001', '8212', '510103'];
    protected static array $productOptionCache = [];
    protected static array $stockSnapshotCache = [];
    protected static array $warehouseNameCache = [];
    protected static array $unitNameCache = [];

    public static function configure(Schema $schema): Schema
    {
        $headerSection = Section::make('Header Penyesuaian')
            ->schema([
                TextInput::make('adjustment_number')
                    ->label('No. Penyesuaian')
                    ->readOnly()
                    ->placeholder('Otomatis saat simpan'),
                DatePicker::make('adjustment_date')
                    ->label('Tanggal Penyesuaian')
                    ->default(today())
                    ->maxDate(today())
                    ->required()
                    ->native(false)
                    ->rule('before_or_equal:today')
                    ->live(),
                Select::make('default_warehouse_id')
                    ->label('Gudang Default')
                    ->placeholder('Pilih gudang')
                    ->options(fn () => Warehouse::query()
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->live()
                    ->default(fn () => self::defaultWarehouseId()),
            ])
            ->columns(3)
            ->columnSpanFull();

        $infoSection = Section::make('Info Lainnya')
            ->schema([
                Select::make('adjustment_account_id')
                    ->label('Akun Penyesuaian')
                    ->options(fn () => self::permittedAccountOptions())
                    ->required()
                    ->default(fn () => self::defaultAdjustmentAccountId())
                    ->native(false)
                    ->helperText('Hanya akun 300001, 8212, dan 510103 yang bisa dipakai.'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(4)
                    ->placeholder('Keterangan tambahan jika diperlukan'),
            ])
            ->columns(2)
            ->columnSpanFull();

        $itemsSection = Section::make('Rincian Barang')
            ->schema([
                Hidden::make('pending_line_item_payload')
                    ->dehydrated(false),
                Select::make('product_lookup')
                    ->label('Cari & Tambah Barang')
                    ->placeholder('Ketik nama atau kode produk')
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->live()
                    ->preload()
                    ->dehydrated(false)
                    ->options(fn () => self::productLookupOptions())
                    ->getSearchResultsUsing(fn (string $search): array => self::productLookupOptions($search))
                    ->disabled(fn (SchemaGet $get): bool => ! self::isHeaderComplete($get))
                    ->helperText('Pilih produk untuk membuka modal detail penyesuaian.')
                    ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get, Select $component): void {
                        if (blank($state)) {
                            return;
                        }

                        $payload = self::buildPendingLineItemPayload((int) $state, $get('default_warehouse_id'));

                        $set('product_lookup', null);

                        $itemsComponent = self::resolveItemsComponentFrom($component);

                        if ($itemsComponent && self::triggerPendingLineItemModal($payload, $itemsComponent)) {
                            return;
                        }

                        $set('pending_line_item_payload', $payload);
                    }),
                Placeholder::make('product_lookup_notice')
                    ->hiddenLabel()
                    ->content('Lengkapi tanggal penyesuaian dan gudang default sebelum menambahkan barang.')
                    ->visible(fn (SchemaGet $get): bool => ! self::isHeaderComplete($get))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'text-sm font-medium text-danger-600']),
                Repeater::make('items')
                    ->label('Detail Penyesuaian')
                    ->relationship('items')
                    ->schema(self::lineItemTableSchema())
                    ->table(self::lineItemTableColumns())
                    ->columns(12)
                    ->columnSpanFull()
                    ->default([])
                    ->reorderable()
                    ->cloneable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->disabled(fn (SchemaGet $get): bool => ! self::isHeaderComplete($get))
                    ->extraItemActions([
                        self::makeEditLineItemAction(),
                    ])
                    ->extraAttributes(['data-row-click-action' => 'edit_line_item'])
                    ->itemLabel(fn (array $state): string => $state['item_name'] ?? 'Baris Penyesuaian')
                    ->afterStateUpdated(function (?array $state, SchemaSet $set): void {
                        self::syncSummary($set, $state ?? []);
                    })
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                        self::syncSummary($set, $state ?? []);
                        self::processPendingLineItemPayload($set, $get, $component);
                    })
                    ->helperText('Klik baris untuk mengubah rincian melalui modal.'),
            ])
            ->columnSpanFull();

        $summarySection = Section::make('Ringkasan Penyesuaian')
            ->schema([
                Hidden::make('total_addition_value')
                    ->default(0)
                    ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state)),
                Hidden::make('total_reduction_value')
                    ->default(0)
                    ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state)),
                Hidden::make('total_set_value')
                    ->default(0)
                    ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state)),
                TextInput::make('total_addition_value_display')
                    ->label('Total Harga Penyesuaian')
                    ->readOnly()
                    ->prefix('Rp')
                    ->default('0')
                    ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($state, SchemaSet $set, SchemaGet $get): void {
                        $set('total_addition_value_display', self::formatCurrencyValue(
                            self::sanitizeDecimal($get('total_addition_value') ?? 0)
                        ));
                    }),
                TextInput::make('total_reduction_value_display')
                    ->label('Total Qty Pengurangan')
                    ->readOnly()
                    ->default('0')
                    ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 3)
JS))
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($state, SchemaSet $set, SchemaGet $get): void {
                        $set('total_reduction_value_display', self::formatQuantityValue(
                            self::sanitizeDecimal($get('total_reduction_value') ?? 0)
                        ));
                    }),
                TextInput::make('total_set_value_display')
                    ->label('Total Qty Atur Stok')
                    ->readOnly()
                    ->default('0')
                    ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 3)
JS))
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($state, SchemaSet $set, SchemaGet $get): void {
                        $set('total_set_value_display', self::formatQuantityValue(
                            self::sanitizeDecimal($get('total_set_value') ?? 0)
                        ));
                    }),
            ])
            ->columns(3)
            ->columnSpanFull();

        return $schema
            ->components([
                $headerSection,
                Tabs::make('inventory_adjustment_tabs')
                    ->tabs([
                        Tab::make('Rincian Barang')
                            ->schema([
                                $itemsSection,
                            ])
                            ->disabled(fn (SchemaGet $get): bool => ! self::isHeaderComplete($get)),
                        Tab::make('Info Tambahan')->schema([
                            $infoSection,
                        ]),
                    ])
                    ->columnSpanFull(),
                $summarySection,
            ]);
    }

    protected static function defaultLineItemState(?int $warehouseId = null): array
    {
        return self::withDisplayValues([
            'id' => null,
            'product_id' => null,
            'item_code' => null,
            'item_name' => null,
            'unit_id' => null,
            'warehouse_id' => $warehouseId,
            'adjustment_type' => InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION,
            'quantity' => 0,
            'target_quantity' => null,
            'current_stock_snapshot' => 0,
            'unit_cost' => 0,
            'total_cost' => 0,
            'notes' => null,
            '__draft' => true,
        ]);
    }

    protected static function buildLineItemState(int $productId, ?int $defaultWarehouseId): array
    {
        $state = self::defaultLineItemState($defaultWarehouseId);

        $product = Product::query()
            ->select(['id', 'code', 'name', 'unit_id', 'default_warehouse_id'])
            ->find($productId);

        if ($product) {
            $state['product_id'] = $product->id;
            $state['item_code'] = $product->code;
            $state['item_name'] = $product->name;
            $state['unit_id'] = $product->unit_id;
        }

        $warehouseId = $state['warehouse_id'] ?? $product?->default_warehouse_id;
        $state['warehouse_id'] = $warehouseId;
        $state['current_stock_snapshot'] = self::resolveCurrentStock($product?->id, $warehouseId);
        $state['__draft'] = false;

        return self::withDisplayValues($state);
    }

    protected static function lineItemTableSchema(): array
    {
        return [
            Hidden::make('id'),
            Hidden::make('product_id'),
            Hidden::make('item_code'),
            Hidden::make('item_name'),
            Hidden::make('unit_id'),
            Hidden::make('warehouse_id'),
            Hidden::make('adjustment_type'),
            Hidden::make('quantity'),
            Hidden::make('target_quantity'),
            Hidden::make('current_stock_snapshot'),
            Hidden::make('unit_cost'),
            Hidden::make('total_cost'),
            Hidden::make('notes'),
            Hidden::make('__draft'),
            Placeholder::make('table_summary')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatLineItemSummary($get))
                ->columnSpan(5)
                ->extraAttributes([
                    'class' => 'whitespace-pre-line leading-tight text-sm text-primary-700 font-semibold',
                ]),
            Placeholder::make('table_snapshot')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => sprintf(
                    'Stok: %s',
                    self::formatQuantityValue(self::sanitizeDecimal($get('current_stock_snapshot') ?? 0))
                ))
                ->columnSpan(2)
                ->extraAttributes(['class' => 'text-sm text-gray-600']),
            Placeholder::make('table_quantity')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): string {
                    $type = $get('adjustment_type');
                    $quantity = self::formatQuantityValue(self::sanitizeDecimal($get('quantity') ?? 0));
                    $unit = self::resolveUnitName($get('unit_id')) ?? 'Unit';

                    if ($type === InventoryAdjustment::ADJUSTMENT_TYPE_SET) {
                        $target = self::formatQuantityValue(self::sanitizeDecimal($get('target_quantity') ?? 0));

                        return sprintf('Set: %s %s', $target, $unit);
                    }

                    $prefix = $type === InventoryAdjustment::ADJUSTMENT_TYPE_REDUCTION ? 'Kurangi' : 'Tambah';

                    return sprintf('%s: %s %s', $prefix, $quantity, $unit);
                })
                ->columnSpan(3)
                ->extraAttributes(['class' => 'text-sm text-gray-700 tabular-nums']),
            Placeholder::make('table_cost')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrency(self::sanitizeDecimal($get('total_cost') ?? 0)))
                ->visible(fn (SchemaGet $get): bool => $get('adjustment_type') === InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION)
                ->columnSpan(2)
                ->extraAttributes(['class' => 'text-right font-semibold tabular-nums text-sm text-gray-900']),
        ];
    }

    protected static function lineItemTableColumns(): array
    {
        return [
            TableColumn::make('Barang')->width('28rem'),
            TableColumn::make('Stok')->width('8rem'),
            TableColumn::make('Qty / Target')->width('12rem'),
            TableColumn::make('Biaya')->width('10rem'),
        ];
    }

    protected static function lineItemFields(): array
    {
        $fields = [
            Hidden::make('id'),
            Hidden::make('product_id')
                ->default(null)
                ->dehydrateStateUsing(fn ($state) => $state ? (int) $state : null),
            Hidden::make('__draft'),
            Hidden::make('quantity')
                ->default(0)
                ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state)),
            Hidden::make('unit_cost')
                ->default(0)
                ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state)),
            Hidden::make('total_cost')
                ->default(0)
                ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state)),
            TextInput::make('item_name')
                ->label('Nama Item')
                ->inlineLabel()
                ->maxLength(180)
                ->required()
                ->columnSpan(12),
            TextInput::make('item_code')
                ->label('Kode Item')
                ->inlineLabel()
                ->maxLength(30)
                ->columnSpan(12),
            ToggleButtons::make('adjustment_type')
                ->label('Tipe Penyesuaian')
                ->inlineLabel()
                ->options(InventoryAdjustment::adjustmentTypeOptions())
                ->required()
                ->default(InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION)
                ->inline()
                ->live()
                ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                    if ($state === InventoryAdjustment::ADJUSTMENT_TYPE_SET) {
                        $active = self::sanitizeDecimal($get('target_quantity') ?? $get('quantity'));
                        $set('target_quantity', $active);
                        $set('quantity', 0);
                    } else {
                        $active = self::sanitizeDecimal($get('quantity') ?? $get('target_quantity'));
                        $set('quantity', $active);
                        $set('target_quantity', null);
                    }

                    $set('adjustment_quantity', self::formatInputDecimal($active ?? 0, 3));
                    self::syncLineItemCost($set, $get);
                })
                ->columnSpan(12),
            TextInput::make('adjustment_quantity')
                ->label('Kuantitas')
                ->inlineLabel()
                ->required()
                ->type('text')
                ->default(0)
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS
                ))
                ->stripCharacters(['.', ','])
                ->live(onBlur: true)
                ->afterStateHydrated(function ($state, SchemaSet $set, SchemaGet $get): void {
                    $set('adjustment_quantity', self::formatDecimal(
                        self::resolveActiveQuantityValue($get),
                        0
                    ));
                })
                ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                    $quantity = self::sanitizeMoneyValue($state);
                    $type = $get('adjustment_type');

                    if ($type === InventoryAdjustment::ADJUSTMENT_TYPE_SET) {
                        $set('target_quantity', $quantity);
                        $set('quantity', 0);
                    } else {
                        $set('quantity', $quantity);
                        $set('target_quantity', null);
                        self::syncLineItemCost($set, $get, quantityOverride: $quantity);
                    }
                })
                ->columnSpan(12),
            Hidden::make('target_quantity')
                ->default(null)
                ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state)),
            Select::make('unit_id')
                ->label('Satuan')
                ->inlineLabel()
                ->options(fn () => Unit::query()->orderBy('name')->pluck('name', 'id'))
                ->native(false)
                ->searchable()
                ->required()
                ->columnSpan(12),
            Select::make('warehouse_id')
                ->label('Gudang Tujuan')
                ->inlineLabel()
                ->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                ->native(false)
                ->searchable()
                ->required()
                ->columnSpan(12)
                ->live()
                ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                    $snapshot = self::resolveCurrentStock($get('product_id'), $state);
                    $set('current_stock_snapshot', $snapshot);
                }),
            TextInput::make('current_stock_snapshot')
                ->label('Stok Saat Ini (Snapshot)')
                ->inlineLabel()
                ->readOnly()
                ->default(0)
                ->formatStateUsing(fn ($state): string => self::formatQuantityValue((float) ($state ?? 0)))
                ->dehydrateStateUsing(fn ($state) => self::sanitizeDecimal($state))
                ->columnSpan(12),
            TextInput::make('unit_price')
                ->label('@Harga')
                ->inlineLabel()
                ->type('text')
                ->required()
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                    if (self::sanitizeMoneyValue($value) < 0) {
                        $fail('Harga tidak boleh negatif.');
                    }
                })
                ->prefix('Rp')
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                ->stripCharacters(['.', ','])
                ->live(onBlur: true)
                ->dehydrated(false)
                ->hidden(fn (SchemaGet $get): bool => $get('adjustment_type') !== InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION)
                ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                    $unitCost = self::sanitizeMoneyValue($state);
                    $set('unit_cost', $unitCost);
                    self::syncLineItemCost($set, $get, unitCostOverride: $unitCost);
                })
                ->columnSpan(12),
            Placeholder::make('total_cost_display')
                ->label('Total Biaya')
                ->reactive()
                ->content(fn (SchemaGet $get): string => self::formatCurrency(self::resolveLiveTotalCost($get)))
                ->hidden(fn (SchemaGet $get): bool => $get('adjustment_type') !== InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION)
                ->extraAttributes(['class' => 'text-right font-semibold tabular-nums text-base text-primary-700'])
                ->columnSpan(12),
            Textarea::make('notes')
                ->label('Catatan Baris')
                ->rows(2)
                ->columnSpan(12),
        ];

        return $fields;
    }

    protected static function makeEditLineItemAction(): Action
    {
        return Action::make('edit_line_item')
            ->label('Ubah Barang')
            ->modalHeading('Detail Penyesuaian')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('xl')
            ->schema(self::lineItemFields())
            ->extraAttributes(['data-row-trigger-only' => true])
            ->mountUsing(function (Schema $schema, array $arguments, Repeater $component): void {
                if (! empty($arguments['pending']) && is_array($arguments['payload'] ?? null)) {
                    $schema->fill($arguments['payload']);

                    return;
                }

                $itemKey = $arguments['item'] ?? null;
                $state = self::getLineItemStateByKey($component, $itemKey) ?? self::defaultLineItemState();

                $schema->fill($state);
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $itemKey = $arguments['item'] ?? null;
                $isPending = (bool) ($arguments['pending'] ?? false);
                $isDelete = (bool) ($arguments['delete_line_item'] ?? false);

                $data = self::normalizeLineItemPayloadData($data);

                if ($isDelete) {
                    self::removeLineItemState($component, $itemKey);

                    return;
                }

                $payload = self::prepareLineItemPayload($data);
                $payload['__draft'] = false;

                if ($isPending) {
                    self::upsertLineItemState($component, $payload);

                    return;
                }

                self::upsertLineItemState($component, $payload, $itemKey);
            })
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('delete_line_item', arguments: ['delete_line_item' => true])
                    ->label('Hapus')
                    ->color('danger')
                    ->requiresConfirmation(),
            ]);
    }

    protected static function normalizeLineItemPayloadData(array $data): array
    {
        $inputQuantity = $data['adjustment_quantity'] ?? null;
        $quantity = self::sanitizeMoneyValue($inputQuantity);
        $type = $data['adjustment_type'] ?? InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION;

        if ($type === InventoryAdjustment::ADJUSTMENT_TYPE_SET) {
            $data['target_quantity'] = $quantity;
            $data['quantity'] = 0;
        } else {
            $data['quantity'] = $quantity;
            $data['target_quantity'] = null;
        }

        $data['adjustment_quantity'] = self::formatDecimal($quantity, 0);

        $unitCostInput = $data['unit_cost'] ?? $data['unit_price'] ?? 0;
        $unitCost = self::sanitizeMoneyValue($unitCostInput);
        $data['unit_cost'] = $unitCost;
        $data['unit_price'] = self::formatCurrencyValue($unitCost);

        $costQuantity = $type === InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION
            ? $data['quantity']
            : 0;

        $totalCost = $type === InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION
            ? round($unitCost * $costQuantity, 2)
            : 0;

        $data['total_cost'] = $totalCost;

        return $data;
    }

    protected static function prepareLineItemPayload(array $data): array
    {
        $quantity = self::sanitizeDecimal($data['quantity'] ?? 0);
        $unitCost = self::sanitizeDecimal($data['unit_cost'] ?? 0);
        $adjustmentType = $data['adjustment_type'] ?? InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION;

        $payload = [
            'id' => $data['id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'item_code' => $data['item_code'] ?? null,
            'item_name' => $data['item_name'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'adjustment_type' => $adjustmentType,
            'quantity' => $quantity,
            'target_quantity' => $adjustmentType === InventoryAdjustment::ADJUSTMENT_TYPE_SET
                ? self::sanitizeDecimal($data['target_quantity'] ?? 0)
                : null,
            'current_stock_snapshot' => self::sanitizeDecimal($data['current_stock_snapshot'] ?? 0),
            'unit_cost' => $adjustmentType === InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION ? $unitCost : 0,
            'total_cost' => $adjustmentType === InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION
                ? round($quantity * $unitCost, 2)
                : 0,
            'notes' => $data['notes'] ?? null,
            '__draft' => (bool) ($data['__draft'] ?? false),
        ];

        return self::withDisplayValues($payload);
    }

    protected static function upsertLineItemState(Repeater $component, array $payload, ?string $itemKey = null): void
    {
        $items = $component->getRawState() ?? [];
        $key = $itemKey ?: (string) Str::uuid();

        $items[$key] = $payload;

        $component->rawState($items);
        $component->callAfterStateUpdated();
        $component->partiallyRender();

        if ($itemKey) {
            $livewire = $component->getLivewire();

            if ($livewire && method_exists($livewire, 'dispatch')) {
                $livewire->dispatch('filament::line-item-modal-closed');
            }
        }
    }

    protected static function removeLineItemState(Repeater $component, ?string $itemKey): void
    {
        if (! $itemKey) {
            return;
        }

        $items = $component->getRawState() ?? [];

        if (! array_key_exists($itemKey, $items)) {
            return;
        }

        unset($items[$itemKey]);

        $component->rawState($items);
        $component->callAfterStateUpdated();
        $component->partiallyRender();
    }

    protected static function getLineItemStateByKey(Repeater $component, ?string $itemKey): ?array
    {
        if (! $itemKey) {
            return null;
        }

        $items = $component->getRawState() ?? [];

        return $items[$itemKey] ?? null;
    }

    protected static function processPendingLineItemPayload(SchemaSet $set, SchemaGet $get, Repeater $component): void
    {
        $payload = $get('pending_line_item_payload');

        if (! is_array($payload)) {
            return;
        }

        $set('pending_line_item_payload', null);

        if (! self::triggerPendingLineItemModal($payload, $component)) {
            $set('pending_line_item_payload', $payload);
        }
    }

    protected static function buildPendingLineItemPayload(int $productId, ?int $defaultWarehouseId): array
    {
        $state = self::buildLineItemState($productId, $defaultWarehouseId);
        $state['__draft'] = true;

        return $state;
    }

    protected static function resolveItemsComponentFrom(Component $context): ?Repeater
    {
        $livewire = $context->getLivewire();

        if (! $livewire || ! method_exists($livewire, 'getSchemaComponent')) {
            return null;
        }

        $key = $context->resolveRelativeKey('items');

        if (blank($key)) {
            return null;
        }

        return $livewire->getSchemaComponent($key, withHidden: true);
    }

    protected static function triggerPendingLineItemModal(array $payload, Repeater $component): bool
    {
        $schemaComponentKey = $component->getKey();
        $livewire = $component->getLivewire();

        if (blank($schemaComponentKey) || ! $livewire) {
            return false;
        }

        $arguments = [
            'pending' => true,
            'payload' => $payload,
        ];

        if (method_exists($livewire, 'mountAction')) {
            try {
                $livewire->mountAction('edit_line_item', $arguments, [
                    'schemaComponent' => $schemaComponentKey,
                ]);

                return true;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (method_exists($livewire, 'dispatch') && method_exists($livewire, 'getId')) {
            $livewire->dispatch('filament::line-item-modal-requested', [
                'livewireId' => $livewire->getId(),
                'action' => 'edit_line_item',
                'arguments' => $arguments,
                'context' => ['schemaComponent' => $schemaComponentKey],
            ]);

            return true;
        }

        return false;
    }

    protected static function syncLineItemCost(
        SchemaSet $set,
        SchemaGet $get,
        ?float $quantityOverride = null,
        ?float $unitCostOverride = null
    ): void {
        if ($get('adjustment_type') !== InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION) {
            $set('total_cost', 0);
            return;
        }

        $quantity = $quantityOverride ?? self::sanitizeDecimal($get('quantity'));
        $unitCost = $unitCostOverride ?? self::sanitizeDecimal($get('unit_cost'));
        $total = round($quantity * $unitCost, 2);

        $set('total_cost', $total);
    }

    protected static function resolveLiveTotalCost(SchemaGet $get): float
    {
        if ($get('adjustment_type') !== InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION) {
            return 0;
        }

        $quantity = self::sanitizeDecimal($get('quantity') ?? 0);
        $unitCost = self::sanitizeDecimal($get('unit_cost') ?? 0);

        return round($quantity * $unitCost, 2);
    }

    protected static function syncSummary(SchemaSet $set, array $lineItems): void
    {
        $additionValue = 0;
        $totalReductionQty = 0;
        $totalSetQty = 0;

        foreach ($lineItems as $item) {
            $type = $item['adjustment_type'] ?? null;
            $quantity = self::sanitizeDecimal($item['quantity'] ?? 0);

            if ($type === InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION) {
                $additionValue += self::sanitizeDecimal($item['total_cost'] ?? 0);
            }

            if ($type === InventoryAdjustment::ADJUSTMENT_TYPE_REDUCTION) {
                $totalReductionQty += $quantity;
            }

            if ($type === InventoryAdjustment::ADJUSTMENT_TYPE_SET) {
                $target = self::sanitizeDecimal($item['target_quantity'] ?? 0);
                $totalSetQty += $target;
            }
        }

        $set('total_addition_value', round($additionValue, 2));
        $set('total_addition_value_display', self::formatCurrencyValue($additionValue));
        $set('total_reduction_value', round($totalReductionQty, 3));
        $set('total_reduction_value_display', self::formatQuantityValue($totalReductionQty));
        $set('total_set_value', round($totalSetQty, 3));
        $set('total_set_value_display', self::formatQuantityValue($totalSetQty));
    }

    protected static function productOptions(?string $search = null): array
    {
        $cacheKey = $search ?? '__default';

        if (array_key_exists($cacheKey, self::$productOptionCache)) {
            return self::$productOptionCache[$cacheKey];
        }

        $query = Product::query()
            ->select(['id', 'code', 'name'])
            ->orderBy('name')
            ->limit(25);

        if ($search) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $options = $query
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => sprintf('%s — %s', $product->code, $product->name),
            ])
            ->toArray();

        self::$productOptionCache[$cacheKey] = $options;

        return $options;
    }

    protected static function productLookupOptions(?string $search = null): array
    {
        return self::productOptions($search);
    }

    protected static function permittedAccountOptions(): array
    {
        return ChartOfAccount::query()
            ->select(['id', 'code', 'name'])
            ->whereIn('code', self::ALLOWED_ACCOUNT_CODES)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (ChartOfAccount $account): array => [
                $account->id => sprintf('%s — %s', $account->code, $account->name),
            ])
            ->toArray();
    }

    protected static function formatLineItemSummary(SchemaGet $get): string
    {
        $parts = [];
        $label = $get('item_name') ?: 'Barang belum dipilih';
        $parts[] = $label;

        if ($code = $get('item_code')) {
            $parts[] = sprintf('[%s]', $code);
        }

        if ($warehouse = self::resolveWarehouseName($get('warehouse_id'))) {
            $parts[] = sprintf('Gudang: %s', $warehouse);
        }

        if ($notes = $get('notes')) {
            $parts[] = $notes;
        }

        return implode(PHP_EOL, array_filter($parts));
    }

    protected static function resolveWarehouseName(?int $warehouseId): ?string
    {
        if (! $warehouseId) {
            return null;
        }

        if (! array_key_exists($warehouseId, self::$warehouseNameCache)) {
            self::$warehouseNameCache[$warehouseId] = Warehouse::query()
                ->whereKey($warehouseId)
                ->value('name');
        }

        return self::$warehouseNameCache[$warehouseId];
    }

    protected static function resolveUnitName(?int $unitId): ?string
    {
        if (! $unitId) {
            return null;
        }

        if (! array_key_exists($unitId, self::$unitNameCache)) {
            self::$unitNameCache[$unitId] = Unit::query()
                ->whereKey($unitId)
                ->value('name');
        }

        return self::$unitNameCache[$unitId];
    }

    protected static function formatCurrency(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    protected static function formatCurrencyValue(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }
    
    protected static function formatQuantityValue(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',') ?: '0';
    }

    protected static function defaultAdjustmentAccountId(): ?int
    {
        return ChartOfAccount::query()
            ->where('code', '300001')
            ->value('id');
    }

    protected static function defaultWarehouseId(): ?int
    {
        return Warehouse::query()
            ->where(function ($query): void {
                $query->where('slug', 'pagu')
                    ->orWhere('is_default', true);
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->value('id');
    }

    protected static function resolveCurrentStock(?int $productId, ?int $warehouseId): float
    {
        if (! $productId) {
            return 0.0;
        }

        $cacheKey = sprintf('%s:%s', $productId, $warehouseId ?: 'all');

        if (array_key_exists($cacheKey, self::$stockSnapshotCache)) {
            return self::$stockSnapshotCache[$cacheKey];
        }

        $receivedQty = GoodsReceiptItem::query()
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->sum('received_quantity');

        $adjustmentBaseQuery = InventoryAdjustmentItem::query()
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId));

        $additionQty = (clone $adjustmentBaseQuery)
            ->where('adjustment_type', InventoryAdjustment::ADJUSTMENT_TYPE_ADDITION)
            ->sum('quantity');

        $reductionQty = (clone $adjustmentBaseQuery)
            ->where('adjustment_type', InventoryAdjustment::ADJUSTMENT_TYPE_REDUCTION)
            ->sum('quantity');

        $latestSetQty = (clone $adjustmentBaseQuery)
            ->where('adjustment_type', InventoryAdjustment::ADJUSTMENT_TYPE_SET)
            ->orderByDesc('created_at')
            ->value('target_quantity');

        $current = $receivedQty + $additionQty - $reductionQty;

        if ($latestSetQty !== null) {
            $current = (float) $latestSetQty;
        }

        self::$stockSnapshotCache[$cacheKey] = round((float) $current, 3);

        return self::$stockSnapshotCache[$cacheKey];
    }

    protected static function isHeaderComplete(SchemaGet $get): bool
    {
        return filled($get('adjustment_date'))
            && filled($get('default_warehouse_id'));
    }

    protected static function sanitizeMoneyValue(mixed $value): float
    {
        if (blank($value)) {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $numericString = preg_replace('/[^0-9,.-]/', '', (string) $value) ?? '';

        if ($numericString === '' || $numericString === '-') {
            return 0.0;
        }

        $sign = str_starts_with($numericString, '-') ? -1 : 1;
        $numericString = ltrim($numericString, '-');

        $lastDot = strrpos($numericString, '.');
        $lastComma = strrpos($numericString, ',');
        $decimalSeparator = null;

        if ($lastDot !== false && $lastComma !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $fractionLength = strlen($numericString) - $lastComma - 1;
            if ($fractionLength > 0 && $fractionLength <= 2) {
                $decimalSeparator = ',';
            }
        } elseif ($lastDot !== false) {
            $fractionLength = strlen($numericString) - $lastDot - 1;
            if ($fractionLength > 0 && $fractionLength <= 2) {
                $decimalSeparator = '.';
            }
        }

        if ($decimalSeparator !== null) {
            $decimalPosition = $decimalSeparator === '.' ? $lastDot : $lastComma;
            $integerPart = substr($numericString, 0, $decimalPosition);
            $fractionalPart = substr($numericString, $decimalPosition + 1);

            $integerDigits = preg_replace('/[^0-9]/', '', $integerPart) ?? '';
            $fractionalDigits = preg_replace('/[^0-9]/', '', $fractionalPart) ?? '';

            $normalized = $integerDigits . '.' . $fractionalDigits;
        } else {
            $normalized = preg_replace('/[^0-9]/', '', $numericString) ?? '';
        }

        if ($normalized === '' || $normalized === '.') {
            return 0.0;
        }

        return $sign * (float) $normalized;
    }

    protected static function sanitizeDecimal($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return 0.0;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
            return (float) $normalized;
        }

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
            return (float) $normalized;
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }

    protected static function withDisplayValues(array $state): array
    {
        $state['adjustment_quantity'] = self::formatDecimal(
            self::resolveActiveQuantityValue($state),
            0
        );
        $state['unit_cost'] = self::sanitizeDecimal($state['unit_cost'] ?? 0);
        $state['unit_price'] = self::formatCurrencyValue($state['unit_cost']);
        $state['total_cost'] = self::sanitizeDecimal($state['total_cost'] ?? 0);

        return $state;
    }

    protected static function resolveActiveQuantityValue($state): float
    {
        $type = $state instanceof SchemaGet
            ? $state('adjustment_type')
            : ($state['adjustment_type'] ?? null);

        if ($type === InventoryAdjustment::ADJUSTMENT_TYPE_SET) {
            return self::sanitizeDecimal(
                $state instanceof SchemaGet ? $state('target_quantity') : ($state['target_quantity'] ?? 0)
            );
        }

        return self::sanitizeDecimal(
            $state instanceof SchemaGet ? $state('quantity') : ($state['quantity'] ?? 0)
        );
    }

    protected static function sanitizeDecimalInput(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return self::sanitizeDecimal($value);
    }

    protected static function formatInputDecimal(mixed $value, int $decimals = 3): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::formatDecimal($value, $decimals);
    }

    protected static function formatDecimal(mixed $value, int $decimals = 2): string
    {
        return number_format((float) ($value ?? 0), $decimals, ',', '.');
    }
}
