<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Schemas;

use App\Models\LiveChickenPurchaseOrder;
use App\Models\Product;
use App\Models\Supplier;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
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
            'quantity' => 1,
            'unit' => 'ekor',
            'unit_price' => 0,
            'discount_type' => LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT,
            'discount_value' => 0,
            'apply_tax' => true,
            'notes' => null,
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
                            ->required()
                            ->helperText('Nomor otomatis dibuat saat simpan dan tetap bisa diperbarui manual.'),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
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
                            ->inline(false),
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
                            ->required(),
                        Select::make('global_discount_type')
                            ->label('Tipe Diskon Global')
                            ->options(LiveChickenPurchaseOrder::discountTypeOptions())
                            ->default(LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT)
                            ->native(false),
                        TextInput::make('global_discount_value')
                            ->label('Nilai Diskon Global')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp'),
            ])
            ->columns(4)
            ->columnSpanFull();

        $lineItemsSection = Section::make('Rincian Barang')
            ->schema([
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
                            ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                                if (! $state) {
                                    return;
                                }

                                self::appendLineItemFromProduct($state, $set, $get);
                                $set('line_item_search', null);
                            })
                            ->columnSpanFull(),
                        Placeholder::make('line_item_gate_notice')
                            ->content('Pilih supplier terlebih dahulu untuk mengaktifkan pencarian barang.')
                            ->visible(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'text-sm font-medium text-danger-600']),
                        Repeater::make('line_items')
                            ->label('Daftar Item (Editable)')
                            ->schema(self::lineItemFields())
                            ->default([])
                            ->columns(12)
                            ->columnSpanFull()
                            ->cloneable()
                            ->reorderable()
                            ->createItemButtonLabel('Tambah manual')
                            ->itemLabel(fn (array $state): string => $state['item_name'] ?? 'Item Live Bird')
                            ->helperText('Minimal satu item tersimpan dengan qty & harga valid.'),
            ])
            ->columnSpanFull();

        $summarySection = Section::make('Ringkasan Kuantitas & Catatan')
            ->schema([
                        TextEntry::make('total_quantity_ea')
                            ->label('Total Ekor')
                            ->state(fn (SchemaGet $get): string => (string) collect($get('line_items') ?? [])
                                ->sum(fn (array $item): float => (float) ($item['unit'] === 'ekor' ? $item['quantity'] ?? 0 : 0))),
                        TextEntry::make('total_weight_kg')
                            ->label('Total Berat (Kg)')
                            ->state(fn (SchemaGet $get): string => (string) collect($get('line_items') ?? [])
                                ->sum(fn (array $item): float => (float) ($item['unit'] === 'kg' ? $item['quantity'] ?? 0 : 0))),
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('Rp'),
                        TextInput::make('discount_total')
                            ->label('Total Diskon')
                            ->numeric()
                            ->prefix('Rp'),
                        TextInput::make('tax_total')
                            ->label('Total Pajak')
                            ->numeric()
                            ->prefix('Rp'),
                        TextInput::make('grand_total')
                            ->label('Total Akhir')
                            ->numeric()
                            ->prefix('Rp'),
                        
                        ])
                        ->columns(3)
                        ->columnSpanFull();

        return $schema
            ->components([
                Tabs::make('live_chicken_po_form_tabs')
                    ->tabs([
                        Tab::make('Detail PO')
                            ->schema([
                                $headerSection,
                                $lineItemsSection,
                                $summarySection,
                            ]),
                        Tab::make('Pembayaran & Pajak')
                            ->schema([
                                $paymentSection,
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function lineItemFields(): array
    {
        return [
            Hidden::make('product_id'),
            TextInput::make('item_name')
                ->label('Nama Barang')
                ->required()
                ->maxLength(120)
                ->columnSpan(5),
            TextInput::make('item_code')
                ->label('Kode #')
                ->maxLength(30)
                ->readOnly()
                ->dehydrated()
                ->columnSpan(2),
            TextInput::make('quantity')
                ->label('Qty')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->live()
                ->columnSpan(2),
            Select::make('unit')
                ->label('Satuan')
                ->options([
                    'ekor' => 'Ekor',
                    'kg' => 'Kg',
                ])
                ->required()
                ->native(false)
                ->columnSpan(3),
            TextInput::make('unit_price')
                ->label('@Harga')
                ->numeric()
                ->type('text')
                ->required()
                ->minValue(0)
                ->prefix('Rp')
                ->mask(RawJs::make('$money($input, ",", ".", 0)'))
                ->stripCharacters(['.', ','])
                ->live()
                ->columnSpan(3),
            ToggleButtons::make('discount_type')
                ->label('Tipe Diskon')
                ->options(LiveChickenPurchaseOrder::discountTypeOptions())
                ->default(LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT)
                ->inline()
                ->grouped()
                ->live()
                ->columnSpan(2),
            TextInput::make('discount_value')
                ->label('Nilai Diskon')
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
                ->columnSpan(2),
            Checkbox::make('apply_tax')
                ->label('PPN 11%')
                ->default(true)
                ->columnSpan(2),
            Placeholder::make('computed_total')
                ->label('Total Harga')
                ->content(fn (SchemaGet $get): string => self::formatCurrency(self::calculateLineTotal($get)))
                ->columnSpan(2),
            Textarea::make('notes')
                ->label('Catatan Item')
                ->rows(2)
                ->columnSpan(12),
        ];
    }

    protected static function appendLineItem(array $data, SchemaSet $set, SchemaGet $get): void
    {
        $items = $get('line_items');

        if (! is_array($items)) {
            $items = [];
        }

        $itemKey = (string) Str::uuid();

        $items[$itemKey] = self::prepareLineItemPayload($data);

        $set('line_items', $items);
    }

    protected static function appendLineItemFromProduct(int $productId, SchemaSet $set, SchemaGet $get): void
    {
        $data = self::defaultLineItemState();
        $data['product_id'] = $productId;

        $details = self::getLiveBirdProductDetails($productId);

        if ($details) {
            $data['item_name'] = $details['name'] ?? null;
            $data['item_code'] = $details['code'] ?? null;
        }

        self::appendLineItem($data, $set, $get);
    }

    protected static function prepareLineItemPayload(array $data): array
    {
        $details = self::getLiveBirdProductDetails($data['product_id'] ?? null);

        return [
            'product_id' => $data['product_id'] ?? $details['id'] ?? null,
            'item_code' => $data['item_code'] ?? $details['code'] ?? null,
            'item_name' => $data['item_name'] ?? $details['name'] ?? null,
            'quantity' => (float) ($data['quantity'] ?? 0),
            'unit' => $data['unit'] ?? 'ekor',
            'unit_price' => self::sanitizeMoneyValue($data['unit_price'] ?? 0),
            'discount_type' => $data['discount_type'] ?? LiveChickenPurchaseOrder::DISCOUNT_TYPE_AMOUNT,
            'discount_value' => self::sanitizeMoneyValue($data['discount_value'] ?? 0),
            'apply_tax' => (bool) ($data['apply_tax'] ?? true),
            'notes' => $data['notes'] ?? null,
        ];
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
                ->first(['id', 'name', 'code'])
                ?->toArray();
        }

        return self::$productNameCache[$productId];
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

    protected static function calculateGrossLineTotal(SchemaGet $get): float
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = self::sanitizeMoneyValue($get('unit_price'));

        return $quantity * $unitPrice;
    }

    protected static function sanitizeMoneyValue(mixed $value): float
    {
        if (blank($value)) {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value) ?? '';
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return (float) $normalized;
    }
}
