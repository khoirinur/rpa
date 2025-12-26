<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Schemas;

use App\Models\LiveChickenPurchaseOrder;
use App\Models\Product;
use App\Models\Supplier;
use Closure;
use Throwable;
use Filament\Schemas\Components\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Components\Utilities\Set as SchemaSet;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\RawJs;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class LiveChickenPurchaseOrderForm
{
    protected static array $productNameCache = [];
    protected static ?array $liveBirdOptionCache = null;
    protected static array $supplierAddressCache = [];
    protected static array $supplierDefaultWarehouseCache = [];

    protected static function defaultLineItemState(): array
    {
        return [
            'product_id' => null,
            'item_code' => null,
            'item_name' => null,
            'quantity' => 0,
            'unit' => 'kg',
            'unit_price' => 0,
            'discount_type' => LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT,
            'discount_value' => 0,
            'apply_tax' => false,
            'notes' => null,
            '__draft' => false,
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        $paymentTermOptions = [
            'manual' => 'Manual',
            'cod' => 'C.O.D (Cash On Delivery)',
            'net_7' => 'Net 7',
            'net_15' => 'Net 15',
            'net_30' => 'Net 30',
            'net_45' => 'Net 45',
            'net_60' => 'Net 60',
        ];

        $taxDppOptions = [
            '100' => 'DPP 100%',
            '11/12' => 'DPP 11/12',
            '11/12-10' => 'DPP 11/12 10%',
            '40' => 'DPP 40%',
            '30' => 'DPP 30%',
            '20' => 'DPP 20%',
            '10' => 'DPP 10%',
        ];

        $taxRateOptions = [
            '0.00' => '0%',
            '10.00' => '10%',
            '11.00' => '11%',
            '12.00' => '12%',
        ];

        $headerSection = Section::make('Header PO Ayam Hidup')
            ->schema([
                        TextInput::make('po_number')
                            ->label('No. PO')
                            ->maxLength(30)
                            ->unique(table: LiveChickenPurchaseOrder::class, column: 'po_number', ignoreRecord: true)
                            ->helperText('Nomor otomatis dibuat saat simpan dan tetap bisa diperbarui manual.'),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload(10)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?int $state, SchemaSet $set): void {
                                if (! $state) {
                                    return;
                                }

                                if (! array_key_exists($state, self::$supplierAddressCache)) {
                                    $supplier = Supplier::query()
                                        ->whereKey($state)
                                        ->first(['id', 'address_line', 'default_warehouse_id']);

                                    self::$supplierAddressCache[$state] = $supplier?->address_line ?? '';
                                    self::$supplierDefaultWarehouseCache[$state] = $supplier?->default_warehouse_id ?? null;
                                }

                                $set('shipping_address', self::$supplierAddressCache[$state]);
                                $set('destination_warehouse_id', self::$supplierDefaultWarehouseCache[$state]);
                            }),
                        Textarea::make('shipping_address')
                            ->label('Alamat Kirim')
                            ->rows(2)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Keterangan (Opsional)')
                            ->rows(2),
                        DatePicker::make('order_date')
                            ->label('Tanggal PO')
                            ->default(today())
                            ->required(),
                        DatePicker::make('delivery_date')
                            ->label('Tanggal Kirim')
                            ->native(false),
                        Select::make('destination_warehouse_id')
                            ->label('Gudang Tujuan')
                            ->relationship('destinationWarehouse', 'name')
                            ->required()
                            ->searchable()
                            ->native(false),
                        Select::make('status')
                            ->label('Status')
                            ->options(LiveChickenPurchaseOrder::statusOptions())
                            ->default(LiveChickenPurchaseOrder::STATUS_DRAFT)
                            ->native(false)
                            ->required(),
            ])
            ->columns(4)
            ->columnSpanFull();

        $paymentSection = Section::make('Pembayaran & Pajak')
            ->schema([
                        Select::make('payment_term')
                            ->label('Syarat Pembayaran')
                            ->options($paymentTermOptions)
                            ->default('cod')
                            ->native(false)
                            ->required(),
                        TextInput::make('payment_term_description')
                            ->label('Catatan Syarat Pembayaran')
                            ->maxLength(120),
                        Toggle::make('is_tax_inclusive')
                            ->label('Harga Termasuk Pajak')
                            ->inline(false)
                            ->live()
                            ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                                self::syncLineItemSummaries($set, $get, $get('line_items') ?? []);
                            }),
                        Select::make('tax_dpp_type')
                            ->label('Jenis DPP')
                            ->options($taxDppOptions)
                            ->default('100')
                            ->native(false)
                            ->required(),
                        Select::make('tax_rate')
                            ->label('Tarif Pajak')
                            ->options($taxRateOptions)
                            ->default('11.00')
                            ->native(false)
                            ->required()
                            ->live()
                               ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                                   self::syncLineItemSummaries($set, $get, $get('line_items') ?? []);
                               }),
                        Select::make('global_discount_type')
                            ->label('Tipe Diskon Global')
                            ->options(LiveChickenPurchaseOrder::discountTypeOptions())
                            ->default(LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT)
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                                self::syncLineItemSummaries($set, $get, $get('line_items') ?? []);
                            }),
                        TextInput::make('global_discount_value')
                            ->label('Nilai Diskon Global')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('Rp')
                            ->dehydrateStateUsing(fn ($state): float => self::sanitizeMoneyValue($state ?? 0))
                            ->live()
                            ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get): void {
                                self::syncLineItemSummaries($set, $get, $get('line_items') ?? []);
                            }),
            ])
            ->columns(4)
            ->columnSpanFull();

        $lineItemsSection = Section::make('Rincian Barang')
            ->schema([
                        Hidden::make('pending_line_item_payload'),
                        Select::make('line_item_search')
                            ->label('Cari & Tambah Barang')
                            ->placeholder('Ketik kode atau nama produk')
                            ->native(false)
                            ->searchable()
                            ->reactive()
                            ->live()
                            ->preload()
                            ->options(fn () => self::getAllLiveBirdProductOptions())
                            ->dehydrated(false)
                            ->disabled(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                            ->getOptionLabelUsing(fn (?int $value): ?string => self::getLiveBirdProductLabel($value))
                            ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get, Select $component): void {
                                if (! $state) {
                                    return;
                                }

                                $payload = self::buildPendingLineItemPayloadFromProduct($state);

                                Log::debug('[PO][debug] product selected, opening pending modal', [
                                    'productId' => $state,
                                    'itemName' => $payload['item_name'] ?? null,
                                ]);

                                $set('line_item_search', null);

                                $lineItemsComponent = self::resolveLineItemsComponentFrom($component);

                                if ($lineItemsComponent && self::triggerPendingLineItemModal($payload, $lineItemsComponent)) {
                                    return;
                                }

                                Log::debug('[PO][debug] pending modal deferred until repeater ready', [
                                    'productId' => $state,
                                    'hasLineItemsComponent' => (bool) $lineItemsComponent,
                                ]);

                                $set('pending_line_item_payload', $payload);
                            })
                            ->columnSpanFull(),
                        Placeholder::make('line_item_gate_notice')
                            ->hiddenLabel()
                            ->content('Pilih supplier terlebih dahulu untuk mengaktifkan pencarian barang.')
                            ->visible(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'text-sm font-medium text-danger-600']),
                        Repeater::make('line_items')
                            ->label('Tabel List Barang')
                            ->schema(self::lineItemTableSchema())
                            ->table(self::lineItemTableColumns())
                            ->default([])
                            ->columns(12)
                            ->columnSpanFull()
                            ->cloneable(true)
                            ->deletable(true)
                            ->addable(false)
                            ->reorderable()
                            ->extraItemActions([
                                self::makeEditLineItemAction(),
                            ])
                            ->extraAttributes(['data-row-click-action' => 'edit_line_item'])
                            ->afterStateUpdated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                                self::syncLineItemSummaries($set, $get, $state ?? []);
                            })
                            ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                                self::syncLineItemSummaries($set, $get, $state ?? []);
                                self::processPendingLineItemPayload($set, $get, $component);
                            })
                            ->itemLabel(fn (array $state): string => $state['item_name'] ?? 'Item Live Bird')
                            ->helperText('Klik baris untuk mengubah detail melalui modal.'),
            ])
            ->columnSpanFull();

        $summarySection = Section::make('Ringkasan Kuantitas & Catatan')
            ->schema([
                        TextInput::make('total_quantity_ea')
                            ->label('Total Ekor')
                            ->readOnly()
                            ->default('0'),
                        TextInput::make('total_weight_kg')
                            ->label('Total Berat (Kg)')
                            ->readOnly()
                            ->default('0'),
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->readOnly()
                            ->default(0)
                            ->prefix('Rp'),
                        TextInput::make('discount_total')
                            ->label('Total Diskon')
                            ->readOnly()
                            ->default(0)
                            ->prefix('Rp'),
                        TextInput::make('tax_total')
                            ->label('Total Pajak')
                            ->readOnly()
                            ->default(0)
                            ->prefix('Rp'),
                        TextInput::make('grand_total')
                            ->label('Total Akhir')
                            ->readOnly()
                            ->default(0)
                            ->prefix('Rp'),
                        
                        ])
                        ->columns(3)
                        ->columnSpanFull();

        return $schema
            ->components([
                $headerSection,
                Tabs::make('live_chicken_po_form_tabs')
                    ->tabs([
                        Tab::make('Detail PO')
                            ->schema([
                                $lineItemsSection,
                            ]),
                        Tab::make('Pembayaran & Pajak')
                            ->schema([
                                $paymentSection,
                            ]),
                    ])
                    ->columnSpanFull(),
                $summarySection,
            ]);
    }

    protected static function lineItemTableSchema(): array
    {
        return [
            Hidden::make('product_id'),
            Hidden::make('item_code'),
            Hidden::make('item_name'),
            Hidden::make('quantity'),
            Hidden::make('unit'),
            Hidden::make('unit_price'),
            Hidden::make('discount_type'),
            Hidden::make('discount_value'),
            Hidden::make('apply_tax'),
            Hidden::make('notes'),
            Placeholder::make('table_item_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): string {
                    $label = $get('item_name') ?: 'Item belum diberi nama';
                    $code = $get('item_code');
                    $notes = $get('notes');

                    $parts = [$label];

                    if ($code) {
                        $parts[] = sprintf('[%s]', $code);
                    }

                    if ($notes) {
                        $parts[] = $notes;
                    }

                    return implode(PHP_EOL, array_filter($parts));
                })
                ->color('primary')
                ->extraAttributes([
                    'class' => 'whitespace-pre-line leading-tight text-sm font-medium',
                ]),
            Placeholder::make('table_quantity')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatQuantityValue(self::sanitizeMoneyValue($get('quantity'))))
                ->extraAttributes(['class' => 'text-right tabular-nums text-sm text-gray-700']),
            Placeholder::make('table_unit')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => strtoupper((string) ($get('unit') ?? 'N/A')))
                ->extraAttributes(['class' => 'text-sm text-gray-700 uppercase']),
            Placeholder::make('table_unit_price')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrency(self::sanitizeMoneyValue($get('unit_price'))))
                ->extraAttributes(['class' => 'text-right tabular-nums text-sm text-gray-700']),
            Placeholder::make('table_discount')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): string {
                    $value = self::sanitizeMoneyValue($get('discount_value'));
                    $type = $get('discount_type') ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT;

                    if ($value <= 0) {
                        return 'N/A';
                    }

                    if ($type === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE) {
                        return number_format(min($value, 100), 2, ',', '.') . '%';
                    }

                    return self::formatCurrency($value);
                })
                ->extraAttributes(['class' => 'text-right tabular-nums text-sm text-gray-700']),
            Placeholder::make('table_tax')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => $get('apply_tax') ? 'YA' : 'Non PPN')
                ->extraAttributes(['class' => 'text-sm text-gray-700 text-center']),
            Placeholder::make('table_line_total')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrency(self::calculateLineTotal($get)))
                ->extraAttributes(['class' => 'text-right font-semibold tabular-nums text-sm text-gray-900']),
        ];
    }

    protected static function lineItemTableColumns(): array
    {
        return [
            TableColumn::make('Barang')->width('28rem'),
            TableColumn::make('Qty')->width('7rem'),
            TableColumn::make('Satuan')->width('6rem'),
            TableColumn::make('@Harga')->width('10rem'),
            TableColumn::make('Diskon')->width('8rem'),
            TableColumn::make('PPN')->width('8rem'),
            TableColumn::make('Total')->width('10rem'),
        ];
    }

    protected static function lineItemFields(): array
    {
        return [
            Hidden::make('product_id'),
            TextInput::make('item_code')
                ->label('Kode #')
                ->inlineLabel()
                ->maxLength(30)
                ->readOnly()
                ->dehydrated()
                ->columnSpanFull(),
            TextInput::make('item_name')
                ->label('Nama Barang')
                ->inlineLabel()
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),
            TextInput::make('quantity')
                ->label('Qty')
                ->inlineLabel()
                ->type('text')
                ->required()
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                    if (self::sanitizeMoneyValue($value) < 0.01) {
                        $fail('Qty minimal 0,01.');
                    }
                })
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS
                ))
                ->stripCharacters(['.', ','])
                ->live()
                ->columnSpanFull(),
            Select::make('unit')
                ->label('Satuan')
                ->inlineLabel()
                ->options([
                    'kg' => 'Kg',
                    'ekor' => 'Ekor',
                ])
                ->required()
                ->native(false)
                ->columnSpanFull(),
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
JS
                ))
                ->stripCharacters(['.', ','])
                ->live()
                ->columnSpanFull(),
            ToggleButtons::make('discount_type')
                ->label('Tipe Diskon')
                ->inlineLabel()
                ->options(LiveChickenPurchaseOrder::discountTypeOptions())
                ->default(LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT)
                ->inline()
                ->grouped()
                ->live()
                ->columnSpanFull(),
            TextInput::make('discount_value')
                ->label('Nilai Diskon')
                ->inlineLabel()
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(fn (SchemaGet $get): ?int => $get('discount_type') === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE ? 100 : null)
                ->rule(fn (SchemaGet $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                    if ($get('discount_type') !== LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT) {
                        return;
                    }

                    $grossTotal = self::calculateGrossLineTotal($get);

                    if ($grossTotal > 0 && self::sanitizeMoneyValue($value) > $grossTotal) {
                        $fail('Diskon nominal tidak boleh melebihi harga total.');
                    }
                })
                ->helperText(fn (SchemaGet $get): string => $get('discount_type') === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE ? 'Maksimal 100%.' : 'Tidak boleh melebihi harga total.')
                ->live()
                ->columnSpanFull(),
            Checkbox::make('apply_tax')
                ->label('PPN')
                ->inlineLabel()
                ->default(false)
                ->columnSpanFull(),
            Placeholder::make('computed_total')
                ->label('Total Harga')
                ->inlineLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrency(self::calculateLineTotal($get)))
                ->columnSpanFull(),
            Textarea::make('notes')
                ->label('Catatan Item')
                ->inlineLabel()
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    protected static function makeEditLineItemAction(): Action
    {
        return Action::make('edit_line_item')
            ->label('Ubah Barang')
            ->modalHeading('Detail Barang')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('5xl')
            ->schema(self::lineItemFields())
            ->extraAttributes(['data-row-trigger-only' => true])
            ->mountUsing(function (Schema $schema, array $arguments, Repeater $component): void {
                if (! empty($arguments['pending']) && is_array($arguments['payload'] ?? null)) {
                    Log::debug('[PO][debug] mounting pending line item modal', [
                        'productId' => $arguments['payload']['product_id'] ?? null,
                    ]);

                    $schema->fill($arguments['payload']);

                    return;
                }

                $itemKey = $arguments['item'] ?? null;
                $state = self::getLineItemStateByKey($component, $itemKey) ?? self::defaultLineItemState();

                Log::debug('[PO][debug] mounting line item modal', [
                    'itemKey' => $itemKey,
                    'isExisting' => filled($itemKey),
                ]);

                $schema->fill($state);
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $itemKey = $arguments['item'] ?? null;
                $isPending = (bool) ($arguments['pending'] ?? false);

                if ($arguments['delete_line_item'] ?? false) {
                    Log::debug('[PO][debug] delete_line_item clicked', [
                        'itemKey' => $itemKey,
                    ]);
                    self::removeLineItemState($component, $itemKey);

                    return;
                }

                if ($isPending) {
                    Log::debug('[PO][debug] pending line item save', [
                        'productId' => $data['product_id'] ?? null,
                    ]);

                    $data['__draft'] = false;
                    self::upsertLineItemState($component, self::prepareLineItemPayload($data));

                    return;
                }

                Log::debug('[PO][debug] line_item_save clicked', [
                    'itemKey' => $itemKey,
                    'isExisting' => filled($itemKey),
                ]);

                self::upsertLineItemState($component, self::prepareLineItemPayload($data), $itemKey);
            })
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('delete_line_item', arguments: ['delete_line_item' => true])
                    ->label('Hapus')
                    ->color('danger')
                    ->requiresConfirmation(),
            ]);
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

        Log::debug('[PO][debug] pending payload detected, triggering modal', [
            'productId' => $payload['product_id'] ?? null,
        ]);

        if (! self::triggerPendingLineItemModal($payload, $component)) {
            Log::warning('[PO][debug] pending modal trigger failed, payload restored', [
                'productId' => $payload['product_id'] ?? null,
            ]);

            $set('pending_line_item_payload', $payload);
        }
    }

    protected static function buildPendingLineItemPayloadFromProduct(int $productId): ?array
    {
        $data = self::defaultLineItemState();
        $data['product_id'] = $productId;

        $details = self::getLiveBirdProductDetails($productId);

        if ($details) {
            $data['item_name'] = $details['name'] ?? null;
            $data['item_code'] = $details['code'] ?? null;
            $data['unit'] = self::resolveProductUnitCode($details['unit_id'] ?? null);
        } else {
            Log::warning('[PO][debug] live bird product details missing', [
                'productId' => $productId,
            ]);
        }

        $data['__draft'] = true;

        return $data;
    }

    protected static function resolveLineItemsComponentFrom(Component $context): ?Repeater
    {
        $livewire = $context->getLivewire();

        if (! $livewire || ! method_exists($livewire, 'getSchemaComponent')) {
            return null;
        }

        $key = $context->resolveRelativeKey('line_items');

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
            Log::warning('[PO][debug] pending modal guard failed', [
                'hasSchemaComponentKey' => (bool) $schemaComponentKey,
                'hasLivewire' => (bool) $livewire,
            ]);
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

                Log::debug('[PO][debug] pending modal opened via mountAction', [
                    'productId' => $payload['product_id'] ?? null,
                ]);

                return true;
            } catch (Throwable $exception) {
                Log::error('[PO][debug] mountAction failed for pending modal', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (method_exists($livewire, 'dispatch') && method_exists($livewire, 'getId')) {
            $livewire->dispatch('filament::line-item-modal-requested', [
                'livewireId' => $livewire->getId(),
                'action' => 'edit_line_item',
                'arguments' => $arguments,
                'context' => ['schemaComponent' => $schemaComponentKey],
            ]);

            Log::debug('[PO][debug] pending modal dispatched via browser event', [
                'productId' => $payload['product_id'] ?? null,
            ]);

            return true;
        }

        Log::warning('[PO][debug] pending modal trigger failed', [
            'productId' => $payload['product_id'] ?? null,
        ]);

        return false;
    }

    protected static function prepareLineItemPayload(array $data): array
    {
        $details = self::getLiveBirdProductDetails($data['product_id'] ?? null);

        return [
            'product_id' => $data['product_id'] ?? $details['id'] ?? null,
            'item_code' => $data['item_code'] ?? $details['code'] ?? null,
            'item_name' => $data['item_name'] ?? $details['name'] ?? null,
            'quantity' => self::sanitizeMoneyValue($data['quantity'] ?? 0),
            'unit' => $data['unit'] ?? 'ekor',
            'unit_price' => self::sanitizeMoneyValue($data['unit_price'] ?? 0),
            'discount_type' => $data['discount_type'] ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT,
            'discount_value' => self::sanitizeMoneyValue($data['discount_value'] ?? 0),
            'apply_tax' => (bool) ($data['apply_tax'] ?? true),
            'notes' => $data['notes'] ?? null,
            '__draft' => (bool) ($data['__draft'] ?? false),
        ];
    }

    protected static function syncLineItemSummaries(SchemaSet $set, SchemaGet $get, array $lineItems): void
    {
        $taxRatePercent = (float) ($get('tax_rate') ?? 0);
        $isTaxInclusive = (bool) ($get('is_tax_inclusive') ?? false);
        $globalDiscountType = $get('global_discount_type') ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT;
        $globalDiscountValue = self::sanitizeMoneyValue($get('global_discount_value') ?? 0);
        $summary = self::calculateLineItemSummaries(
            $lineItems,
            $taxRatePercent,
            $isTaxInclusive,
            $globalDiscountType,
            $globalDiscountValue
        );

        $set('total_quantity_ea', (string) $summary['total_quantity_ea']);
        $set('total_weight_kg', (string) $summary['total_weight_kg']);
        $set('subtotal', $summary['subtotal']);
        $set('discount_total', $summary['discount_total']);
        $set('tax_total', $summary['tax_total']);
        $set('grand_total', $summary['grand_total']);
    }

    protected static function calculateLineItemSummaries(
        array $lineItems,
        float $taxRatePercent,
        bool $isTaxInclusive = false,
        string $globalDiscountType = LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT,
        float $globalDiscountValue = 0.0
    ): array
    {
        $totals = [
            'total_quantity_ea' => 0.0,
            'total_weight_kg' => 0.0,
            'gross_subtotal' => 0.0,
            'discount_total' => 0.0,
            'subtotal' => 0.0,
            'taxable_subtotal' => 0.0,
        ];

        foreach ($lineItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = self::sanitizeMoneyValue($item['quantity'] ?? 0);
            $unit = strtolower((string) ($item['unit'] ?? ''));

            if ($unit === 'ekor') {
                $totals['total_quantity_ea'] += $quantity;
            }

            if ($unit === 'kg') {
                $totals['total_weight_kg'] += $quantity;
            }

            $unitPrice = self::sanitizeMoneyValue($item['unit_price'] ?? 0);
            $grossLine = $quantity * $unitPrice;

            $discountType = $item['discount_type'] ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT;
            $discountValue = self::sanitizeMoneyValue($item['discount_value'] ?? 0);
            $discountAmount = self::resolveLineDiscountAmount($grossLine, $discountType, $discountValue);

            $netLine = max($grossLine - $discountAmount, 0);

            $totals['gross_subtotal'] += $grossLine;
            $totals['discount_total'] += $discountAmount;
            $totals['subtotal'] += $netLine;

            if (! empty($item['apply_tax'])) {
                $totals['taxable_subtotal'] += $netLine;
            }
        }

        $globalDiscountAmount = self::resolveLineDiscountAmount($totals['subtotal'], $globalDiscountType, $globalDiscountValue);
        $netSubtotal = max($totals['subtotal'] - $globalDiscountAmount, 0);

        $taxableSubtotal = $totals['taxable_subtotal'];

        if ($totals['subtotal'] > 0 && $taxableSubtotal > 0 && $globalDiscountAmount > 0) {
            $taxableShare = min($taxableSubtotal / $totals['subtotal'], 1);
            $taxableSubtotal = max($taxableSubtotal - ($globalDiscountAmount * $taxableShare), 0);
        }

        $taxRate = max($taxRatePercent, 0) / 100;
        $taxTotal = $isTaxInclusive ? 0.0 : $taxableSubtotal * $taxRate;
        $grandTotal = $netSubtotal + $taxTotal;

        return [
            'total_quantity_ea' => round($totals['total_quantity_ea'], 2),
            'total_weight_kg' => round($totals['total_weight_kg'], 2),
            'subtotal' => round($netSubtotal, 2),
            'discount_total' => round($totals['discount_total'] + $globalDiscountAmount, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }

    protected static function resolveLineDiscountAmount(float $grossTotal, string $discountType, float $discountValue): float
    {
        if ($grossTotal <= 0 || $discountValue <= 0) {
            return 0.0;
        }

        if ($discountType === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE) {
            return min($discountValue, 100) / 100 * $grossTotal;
        }

        return min($discountValue, $grossTotal);
    }

    protected static function searchLiveBirdProducts(?string $search = null): array
    {
        return Product::query()
            ->whereHas('productCategory', fn (Builder $query) => $query->where('code', 'LB'))
            ->where('is_active', true)
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => sprintf('%s · %s', $product->code, $product->name),
            ])
            ->toArray();
    }

    protected static function getAllLiveBirdProductOptions(): array
    {
        if (self::$liveBirdOptionCache === null) {
            self::$liveBirdOptionCache = self::searchLiveBirdProducts();
        }

        return self::$liveBirdOptionCache;
    }

    protected static function getLiveBirdProductLabel(?int $productId): ?string
    {
        $details = $productId ? self::getLiveBirdProductDetails($productId) : null;

        return $details ? sprintf('%s · %s', $details['code'], $details['name']) : null;
    }

    protected static function getLiveBirdProductDetails(?int $productId): ?array
    {
        if (! $productId) {
            return null;
        }

        if (! array_key_exists($productId, self::$productNameCache)) {
            self::$productNameCache[$productId] = Product::query()
                ->whereKey($productId)
                ->whereHas('productCategory', fn (Builder $query) => $query->where('code', 'LB'))
                ->first(['id', 'name', 'code', 'unit_id'])
                ?->toArray();
        }

        return self::$productNameCache[$productId];
    }

    protected static function resolveProductUnitCode(?int $unitId): string
    {
        return match ($unitId) {
            2 => 'ekor',
            1 => 'kg',
            default => 'kg',
        };
    }

    protected static function calculateLineTotal(SchemaGet $get): float
    {
        $grossTotal = self::calculateGrossLineTotal($get);
        $discountValue = self::sanitizeMoneyValue($get('discount_value'));
        $discountType = $get('discount_type') ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT;

        $discountAmount = 0;

        if ($discountType === LiveChickenPurchaseOrder::DISCOUNT_TYPE_PERCENTAGE) {
            $discountAmount = min($discountValue, 100) / 100 * $grossTotal;
        } else {
            $discountAmount = min($discountValue, $grossTotal);
        }

        return max($grossTotal - $discountAmount, 0);
    }

    protected static function formatCurrency(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    protected static function formatQuantityValue(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }

    protected static function calculateGrossLineTotal(SchemaGet $get): float
    {
        $rawQuantity = $get('quantity');
        $rawUnitPrice = $get('unit_price');
        $quantity = self::sanitizeMoneyValue($rawQuantity);
        $unitPrice = self::sanitizeMoneyValue($rawUnitPrice);
        return $quantity * $unitPrice;
    }

    protected static function sanitizeMoneyValue(mixed $value): float
    {
        if (blank($value)) {
            return 0;
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

        $normalized = str_replace('.', '', $numericString);
        $normalized = str_replace(',', '.', $normalized);

        return $sign * (float) $normalized;
    }
}
