<?php

namespace App\Filament\Admin\Resources\GoodsReceipts\Schemas;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\LiveChickenPurchaseOrder;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Components\Utilities\Set as SchemaSet;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;

class GoodsReceiptForm
{
    protected static array $purchaseOrderCache = [];
    protected static array $productNameCache = [];
    protected static ?array $liveBirdOptionCache = null;
    protected static ?array $chartOfAccountOptionCache = null;
    protected static array $chartOfAccountDetailsCache = [];
    protected static array $supplierNameCache = [];
    protected static array $warehouseNameCache = [];

    public static function configure(Schema $schema): Schema
    {
        $headerSection = Section::make('Validasi PO & Header')
            ->schema([
                Hidden::make('receipt_number')
                    ->default(null),
                Placeholder::make('receipt_number_display')
                    ->label('No. Penerimaan')
                    ->content(fn (SchemaGet $get): string => $get('receipt_number') ?? 'Nomor akan dibuat otomatis saat disimpan.'),
                Select::make('live_chicken_purchase_order_id')
                    ->label('Referensi PO Ayam Hidup')
                    ->relationship(
                        name: 'purchaseOrder',
                        titleAttribute: 'po_number',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', LiveChickenPurchaseOrder::STATUS_APPROVED)
                    )
                    ->searchable()
                    ->preload(10)
                    ->required()
                    ->native(false)
                    ->live()
                    ->placeholder('Pilih PO yang sudah disetujui')
                    ->default(function (): ?int {
                        $value = request()->query('live_chicken_purchase_order_id');

                        return $value ? (int) $value : null;
                    })
                    ->helperText('Semua detail akan terkunci sampai PO dipilih.')
                    ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                        self::syncPurchaseOrderPayload($state, $set, $get);
                    })
                    ->afterStateHydrated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                        if ($state) {
                            self::syncPurchaseOrderPayload($state, $set, $get);
                        }
                    }),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->visible(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id'))),
                Placeholder::make('supplier_display')
                    ->label('Supplier')
                    ->content(fn (SchemaGet $get): string => self::resolveSupplierDisplay($get('supplier_id'), $get('live_chicken_purchase_order_id')))
                    ->reactive()
                    ->visible(fn (SchemaGet $get): bool => filled($get('live_chicken_purchase_order_id'))),
                Select::make('destination_warehouse_id')
                    ->label('Gudang Tujuan')
                    ->relationship('destinationWarehouse', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Sistem multi-gudang, pastikan gudang tujuan sesuai stok masuk.')
                    ->visible(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id'))),
                Placeholder::make('warehouse_display')
                    ->label('Gudang Tujuan')
                    ->content(fn (SchemaGet $get): string => self::resolveWarehouseDisplay($get('destination_warehouse_id'), $get('live_chicken_purchase_order_id')))
                    ->reactive()
                    ->visible(fn (SchemaGet $get): bool => filled($get('live_chicken_purchase_order_id'))),
                DateTimePicker::make('received_at')
                    ->label('Tanggal & Jam Diterima')
                    ->native(false)
                    ->seconds(false)
                    ->helperText('Kosongkan jika ingin otomatis terisi waktu saat disimpan'),
                Select::make('status')
                    ->label('Status Penerimaan')
                    ->options(GoodsReceipt::statusOptions())
                    ->default(GoodsReceipt::STATUS_DRAFT)
                    ->native(false)
                    ->required(),
            ])
            ->columns(4)
            ->columnSpanFull();

        $itemsSection = Section::make('Detail Item Diterima')
            ->schema([
                Hidden::make('pending_receipt_item_payload'),
                Hidden::make('metadata.receiving_items')
                    ->default([]),
                Placeholder::make('line_item_gate_notice')
                    ->hiddenLabel()
                    ->content('Pilih PO terlebih dahulu untuk mulai mencatat item yang diterima.')
                    ->visible(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id')))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'text-sm font-medium text-danger-600']),
                Placeholder::make('line_item_info')
                    ->hiddenLabel()
                    ->content('Item otomatis menyalin rincian dari PO. Sesuaikan qty/berat sesuai hasil terima.')
                    ->visible(fn (SchemaGet $get): bool => filled($get('live_chicken_purchase_order_id')))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'text-sm text-primary-600']),
                Select::make('receipt_item_search')
                    ->label('Cari & Tambah Barang')
                    ->placeholder('Ketik kode atau nama produk')
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->live()
                    ->preload()
                    ->options(fn () => self::getAllLiveBirdProductOptions())
                    ->dehydrated(false)
                    ->disabled(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id')))
                    ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get, Select $component): void {
                        if (! $state) {
                            return;
                        }

                        $payload = self::buildPendingReceiptItemPayloadFromProduct($state);

                        $set('receipt_item_search', null);

                        if (! $payload) {
                            return;
                        }

                        $itemsComponent = self::resolveReceiptItemsComponentFrom($component);

                        if ($itemsComponent && self::triggerPendingReceiptItemModal($payload, $itemsComponent)) {
                            return;
                        }

                        $set('pending_receipt_item_payload', $payload);
                    })
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->relationship('items')
                    ->label('Daftar Item')
                    ->schema(self::receiptItemTableSchema())
                    ->table(self::receiptItemTableColumns())
                    ->default([])
                    ->columns(12)
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->cloneable(false)
                    ->extraItemActions([
                        self::makeEditReceiptItemAction(),
                    ])
                    ->extraAttributes(['data-row-click-action' => 'edit_receipt_item'])
                    ->itemLabel(fn (array $state): string => normalize_item_name($state['item_name'] ?? null) ?? 'Item Penerimaan')
                    ->helperText('Klik baris untuk menyesuaikan qty terima, susut, atau catatan QC.')
                    ->disabled(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id')))
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                        if (blank($state) && filled($get('live_chicken_purchase_order_id'))) {
                            $state = self::hydrateItemsFromPurchaseOrder($get('live_chicken_purchase_order_id'), $set, $get) ?? $state;
                        }

                        $stateWithKeys = self::ensureReceiptItemBufferKeys($state ?? []);

                        if ($stateWithKeys !== ($state ?? [])) {
                            $set('items', $stateWithKeys);
                        }

                        self::ensureRawStateBufferKeys($component);

                        self::syncItemSummaries($set, $get, $stateWithKeys);
                        self::storeReceivingItemsMetadata($set, $get, $stateWithKeys);
                        self::processPendingReceiptItemPayload($set, $get, $component);
                    })
                    ->afterStateUpdated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                        $stateWithKeys = self::ensureReceiptItemBufferKeys($state ?? []);

                        if ($stateWithKeys !== ($state ?? [])) {
                            $set('items', $stateWithKeys);
                        }

                        self::ensureRawStateBufferKeys($component);

                        self::syncItemSummaries($set, $get, $stateWithKeys);
                        self::storeReceivingItemsMetadata($set, $get, $stateWithKeys);
                    }),
            ])
            ->columnSpanFull();

        $summarySection = Section::make('Ringkasan Penerimaan')
            ->schema([
                Hidden::make('total_item_count')->default(0),
                Hidden::make('total_received_quantity_ea')->default(0),
                Hidden::make('total_received_weight_kg')->default(0),
                Hidden::make('loss_quantity_ea')->default(0),
                Hidden::make('loss_weight_kg')->default(0),
                Hidden::make('loss_percentage')->default(0),
                Placeholder::make('summary_total_items')
                    ->label('Jumlah Item')
                    ->content(fn (SchemaGet $get): string => number_format((int) ($get('total_item_count') ?? 0))),
                Placeholder::make('summary_total_quantity')
                    ->label('Total Qty Terima (Ekor)')
                    ->content(fn (SchemaGet $get): string => self::formatDecimal($get('total_received_quantity_ea'), 3)),
                Placeholder::make('summary_total_weight')
                    ->label('Total Berat Terima (Kg)')
                    ->content(fn (SchemaGet $get): string => self::formatDecimal($get('total_received_weight_kg'))),
                Placeholder::make('summary_loss_qty')
                    ->label('Total Susut Qty')
                    ->content(fn (SchemaGet $get): string => self::formatDecimal($get('loss_quantity_ea'), 3)),
                Placeholder::make('summary_loss_weight')
                    ->label('Total Susut Berat (Kg)')
                    ->content(fn (SchemaGet $get): string => self::formatDecimal($get('loss_weight_kg'))),
                Placeholder::make('summary_loss_percentage')
                    ->label('Persentase Susut')
                    ->content(fn (SchemaGet $get): string => self::formatDecimal($get('loss_percentage'), 2) . ' %'),
            ])
            ->columns(3)
            ->columnSpanFull();

        $inspectionSection = Section::make('Pemeriksaan Kedatangan')
            ->schema([
                TextInput::make('supplier_delivery_note_number')
                    ->label('No. Surat Jalan Supplier')
                    ->maxLength(50),
                TextInput::make('vehicle_plate_number')
                    ->label('No. Polisi Kendaraan')
                    ->maxLength(30),
                TextInput::make('arrival_inspector_name')
                    ->label('Petugas Pemeriksa')
                    ->maxLength(120),
                TextInput::make('arrival_temperature_c')
                    ->label('Suhu Kedatangan (°C)')
                    ->numeric()
                    ->step('0.01'),
                CheckboxList::make('arrival_checks')
                    ->label('Checklist Kedatangan')
                    ->options([
                        'seal_intact' => 'Segel kendaraan utuh',
                        'weight_documented' => 'Hasil timbang terlampir',
                        'sanitation_passed' => 'Sanitasi area ok',
                        'temperature_ok' => 'Suhu dalam batas toleransi',
                    ])
                    ->columns(2),
                Textarea::make('arrival_notes')
                    ->label('Catatan Pemeriksaan')
                    ->rows(3),
            ])
            ->columns(2)
            ->columnSpanFull();

        $costSection = Section::make('Biaya & Dokumen Pendukung')
            ->schema([
                Hidden::make('pending_additional_cost_payload'),
                Select::make('additional_cost_search')
                    ->label('Cari & Tambah Biaya Tambahan')
                    ->placeholder('Ketik kode atau nama akun')
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->live()
                    ->preload()
                    ->options(fn (): array => self::getChartOfAccountOptions())
                    ->dehydrated(false)
                    ->afterStateUpdated(function (?string $state, SchemaSet $set, SchemaGet $get, Select $component): void {
                        if (! $state) {
                            return;
                        }

                        $payload = self::buildPendingAdditionalCostPayloadFromAccount($state);

                        $set('additional_cost_search', null);

                        if (! $payload) {
                            return;
                        }

                        $costComponent = self::resolveAdditionalCostsComponentFrom($component);

                        if ($costComponent && self::triggerPendingAdditionalCostModal($payload, $costComponent)) {
                            return;
                        }

                        $set('pending_additional_cost_payload', $payload);
                    })
                    ->columnSpanFull(),
                Repeater::make('additional_costs')
                    ->label('Daftar Biaya Tambahan')
                    ->schema(self::additionalCostTableSchema())
                    ->table(self::additionalCostTableColumns())
                    ->default([])
                    ->columns(12)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->cloneable(false)
                    ->extraItemActions([
                        self::makeEditAdditionalCostAction(),
                    ])
                    ->extraAttributes(['data-row-click-action' => 'edit_additional_cost'])
                    ->itemLabel(fn (array $state): string => $state['name'] ?? 'Biaya Tambahan')
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                        $stateWithKeys = self::ensureAdditionalCostBufferKeys($state ?? []);

                        if ($stateWithKeys !== ($state ?? [])) {
                            $set('additional_costs', $stateWithKeys);
                        }

                        self::ensureAdditionalCostRawStateBufferKeys($component);
                        self::processPendingAdditionalCostPayload($set, $get, $component);
                    })
                    ->afterStateUpdated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                        $stateWithKeys = self::ensureAdditionalCostBufferKeys($state ?? []);

                        if ($stateWithKeys !== ($state ?? [])) {
                            $set('additional_costs', $stateWithKeys);
                        }

                        self::ensureAdditionalCostRawStateBufferKeys($component);
                    }),
                FileUpload::make('attachments')
                    ->label('Lampiran Dokumen / Foto')
                    ->directory('goods-receipts/attachments')
                    ->multiple()
                    ->downloadable()
                    ->previewable(true)
                    ->maxFiles(10)
                    ->maxSize(5120)
                    ->helperText('Format jpg/png/pdf/docx/xlsx, maksimal 5MB per file.'),
            ])
            ->columnSpanFull();

        return $schema
            ->components([
                $headerSection,
                Tabs::make('goods_receipt_form_tabs')
                    ->tabs([
                        Tab::make('Detail Penerimaan')
                            ->schema([
                                $itemsSection,
                                $summarySection,
                            ]),
                        Tab::make('Pemeriksaan Kedatangan')
                            ->schema([
                                $inspectionSection,
                            ]),
                        Tab::make('Biaya & Dokumen')
                            ->schema([
                                $costSection,
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function syncPurchaseOrderPayload(?int $purchaseOrderId, SchemaSet $set, SchemaGet $get): void
    {
        if (! $purchaseOrderId) {
            return;
        }

        $payload = self::getPurchaseOrderPayload($purchaseOrderId);

        if (! $payload) {
            return;
        }

        $set('supplier_id', $payload['supplier_id']);
        $set('destination_warehouse_id', $payload['destination_warehouse_id']);
        self::hydrateItemsFromPurchaseOrder($purchaseOrderId, $set, $get);
    }

    protected static function getPurchaseOrderPayload(int $purchaseOrderId): ?array
    {
        if (! array_key_exists($purchaseOrderId, self::$purchaseOrderCache)) {
            $po = LiveChickenPurchaseOrder::query()
                ->whereKey($purchaseOrderId)
                ->first([
                    'id',
                    'supplier_id',
                    'destination_warehouse_id',
                ]);

            self::$purchaseOrderCache[$purchaseOrderId] = $po
                ? [
                    'supplier_id' => $po->supplier_id,
                    'destination_warehouse_id' => $po->destination_warehouse_id,
                ]
                : null;
        }

        return self::$purchaseOrderCache[$purchaseOrderId];
    }

    protected static function hydrateItemsFromPurchaseOrder(int $purchaseOrderId, SchemaSet $set, SchemaGet $get): ?array
    {
        $po = LiveChickenPurchaseOrder::query()
            ->withCasts([
                'metadata' => 'array',
            ])
            ->find($purchaseOrderId, ['id', 'metadata']);

        if (! $po) {
            return null;
        }

        $lineItems = data_get($po->metadata ?? [], 'line_items', []);

        if (! is_array($lineItems) || empty($lineItems)) {
            return null;
        }

        $mappedItems = collect($lineItems)
            ->map(function (array $item): array {
                $productId = $item['product_id'] ?? null;
                $itemCode = $item['item_code'] ?? null;
                $itemName = normalize_item_name($item['item_name'] ?? ($item['name'] ?? null));
                $unit = strtolower((string) ($item['unit'] ?? 'kg'));
                $rawQuantity = (float) ($item['quantity'] ?? 0);
                $rawWeight = (float) ($item['weight_kg'] ?? $item['quantity_kg'] ?? 0);

                $orderedQty = $unit === 'ekor' ? $rawQuantity : 0;
                $orderedWeight = $rawWeight > 0 ? $rawWeight : ($unit === 'kg' ? $rawQuantity : 0);

                return [
                    'product_id' => $productId,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'unit' => $unit,
                    'ordered_quantity' => $orderedQty,
                    'ordered_weight_kg' => $orderedWeight,
                    'received_quantity' => $orderedQty,
                    'received_weight_kg' => $orderedWeight,
                    'loss_quantity' => 0,
                    'loss_weight_kg' => 0,
                    'tolerance_percentage' => self::calculateItemLossPercentage($orderedQty, $orderedWeight, 0, 0),
                    'warehouse_id' => null,
                    'is_returned' => false,
                    'status' => 'pending',
                    'qc_notes' => null,
                    'buffer_key' => (string) Str::uuid(),
                ];
            })
            ->values()
            ->all();

        $set('items', $mappedItems);
        self::storeReceivingItemsMetadata($set, $get, $mappedItems);
        self::setSummaryFieldsFromItems($set, $mappedItems);

        return $mappedItems;
    }

    protected static function syncItemSummaries(SchemaSet $set, SchemaGet $get, array $items): void
    {
        self::setSummaryFieldsFromItems($set, $items);
    }

    protected static function setSummaryFieldsFromItems(SchemaSet $set, array $items): void
    {
        $collection = Collection::make($items);

        $totalItems = $collection->count();
        $totalOrderedQty = $collection->sum(fn (array $item): float => (float) ($item['ordered_quantity'] ?? 0));
        $totalOrderedWeight = $collection->sum(fn (array $item): float => (float) ($item['ordered_weight_kg'] ?? 0));
        $totalReceivedQty = $collection->sum(fn (array $item): float => (float) ($item['received_quantity'] ?? 0));
        $totalReceivedWeight = $collection->sum(fn (array $item): float => (float) ($item['received_weight_kg'] ?? 0));
        $lossQty = $collection->sum(fn (array $item): float => (float) ($item['loss_quantity'] ?? 0));
        $lossWeight = $collection->sum(fn (array $item): float => (float) ($item['loss_weight_kg'] ?? 0));

        if ($lossQty <= 0) {
            $lossQty = max($totalOrderedQty - $totalReceivedQty, 0);
        }

        if ($lossWeight <= 0) {
            $lossWeight = max($totalOrderedWeight - $totalReceivedWeight, 0);
        }

        $lossPercentage = 0;

        if ($totalOrderedWeight > 0) {
            $lossPercentage = round(($lossWeight / max($totalOrderedWeight, 0.0001)) * 100, 2);
        } elseif ($totalOrderedQty > 0) {
            $lossPercentage = round(($lossQty / max($totalOrderedQty, 0.0001)) * 100, 2);
        }

        $set('total_item_count', $totalItems);
        $set('total_received_quantity_ea', round($totalReceivedQty, 3));
        $set('total_received_weight_kg', round($totalReceivedWeight, 3));
        $set('loss_quantity_ea', round($lossQty, 3));
        $set('loss_weight_kg', round($lossWeight, 3));
        $set('loss_percentage', round($lossPercentage, 2));
    }

    protected static function formatDecimal(mixed $value, int $decimals = 2): string
    {
        $formatted = number_format((float) ($value ?? 0), $decimals, ',', '.');

        if (! str_contains($formatted, ',')) {
            return $formatted;
        }

        [$integerPart, $decimalPart] = explode(',', $formatted, 2);
        $trimmedDecimal = rtrim($decimalPart, '0');

        if ($trimmedDecimal === '') {
            return $integerPart;
        }

        return sprintf('%s,%s', $integerPart, $trimmedDecimal);
    }

    protected static function formatCurrency(mixed $value): string
    {
        return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
    }

    protected static function formatInputDecimal(mixed $value, int $decimals = 3): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::formatDecimal($value, $decimals);
    }

    protected static function calculateItemLossPercentage(float $orderedQty, float $orderedWeight, float $lossQty, float $lossWeight): float
    {
        if ($orderedWeight > 0) {
            return round(($lossWeight / max($orderedWeight, 0.0001)) * 100, 2);
        }

        if ($orderedQty > 0) {
            return round(($lossQty / max($orderedQty, 0.0001)) * 100, 2);
        }

        return 0;
    }

    protected static function formatLossSnapshot(array $item): string
    {
        $orderedQty = sanitize_decimal($item['ordered_quantity'] ?? 0);
        $orderedWeight = sanitize_decimal($item['ordered_weight_kg'] ?? 0);
        $receivedQty = sanitize_decimal($item['received_quantity'] ?? 0);
        $receivedWeight = sanitize_decimal($item['received_weight_kg'] ?? 0);
        $lossQtyField = $item['loss_quantity'] ?? null;
        $lossWeightField = $item['loss_weight_kg'] ?? null;
        $lossQty = $lossQtyField !== null ? sanitize_decimal($lossQtyField) : max($orderedQty - $receivedQty, 0);
        $lossWeight = $lossWeightField !== null ? sanitize_decimal($lossWeightField) : max($orderedWeight - $receivedWeight, 0);

        $percentage = self::calculateItemLossPercentage($orderedQty, $orderedWeight, $lossQty, $lossWeight);
        $unit = strtolower((string) ($item['unit'] ?? 'kg'));

        $lossDisplay = match ($unit) {
            'ekor' => self::formatDecimal($lossQty, 3) . ' Ekor',
            'kg' => self::formatDecimal($lossWeight, 3) . ' Kg',
            default => self::formatDecimal($lossQty, 3) . ' Ekor | ' . self::formatDecimal($lossWeight, 3) . ' Kg',
        };

        return sprintf('%s %% (%s)', self::formatDecimal($percentage, 2), $lossDisplay);
    }

    protected static function resolveSupplierName(?int $supplierId): ?string
    {
        if (! $supplierId) {
            return null;
        }

        if (! array_key_exists($supplierId, self::$supplierNameCache)) {
            self::$supplierNameCache[$supplierId] = Supplier::query()
                ->whereKey($supplierId)
                ->value('name');
        }

        return self::$supplierNameCache[$supplierId];
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

    protected static function resolveReceiptItemsComponentFrom(Component $context): ?Repeater
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


    protected static function processPendingReceiptItemPayload(SchemaSet $set, SchemaGet $get, Repeater $component): void
    {
        $payload = $get('pending_receipt_item_payload');

        if (! is_array($payload)) {
            return;
        }

        $set('pending_receipt_item_payload', null);

        if (! self::triggerPendingReceiptItemModal($payload, $component)) {
            $set('pending_receipt_item_payload', $payload);
        }
    }

    protected static function processPendingAdditionalCostPayload(SchemaSet $set, SchemaGet $get, Repeater $component): void
    {
        $payload = $get('pending_additional_cost_payload');

        if (! is_array($payload)) {
            return;
        }

        $set('pending_additional_cost_payload', null);

        if (! self::triggerPendingAdditionalCostModal($payload, $component)) {
            $set('pending_additional_cost_payload', $payload);
        }
    }

    protected static function buildPendingReceiptItemPayloadFromProduct(int $productId): ?array
    {
        $data = self::defaultReceiptItemState();
        $data['product_id'] = $productId;

        $details = self::getLiveBirdProductDetails($productId);

        if ($details) {
            $data['item_name'] = normalize_item_name($details['name'] ?? null);
            $data['item_code'] = $details['code'] ?? null;
            $data['unit'] = self::resolveProductUnitCode($details['unit_id'] ?? null);
        }

        $data['buffer_key'] = (string) Str::uuid();

        return $data;
    }

    protected static function buildPendingAdditionalCostPayloadFromAccount(string $accountCode): ?array
    {
        $details = self::getChartOfAccountDetails($accountCode);

        if (! $details) {
            return null;
        }

        return [
            'name' => $details['name'] ?? 'Biaya Tambahan',
            'coa_reference' => $details['code'] ?? $accountCode,
            'amount' => 0,
            'notes' => null,
            'buffer_key' => (string) Str::uuid(),
        ];
    }

    protected static function resolveSupplierDisplay(?int $supplierId, ?int $purchaseOrderId): string
    {
        $name = self::resolveSupplierName($supplierId);

        if (! $name && $purchaseOrderId) {
            $payload = self::getPurchaseOrderPayload($purchaseOrderId);
            if ($payload) {
                $name = self::resolveSupplierName($payload['supplier_id'] ?? null);
            }
        }

        return $name ?? '-';
    }

    protected static function resolveWarehouseDisplay(?int $warehouseId, ?int $purchaseOrderId): string
    {
        $name = self::resolveWarehouseName($warehouseId);

        if (! $name && $purchaseOrderId) {
            $payload = self::getPurchaseOrderPayload($purchaseOrderId);
            if ($payload) {
                $name = self::resolveWarehouseName($payload['destination_warehouse_id'] ?? null);
            }
        }

        return $name ?? '-';
    }
    

    protected static function resolveAdditionalCostsComponentFrom(Component $context): ?Repeater
    {
        $livewire = $context->getLivewire();

        if (! $livewire || ! method_exists($livewire, 'getSchemaComponent')) {
            return null;
        }

        $key = $context->resolveRelativeKey('additional_costs');

        if (blank($key)) {
            return null;
        }

        return $livewire->getSchemaComponent($key, withHidden: true);
    }

    protected static function triggerPendingReceiptItemModal(array $payload, Repeater $component): bool
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
                $livewire->mountAction('edit_receipt_item', $arguments, [
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
                'action' => 'edit_receipt_item',
                'arguments' => $arguments,
                'context' => ['schemaComponent' => $schemaComponentKey],
            ]);

            return true;
        }

        return false;
    }

    protected static function triggerPendingAdditionalCostModal(array $payload, Repeater $component): bool
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
                $livewire->mountAction('edit_additional_cost', $arguments, [
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
                'action' => 'edit_additional_cost',
                'arguments' => $arguments,
                'context' => ['schemaComponent' => $schemaComponentKey],
            ]);

            return true;
        }

        return false;
    }

    protected static function getAllLiveBirdProductOptions(): array
    {
        if (self::$liveBirdOptionCache === null) {
            self::$liveBirdOptionCache = Product::query()
                ->whereHas('productCategory', fn (Builder $query): Builder => $query->where('code', 'LB'))
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (Product $product): array => [
                    $product->id => sprintf('%s · %s', $product->code, $product->name),
                ])
                ->toArray();
        }

        return self::$liveBirdOptionCache;
    }

    protected static function getChartOfAccountOptions(): array
    {
        if (self::$chartOfAccountOptionCache === null) {
            self::$chartOfAccountOptionCache = ChartOfAccount::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->limit(300)
                ->get(['code', 'name'])
                ->mapWithKeys(fn (ChartOfAccount $account): array => [
                    $account->code => sprintf('%s · %s', $account->code, $account->name),
                ])
                ->toArray();
        }

        return self::$chartOfAccountOptionCache;
    }

    protected static function getChartOfAccountDetails(?string $code): ?array
    {
        if (! $code) {
            return null;
        }

        if (! array_key_exists($code, self::$chartOfAccountDetailsCache)) {
            self::$chartOfAccountDetailsCache[$code] = ChartOfAccount::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first(['code', 'name'])?->toArray();
        }

        return self::$chartOfAccountDetailsCache[$code] ?? null;
    }

    protected static function getLiveBirdProductDetails(?int $productId): ?array
    {
        if (! $productId) {
            return null;
        }

        if (! array_key_exists($productId, self::$productNameCache)) {
            self::$productNameCache[$productId] = Product::query()
                ->whereKey($productId)
                ->first([
                    'id',
                    'code',
                    'name',
                    'unit_id',
                ])?->toArray();
        }

        return self::$productNameCache[$productId] ?? null;
    }

    protected static function resolveProductUnitCode(?int $unitId): string
    {
        return match ($unitId) {
            2 => 'ekor',
            default => 'kg',
        };
    }

    protected static function receiptItemTableSchema(): array
    {
        return [
            Hidden::make('product_id'),
            Hidden::make('item_code'),
            Hidden::make('item_name'),
            Hidden::make('unit'),
            Hidden::make('ordered_quantity'),
            Hidden::make('ordered_weight_kg'),
            Hidden::make('received_quantity'),
            Hidden::make('received_weight_kg'),
            Hidden::make('loss_quantity'),
            Hidden::make('loss_weight_kg'),
            Hidden::make('tolerance_percentage'),
            Hidden::make('warehouse_id'),
            Hidden::make('is_returned'),
            Hidden::make('status'),
            Hidden::make('qc_notes'),
            Hidden::make('buffer_key'),
            Placeholder::make('table_item_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $segments = array_filter([
                        normalize_item_name($get('item_name')) ?? 'Item belum diberi nama',
                        $get('item_code') ? sprintf('[%s]', $get('item_code')) : null,
                        strtoupper((string) ($get('unit') ?? 'KG')),
                    ]);

                    return self::convertNewlinesToBreaks(implode(PHP_EOL, array_values($segments)));
                })
                ->color('primary')
                ->html()
                ->extraAttributes(['class' => 'leading-tight text-sm font-medium']),
            Placeholder::make('table_order_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $summary = self::formatUnitAwareSummary(
                        $get('unit'),
                        $get('ordered_quantity'),
                        $get('ordered_weight_kg')
                    );

                    return self::convertNewlinesToBreaks($summary);
                })
                ->html()
                ->extraAttributes(['class' => 'text-sm text-gray-700 tabular-nums text-right']),
            Placeholder::make('table_received_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $summary = self::formatUnitAwareSummary(
                        $get('unit'),
                        $get('received_quantity'),
                        $get('received_weight_kg')
                    );

                    return self::convertNewlinesToBreaks($summary);
                })
                ->html()
                ->extraAttributes(['class' => 'text-sm font-semibold text-gray-900 tabular-nums text-right']),
            Placeholder::make('table_loss_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $lossSummary = self::formatUnitAwareSummary(
                        $get('unit'),
                        $get('loss_quantity'),
                        $get('loss_weight_kg')
                    );
                    $tolerance = number_format((float) ($get('tolerance_percentage') ?? 0), 2, ',', '.');

                    return self::convertNewlinesToBreaks(trim($lossSummary . PHP_EOL . sprintf('Susut %s%%', $tolerance)));
                })
                ->html()
                ->extraAttributes(['class' => 'text-sm text-danger-600 tabular-nums text-right']),
            Placeholder::make('table_qc_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $returnFlag = $get('is_returned') ? 'Perlu Retur' : 'OK';
                    $notes = Str::limit((string) ($get('qc_notes') ?? 'Tidak ada catatan'), 60);

                    return self::convertNewlinesToBreaks($returnFlag . PHP_EOL . $notes);
                })
                ->html()
                ->extraAttributes(['class' => 'text-sm text-gray-700']),
        ];
    }

    protected static function receiptItemTableColumns(): array
    {
        return [
            TableColumn::make('Barang')->width('28rem'),
            TableColumn::make('Pesanan')->width('10rem'),
            TableColumn::make('Diterima')->width('10rem'),
            TableColumn::make('Susut')->width('10rem'),
            TableColumn::make('QC / Retur')->width('16rem'),
        ];
    }

    protected static function receiptItemFormFields(): array
    {
        return [
            Hidden::make('product_id'),
            Hidden::make('item_code'),
            Hidden::make('unit'),
            Hidden::make('ordered_quantity'),
            Hidden::make('ordered_weight_kg'),
            Hidden::make('loss_quantity'),
            Hidden::make('loss_weight_kg'),
            Hidden::make('status'),
            Hidden::make('buffer_key'),
            TextInput::make('item_name')
                ->label('Nama Item')
                ->inlineLabel()
                ->readOnly()
                ->columnSpanFull(),
            Placeholder::make('ordered_snapshot')
                ->label('Pesanan')
                ->inlineLabel()
                ->content(function (SchemaGet $get): string {
                    $unit = strtolower((string) ($get('unit') ?? ''));
                    $orderedQty = self::formatDecimal($get('ordered_quantity'), 3);
                    $orderedWeight = self::formatDecimal($get('ordered_weight_kg'), 3);

                    return match ($unit) {
                        'ekor' => sprintf('%s Ekor', $orderedQty),
                        'kg' => sprintf('%s Kg', $orderedWeight),
                        default => sprintf('%s Ekor | %s Kg', $orderedQty, $orderedWeight),
                    };
                })
                ->columnSpanFull(),
            TextInput::make('received_quantity')
                ->label('Qty Terima (Ekor)')
                ->inlineLabel()
                ->type('text')
                ->default(0)
                ->dehydrateStateUsing(fn ($state): float => sanitize_positive_decimal($state))
                ->formatStateUsing(fn ($state): string => self::formatInputDecimal($state, 3))
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 3)
JS
                ))
                ->columnSpanFull()
                ->visible(fn (SchemaGet $get): bool => self::shouldDisplayQuantityField($get('unit'))),
            TextInput::make('received_weight_kg')
                ->label('Berat Terima (Kg)')
                ->inlineLabel()
                ->type('text')
                ->default(0)
                ->dehydrateStateUsing(fn ($state): float => sanitize_positive_decimal($state))
                ->formatStateUsing(fn ($state): string => self::formatInputDecimal($state, 3))
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 3)
JS
                ))
                ->columnSpanFull()
                ->visible(fn (SchemaGet $get): bool => self::shouldDisplayWeightField($get('unit'))),
            Placeholder::make('loss_snapshot')
                ->label('Total Susut')
                ->inlineLabel()
                ->content(function (SchemaGet $get): string {
                    return self::formatLossSnapshot([
                        'unit' => $get('unit'),
                        'ordered_quantity' => $get('ordered_quantity'),
                        'ordered_weight_kg' => $get('ordered_weight_kg'),
                        'received_quantity' => $get('received_quantity'),
                        'received_weight_kg' => $get('received_weight_kg'),
                        'loss_quantity' => $get('loss_quantity'),
                        'loss_weight_kg' => $get('loss_weight_kg'),
                    ]);
                })
                ->columnSpanFull(),
            Select::make('warehouse_id')
                ->label('Gudang Penyimpanan')
                ->inlineLabel()
                ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->native(false)
                ->placeholder('Ikuti gudang tujuan penerimaan')
                ->columnSpanFull(),
            Toggle::make('is_returned')
                ->label('Butuh Retur?')
                ->inline(false)
                ->columnSpanFull(),
            Textarea::make('qc_notes')
                ->label('Catatan QC / Alasan Susut')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    protected static function additionalCostTableSchema(): array
    {
        return [
            Hidden::make('name'),
            Hidden::make('coa_reference'),
            Hidden::make('amount'),
            Hidden::make('notes'),
            Hidden::make('buffer_key'),
            Placeholder::make('table_cost_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $name = $get('name') ?: 'Biaya tanpa nama';
                    $code = $get('coa_reference') ? sprintf('[%s]', $get('coa_reference')) : null;

                    return self::convertNewlinesToBreaks(implode(PHP_EOL, array_filter([$name, $code])));
                })
                ->html()
                ->extraAttributes(['class' => 'leading-tight text-sm font-medium text-gray-900']),
            Placeholder::make('table_cost_amount')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrency($get('amount')))
                ->extraAttributes(['class' => 'text-right font-semibold tabular-nums text-sm text-gray-900']),
            Placeholder::make('table_cost_notes')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $notes = Str::limit((string) ($get('notes') ?? 'Tidak ada catatan'), 120);

                    return self::convertNewlinesToBreaks($notes);
                })
                ->html()
                ->extraAttributes(['class' => 'text-sm text-gray-700']),
        ];
    }

    protected static function additionalCostTableColumns(): array
    {
        return [
            TableColumn::make('Biaya')->width('24rem'),
            TableColumn::make('Nominal')->width('10rem'),
            TableColumn::make('Catatan')->width('20rem'),
        ];
    }

    protected static function additionalCostFormFields(): array
    {
        return [
            Hidden::make('buffer_key'),
            Hidden::make('coa_reference'),
            Placeholder::make('coa_reference_display')
                ->label('Referensi COA / Akun')
                ->inlineLabel()
                ->content(fn (SchemaGet $get): string => $get('coa_reference') ? sprintf('[%s]', $get('coa_reference')) : 'Belum dipilih (gunakan pencarian akun).')
                ->columnSpanFull(),
            TextInput::make('name')
                ->label('Nama Biaya')
                ->inlineLabel()
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),
            TextInput::make('amount')
                ->label('Nominal')
                ->inlineLabel()
                ->prefix('Rp')
                ->default(0)
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS
                ))
                ->stripCharacters(['.', ','])
                ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                ->columnSpanFull(),
            Textarea::make('notes')
                ->label('Catatan')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    protected static function makeEditReceiptItemAction(): Action
    {
        return Action::make('edit_receipt_item')
            ->label('Sesuaikan Item')
            ->modalHeading('Detail Item Penerimaan')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('4xl')
            ->schema(self::receiptItemFormFields())
            ->extraAttributes(['data-row-trigger-only' => true])
            ->mountUsing(function (Schema $schema, array $arguments, Repeater $component): void {
                if (! empty($arguments['pending']) && is_array($arguments['payload'] ?? null)) {
                    $schema->fill($arguments['payload']);

                    return;
                }

                if (is_array($arguments['payload'] ?? null)) {
                    $schema->fill($arguments['payload']);

                    return;
                }

                if (! empty($arguments['buffer_key'])) {
                    $state = self::getReceiptItemStateByBufferKey($component, $arguments['buffer_key']);

                    if ($state) {
                        $schema->fill($state);

                        return;
                    }
                }

                $itemKey = $arguments['item'] ?? null;
                $state = self::getReceiptItemStateByKey($component, $itemKey) ?? self::defaultReceiptItemState();

                $schema->fill($state);
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $itemKey = $arguments['item'] ?? null;
                $isPending = (bool) ($arguments['pending'] ?? false);

                if ($isPending) {
                    self::upsertReceiptItemState($component, self::prepareReceiptItemPayload($data));

                    return;
                }

                if ($itemKey === null) {
                    return;
                }

                self::upsertReceiptItemState($component, self::prepareReceiptItemPayload($data), $itemKey);
            });
    }

    protected static function makeEditAdditionalCostAction(): Action
    {
        return Action::make('edit_additional_cost')
            ->label('Sesuaikan Biaya')
            ->modalHeading('Detail Biaya Tambahan')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('lg')
            ->schema(self::additionalCostFormFields())
            ->extraAttributes(['data-row-trigger-only' => true])
            ->mountUsing(function (Schema $schema, array $arguments, Repeater $component): void {
                if (! empty($arguments['pending']) && is_array($arguments['payload'] ?? null)) {
                    $schema->fill($arguments['payload']);

                    return;
                }

                if (is_array($arguments['payload'] ?? null)) {
                    $schema->fill($arguments['payload']);

                    return;
                }

                if (! empty($arguments['buffer_key'])) {
                    $state = self::getAdditionalCostStateByBufferKey($component, $arguments['buffer_key']);

                    if ($state) {
                        $schema->fill($state);

                        return;
                    }
                }

                $itemKey = $arguments['item'] ?? null;
                $state = self::getAdditionalCostStateByKey($component, $itemKey) ?? self::defaultAdditionalCostState();

                $schema->fill($state);
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $itemKey = $arguments['item'] ?? null;

                if ($arguments['delete_additional_cost'] ?? false) {
                    self::removeAdditionalCostState($component, $itemKey);

                    return;
                }

                if (! empty($arguments['pending'])) {
                    self::upsertAdditionalCostState($component, self::prepareAdditionalCostPayload($data));

                    return;
                }

                if ($itemKey === null) {
                    return;
                }

                self::upsertAdditionalCostState($component, self::prepareAdditionalCostPayload($data), $itemKey);
            })
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('delete_additional_cost', arguments: ['delete_additional_cost' => true])
                    ->label('Hapus')
                    ->color('danger')
                    ->requiresConfirmation(),
            ]);
    }

    protected static function defaultReceiptItemState(): array
    {
        return [
            'product_id' => null,
            'item_code' => null,
            'item_name' => null,
            'unit' => 'kg',
            'ordered_quantity' => 0,
            'ordered_weight_kg' => 0,
            'received_quantity' => 0,
            'received_weight_kg' => 0,
            'loss_quantity' => 0,
            'loss_weight_kg' => 0,
            'tolerance_percentage' => 0,
            'warehouse_id' => null,
            'is_returned' => false,
            'status' => 'pending',
            'qc_notes' => null,
            'buffer_key' => null,
        ];
    }

    protected static function defaultAdditionalCostState(): array
    {
        return [
            'name' => null,
            'coa_reference' => null,
            'amount' => 0,
            'notes' => null,
            'buffer_key' => null,
        ];
    }

    protected static function prepareReceiptItemPayload(array $data): array
    {
        $orderedQty = sanitize_positive_decimal($data['ordered_quantity'] ?? 0);
        $orderedWeight = sanitize_positive_decimal($data['ordered_weight_kg'] ?? 0);
        $receivedQty = sanitize_positive_decimal($data['received_quantity'] ?? 0);
        $receivedWeight = sanitize_positive_decimal($data['received_weight_kg'] ?? 0);

        $lossQty = max($orderedQty - $receivedQty, 0);
        $lossWeight = max($orderedWeight - $receivedWeight, 0);

        return [
            'product_id' => $data['product_id'] ?? null,
            'item_code' => $data['item_code'] ?? null,
            'item_name' => normalize_item_name($data['item_name'] ?? null),
            'unit' => $data['unit'] ?? 'kg',
            'ordered_quantity' => $orderedQty,
            'ordered_weight_kg' => $orderedWeight,
            'received_quantity' => $receivedQty,
            'received_weight_kg' => $receivedWeight,
            'loss_quantity' => round($lossQty, 3),
            'loss_weight_kg' => round($lossWeight, 3),
            'tolerance_percentage' => self::calculateItemLossPercentage($orderedQty, $orderedWeight, $lossQty, $lossWeight),
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'is_returned' => (bool) ($data['is_returned'] ?? false),
            'status' => $data['status'] ?? 'pending',
            'qc_notes' => $data['qc_notes'] ?? null,
            'buffer_key' => $data['buffer_key'] ?? (string) Str::uuid(),
        ];
    }

    protected static function prepareAdditionalCostPayload(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'coa_reference' => $data['coa_reference'] ?? null,
            'amount' => sanitize_rupiah($data['amount'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'buffer_key' => $data['buffer_key'] ?? (string) Str::uuid(),
        ];
    }

    protected static function upsertReceiptItemState(Repeater $component, array $payload, ?string $itemKey = null): string
    {
        $items = $component->getRawState() ?? [];

        $resolvedKey = $itemKey;

        if ($resolvedKey !== null && ! array_key_exists($resolvedKey, $items)) {
            $resolvedKey = self::locateItemKeyByBufferKey($items, $payload['buffer_key'] ?? null) ?? $resolvedKey;
        }

        if ($resolvedKey === null || ! array_key_exists($resolvedKey, $items)) {
            $resolvedKey = (string) Str::uuid();
        }

        $items[$resolvedKey] = array_merge($items[$resolvedKey] ?? [], $payload);

        $component->rawState($items);
        $component->callAfterStateUpdated();
        $component->partiallyRender();

        $livewire = $component->getLivewire();

        if ($livewire && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('filament::line-item-modal-closed');
        }

        return $resolvedKey;
    }

    protected static function upsertAdditionalCostState(Repeater $component, array $payload, ?string $itemKey = null): string
    {
        $items = $component->getRawState() ?? [];

        $resolvedKey = $itemKey;

        if ($resolvedKey !== null && ! array_key_exists($resolvedKey, $items)) {
            $resolvedKey = self::locateAdditionalCostKeyByBufferKey($items, $payload['buffer_key'] ?? null) ?? $resolvedKey;
        }

        if ($resolvedKey === null || ! array_key_exists($resolvedKey, $items)) {
            $resolvedKey = (string) Str::uuid();
        }

        $items[$resolvedKey] = array_merge($items[$resolvedKey] ?? [], $payload);

        $component->rawState($items);
        $component->callAfterStateUpdated();
        $component->partiallyRender();

        $livewire = $component->getLivewire();

        if ($livewire && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('filament::line-item-modal-closed');
        }

        return $resolvedKey;
    }

    protected static function removeAdditionalCostState(Repeater $component, ?string $itemKey): void
    {
        if ($itemKey === null) {
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

        $livewire = $component->getLivewire();

        if ($livewire && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch('filament::line-item-modal-closed');
        }
    }

    protected static function locateItemKeyByBufferKey(array $items, ?string $bufferKey): ?string
    {
        if (! $bufferKey) {
            return null;
        }

        foreach ($items as $key => $item) {
            if (($item['buffer_key'] ?? null) === $bufferKey) {
                return (string) $key;
            }
        }

        return null;
    }

    protected static function locateAdditionalCostKeyByBufferKey(array $items, ?string $bufferKey): ?string
    {
        if (! $bufferKey) {
            return null;
        }

        foreach ($items as $key => $item) {
            if (($item['buffer_key'] ?? null) === $bufferKey) {
                return (string) $key;
            }
        }

        return null;
    }

    protected static function getReceiptItemStateByKey(Repeater $component, ?string $itemKey): ?array
    {
        if (! $itemKey) {
            return null;
        }

        $items = $component->getRawState() ?? [];

        if (array_key_exists($itemKey, $items)) {
            return $items[$itemKey];
        }

        return self::resolveReceiptItemStateFromMixedKey($items, $itemKey);
    }

    protected static function getAdditionalCostStateByKey(Repeater $component, ?string $itemKey): ?array
    {
        if (! $itemKey) {
            return null;
        }

        $items = $component->getRawState() ?? [];

        if (array_key_exists($itemKey, $items)) {
            return $items[$itemKey];
        }

        return self::resolveAdditionalCostStateFromMixedKey($items, $itemKey);
    }

    protected static function resolveReceiptItemStateFromMixedKey(array $items, string $itemKey): ?array
    {
        if (empty($items)) {
            return null;
        }

        $orderedKeys = array_keys($items);

        if (is_numeric($itemKey)) {
            $index = (int) $itemKey;

            if (isset($orderedKeys[$index])) {
                return $items[$orderedKeys[$index]];
            }
        }

        $delimitersPattern = '/[\.\:\|]/';
        $segments = preg_split($delimitersPattern, $itemKey, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! empty($segments)) {
            foreach (array_reverse($segments) as $segment) {
                if ($segment === 'items') {
                    continue;
                }

                if (isset($items[$segment])) {
                    return $items[$segment];
                }

                if (ctype_digit($segment)) {
                    $index = (int) $segment;

                    if (isset($orderedKeys[$index])) {
                        return $items[$orderedKeys[$index]];
                    }
                }
            }
        }

        return null;
    }

    protected static function resolveAdditionalCostStateFromMixedKey(array $items, string $itemKey): ?array
    {
        if (empty($items)) {
            return null;
        }

        $orderedKeys = array_keys($items);

        if (is_numeric($itemKey)) {
            $index = (int) $itemKey;

            if (isset($orderedKeys[$index])) {
                return $items[$orderedKeys[$index]];
            }
        }

        $delimitersPattern = '/[\.\:\|]/';
        $segments = preg_split($delimitersPattern, $itemKey, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! empty($segments)) {
            foreach (array_reverse($segments) as $segment) {
                if ($segment === 'additional_costs') {
                    continue;
                }

                if (isset($items[$segment])) {
                    return $items[$segment];
                }

                if (ctype_digit($segment)) {
                    $index = (int) $segment;

                    if (isset($orderedKeys[$index])) {
                        return $items[$orderedKeys[$index]];
                    }
                }
            }
        }

        return null;
    }

    protected static function convertNewlinesToBreaks(?string $value): HtmlString
    {
        $escaped = e((string) ($value ?? ''));

        return new HtmlString(str_replace(["\r\n", "\r", "\n"], '<br>', $escaped));
    }

    protected static function shouldDisplayQuantityField(mixed $unit): bool
    {
        $normalizedUnit = strtolower((string) ($unit ?? ''));

        if ($normalizedUnit === '') {
            return true;
        }

        return $normalizedUnit !== 'kg';
    }

    protected static function shouldDisplayWeightField(mixed $unit): bool
    {
        $normalizedUnit = strtolower((string) ($unit ?? ''));

        if ($normalizedUnit === '') {
            return true;
        }

        return $normalizedUnit !== 'ekor';
    }

    protected static function formatUnitAwareSummary(mixed $unit, float $quantityValue, float $weightValue): string
    {
        $normalizedUnit = strtolower((string) ($unit ?? ''));
        $quantityLine = self::formatDecimal($quantityValue, 3) . ' Ekor';
        $weightLine = self::formatDecimal($weightValue, 3) . ' Kg';

        return match ($normalizedUnit) {
            'kg' => $weightLine,
            'ekor' => $quantityLine,
            default => trim($quantityLine . PHP_EOL . $weightLine),
        };
    }

    protected static function getReceiptItemStateByBufferKey(Repeater $component, ?string $bufferKey): ?array
    {
        if (! $bufferKey) {
            return null;
        }

        foreach ($component->getRawState() ?? [] as $item) {
            if (($item['buffer_key'] ?? null) === $bufferKey) {
                return $item;
            }
        }

        return null;
    }

    protected static function getAdditionalCostStateByBufferKey(Repeater $component, ?string $bufferKey): ?array
    {
        if (! $bufferKey) {
            return null;
        }

        foreach ($component->getRawState() ?? [] as $item) {
            if (($item['buffer_key'] ?? null) === $bufferKey) {
                return $item;
            }
        }

        return null;
    }

    protected static function ensureReceiptItemBufferKeys(array $items): array
    {
        foreach ($items as $index => $item) {
            if (blank($item['buffer_key'] ?? null)) {
                $items[$index]['buffer_key'] = (string) Str::uuid();
            }
        }

        return $items;
    }

    protected static function ensureAdditionalCostBufferKeys(array $items): array
    {
        foreach ($items as $index => $item) {
            if (blank($item['buffer_key'] ?? null)) {
                $items[$index]['buffer_key'] = (string) Str::uuid();
            }
        }

        return $items;
    }

    protected static function ensureRawStateBufferKeys(?Repeater $component): void
    {
        if (! $component) {
            return;
        }

        $rawState = $component->getRawState() ?? [];
        $needsUpdate = false;

        foreach ($rawState as $key => $item) {
            if (blank($item['buffer_key'] ?? null)) {
                $rawState[$key]['buffer_key'] = (string) Str::uuid();
                $needsUpdate = true;
            }
        }

        if ($needsUpdate) {
            $component->rawState($rawState);
        }
    }

    protected static function ensureAdditionalCostRawStateBufferKeys(?Repeater $component): void
    {
        if (! $component) {
            return;
        }

        $rawState = $component->getRawState() ?? [];
        $needsUpdate = false;

        foreach ($rawState as $key => $item) {
            if (blank($item['buffer_key'] ?? null)) {
                $rawState[$key]['buffer_key'] = (string) Str::uuid();
                $needsUpdate = true;
            }
        }

        if ($needsUpdate) {
            $component->rawState($rawState);
        }
    }

    protected static function storeReceivingItemsMetadata(SchemaSet $set, SchemaGet $get, array $items): void
    {
        $metadata = $get('metadata') ?? [];

        data_set($metadata, 'receiving_items', array_values($items));

        $set('metadata', $metadata);
    }

}
