<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices\Schemas;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\LiveChickenPurchaseOrder;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\Supplier;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use function normalize_item_name;
use function sanitize_decimal;
use function sanitize_positive_decimal;
use function sanitize_rupiah;

class PurchaseInvoiceForm
{
    protected const DEFAULT_UNIT = 'kg';

    protected static array $supplierDefaultWarehouseCache = [];
    protected static array $purchaseOrderCache = [];
    protected static array $goodsReceiptCache = [];
    protected static ?array $unitOptions = null;

    public static function configure(Schema $schema): Schema
    {
        $headerSection = Section::make('Header Faktur Pembelian')
            ->schema([
                Hidden::make('reference_number'),
                TextInput::make('invoice_number')
                    ->label('No. Faktur')
                    ->maxLength(30)
                    ->unique(table: PurchaseInvoice::class, column: 'invoice_number', ignoreRecord: true)
                    ->helperText('Nomor otomatis dibuat saat simpan dan tetap bisa diperbarui manual.'),
                Select::make('reference_type')
                    ->label('Sumber Dokumen')
                    ->options(PurchaseInvoice::referenceTypeOptions())
                    ->default(PurchaseInvoice::REFERENCE_TYPE_PURCHASE_ORDER)
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state, SchemaSet $set): void {
                        if ($state === PurchaseInvoice::REFERENCE_TYPE_PURCHASE_ORDER) {
                            $set('goods_receipt_id', null);
                        }

                        if ($state === PurchaseInvoice::REFERENCE_TYPE_GOODS_RECEIPT) {
                            $set('live_chicken_purchase_order_id', null);
                        }
                    }),
                Select::make('live_chicken_purchase_order_id')
                    ->label('Purchase Order')
                    ->relationship('purchaseOrder', 'po_number', modifyQueryUsing: fn (Builder $query): Builder => $query->latest('order_date'))
                    ->visible(fn (SchemaGet $get): bool => $get('reference_type') === PurchaseInvoice::REFERENCE_TYPE_PURCHASE_ORDER)
                    ->preload(15)
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                        if (! $state) {
                            return;
                        }

                        if ($get('reference_type') !== PurchaseInvoice::REFERENCE_TYPE_PURCHASE_ORDER) {
                            $set('reference_type', PurchaseInvoice::REFERENCE_TYPE_PURCHASE_ORDER);
                        }

                        self::applyPurchaseOrderPayload($state, $set, $get);
                    }),
                Select::make('goods_receipt_id')
                    ->label('Goods Receipt')
                    ->relationship('goodsReceipt', 'receipt_number', modifyQueryUsing: fn (Builder $query): Builder => $query->latest('received_at'))
                    ->visible(fn (SchemaGet $get): bool => $get('reference_type') === PurchaseInvoice::REFERENCE_TYPE_GOODS_RECEIPT)
                    ->preload(15)
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                        if (! $state) {
                            return;
                        }

                        if ($get('reference_type') !== PurchaseInvoice::REFERENCE_TYPE_GOODS_RECEIPT) {
                            $set('reference_type', PurchaseInvoice::REFERENCE_TYPE_GOODS_RECEIPT);
                        }

                        self::applyGoodsReceiptPayload($state, $set, $get);
                    }),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload(15)
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                        if (! $state) {
                            return;
                        }

                        self::applySupplierDefaults($state, $set, $get);
                    }),
                Select::make('destination_warehouse_id')
                    ->label('Gudang Tujuan')
                    ->relationship('destinationWarehouse', 'name')
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                        if (! $state) {
                            return;
                        }

                        self::propagateWarehouseToItems($state, $set, $get);
                    }),
                DatePicker::make('invoice_date')
                    ->label('Tanggal Faktur')
                    ->required()
                    ->default(today())
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (SchemaSet $set, SchemaGet $get): void {
                        self::syncDueDate($set, $get);
                    }),
                DatePicker::make('due_date')
                    ->label('Tanggal Jatuh Tempo')
                    ->required()
                    ->native(false)
                    ->afterStateHydrated(function ($state, SchemaSet $set, SchemaGet $get): void {
                        if (blank($state) && filled($get('invoice_date'))) {
                            self::syncDueDate($set, $get);
                        }
                    }),
                TextInput::make('tax_invoice_number')
                    ->label('No. Faktur Pajak')
                    ->maxLength(40),
                Select::make('status')
                    ->label('Status Faktur')
                    ->options(PurchaseInvoice::statusOptions())
                    ->default(PurchaseInvoice::STATUS_DRAFT)
                    ->native(false),
                Select::make('payment_status')
                    ->label('Status Pembayaran')
                    ->options(PurchaseInvoice::paymentStatusOptions())
                    ->default(PurchaseInvoice::PAYMENT_STATUS_UNPAID)
                    ->native(false),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(4)
            ->columnSpanFull();

        $itemsSection = Section::make('Rincian Barang Faktur')
            ->schema([
                Placeholder::make('items_gate_notice')
                    ->hiddenLabel()
                    ->content('Pilih supplier terlebih dahulu untuk mengaktifkan rincian barang.')
                    ->visible(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'text-sm font-medium text-danger-600']),
                Repeater::make('items')
                    ->label('Daftar Barang')
                    ->relationship('items')
                    ->orderColumn('line_number')
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload(15)
                            ->native(false)
                            ->columnSpan(3),
                        TextInput::make('item_name')
                            ->label('Nama Item')
                            ->required()
                            ->maxLength(180)
                            ->columnSpan(3),
                        Hidden::make('item_code'),
                        Select::make('unit')
                            ->label('Satuan')
                            ->options(fn (): array => self::getUnitOptions())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->default(self::DEFAULT_UNIT)
                            ->columnSpan(3),
                        TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true)
                            ->mask(RawJs::make(<<<'JS'
$decimal($input, 3, ',', '.')
JS))
                            ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get, TextInput $component): void {
                                self::refreshLineItemPercentageDiscount($component);
                            })
                            ->dehydrateStateUsing(fn ($state): float => sanitize_positive_decimal($state ?? 0))
                            ->columnSpan(3),
                        TextInput::make('unit_price')
                            ->label('Harga Satuan')
                            ->prefix('Rp')
                            ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                            ->stripCharacters(['.', ','])
                            ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get, TextInput $component): void {
                                self::refreshLineItemPercentageDiscount($component);
                            })
                            ->columnSpan(3),
                        Select::make('discount_type')
                            ->label('Tipe Diskon')
                            ->options(PurchaseInvoice::discountTypeOptions())
                            ->default(PurchaseInvoice::DISCOUNT_TYPE_AMOUNT)
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, SchemaSet $set): void {
                                if ($state !== PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE) {
                                    $set('discount_percentage', null);
                                }
                            })
                            ->columnSpan(3),
                        TextInput::make('discount_value')
                            ->label('Nilai Diskon')
                            ->prefix('Rp')
                            ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                            ->stripCharacters(['.', ','])
                            ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                            ->default(0)
                            ->columnSpan(3),
                        Hidden::make('discount_percentage')
                            ->default(null)
                            ->dehydrated(false),
                        Toggle::make('apply_tax')
                            ->label('Kenakan Pajak')
                            ->inline(false)
                            ->columnSpan(2),
                        Select::make('tax_rate')
                            ->label('Tarif Pajak')
                            ->options(PurchaseInvoice::taxRateOptions())
                            ->default('11.00')
                            ->native(false)
                            ->columnSpan(2),
                        Select::make('warehouse_id')
                            ->label('Gudang Item')
                            ->relationship('warehouse', 'name')
                            ->native(false)
                            ->preload(15)
                            ->searchable()
                            ->columnSpan(4),
                        Textarea::make('notes')
                            ->label('Catatan Item')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->extraItemActions([
                        Action::make('set_line_discount_percentage')
                            ->label('Set %')
                            ->icon('heroicon-o-calculator')
                            ->color('primary')
                            ->size('sm')
                            ->form([
                                Hidden::make('item_key'),
                                TextInput::make('percentage')
                                    ->label('Persentase Diskon')
                                    ->suffix('%')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->required(),
                            ])
                            ->mountUsing(function (Action $action, array $arguments, Repeater $component): void {
                                $itemKey = $arguments['item'] ?? null;

                                if ($itemKey === null) {
                                    return;
                                }

                                $items = $component->getRawState() ?? [];
                                $item = $items[$itemKey] ?? null;

                                if (! $item) {
                                    return;
                                }

                                $lineBase = self::calculateLineItemBaseAmount($item);

                                $action->formData([
                                    'item_key' => $itemKey,
                                    'percentage' => $item['discount_percentage'] ?? null,
                                ]);

                                $action->modalHeading(sprintf(
                                    'Diskon Persen · %s',
                                    normalize_item_name($item['item_name'] ?? 'Item Faktur') ?? 'Item Faktur',
                                ));

                                $action->modalDescription(sprintf(
                                    'Nilai baris saat ini: Rp %s',
                                    self::formatCurrency($lineBase),
                                ));
                            })
                            ->action(function (array $data, array $arguments, Repeater $component): void {
                                $itemKey = $data['item_key'] ?? ($arguments['item'] ?? null);
                                $percentage = isset($data['percentage']) ? (float) $data['percentage'] : null;

                                if ($percentage === null) {
                                    return;
                                }

                                self::debug('Action submit set_line_discount_percentage', [
                                    'item_key' => $itemKey,
                                    'arg_item' => $arguments['item'] ?? null,
                                    'percentage' => $percentage,
                                ]);

                                self::applyLineItemDiscountPercentage($component, $itemKey, $percentage);
                            }),
                    ])
                    ->columns(12)
                    ->columnSpanFull()
                    ->default([])
                    ->minItems(0)
                    ->reorderable()
                    ->addActionLabel('Tambah Item Faktur')
                    ->disabled(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get): void {
                        $normalizedItems = self::normalizeInvoiceItems($state ?? []);

                        if ($normalizedItems !== ($state ?? [])) {
                            $set('items', $normalizedItems);
                        }

                        self::syncInvoiceTotals($set, $get, $normalizedItems);
                    })
                    ->afterStateUpdated(function (?array $state, SchemaSet $set, SchemaGet $get): void {
                        $normalizedItems = self::normalizeInvoiceItems($state ?? []);

                        if ($normalizedItems !== ($state ?? [])) {
                            $set('items', $normalizedItems);
                        }

                        self::syncInvoiceTotals($set, $get, $normalizedItems);
                    }),
            ])
            ->columnSpanFull();

        $summarySection = Section::make('Ringkasan Nilai Faktur')
            ->schema([
                Hidden::make('line_item_total')->default(0),
                TextInput::make('line_item_total_display')
                    ->label('Jumlah Baris')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('total_quantity_ea')->default(0),
                TextInput::make('total_quantity_ea_display')
                    ->label('Total Ekor')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('total_weight_kg')->default(0),
                TextInput::make('total_weight_kg_display')
                    ->label('Total Berat (Kg)')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('subtotal')->default(0),
                TextInput::make('subtotal_display')
                    ->label('Subtotal')
                    ->prefix('Rp')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('discount_total')->default(0),
                TextInput::make('discount_total_display')
                    ->label('Total Diskon')
                    ->prefix('Rp')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('tax_total')->default(0),
                TextInput::make('tax_total_display')
                    ->label('Total Pajak')
                    ->prefix('Rp')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('additional_cost_total')->default(0),
                TextInput::make('additional_cost_total_display')
                    ->label('Biaya Lainnya')
                    ->prefix('Rp')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('grand_total')->default(0),
                TextInput::make('grand_total_display')
                    ->label('Total Faktur')
                    ->prefix('Rp')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('paid_total')->default(0),
                TextInput::make('paid_total_display')
                    ->label('Total Pembayaran')
                    ->prefix('Rp')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
                Hidden::make('balance_due')->default(0),
                TextInput::make('balance_due_display')
                    ->label('Sisa Bayar')
                    ->prefix('Rp')
                    ->readOnly()
                    ->default('0')
                    ->dehydrated(false),
            ])
            ->columns(3)
            ->columnSpanFull();

        $paymentSection = Section::make('Pembayaran & Pajak')
            ->schema([
                Select::make('payment_term')
                    ->label('Syarat Pembayaran')
                    ->options(PurchaseInvoice::paymentTermOptions())
                    ->default('cod')
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (SchemaSet $set, SchemaGet $get): void {
                        self::syncDueDate($set, $get);
                    }),
                TextInput::make('payment_term_description')
                    ->label('Catatan Syarat Pembayaran')
                    ->maxLength(120),
                Select::make('cash_account_id')
                    ->label('Rekening Kas/Bank')
                    ->relationship('cashAccount', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('type', 'kas_bank')->orderBy('code'))
                    ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record): string => sprintf('%s — %s', $record->code, $record->name))
                    ->searchable()
                    ->preload(15)
                    ->native(false),
                Toggle::make('is_tax_inclusive')
                    ->label('Harga Termasuk Pajak')
                    ->inline(false)
                    ->live()
                    ->afterStateUpdated(function (SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get);
                    }),
                Select::make('tax_dpp_type')
                    ->label('Jenis DPP')
                    ->options(PurchaseInvoice::taxDppOptions())
                    ->default('100')
                    ->native(false),
                Select::make('tax_rate')
                    ->label('Tarif Pajak Default')
                    ->options(PurchaseInvoice::taxRateOptions())
                    ->default('11.00')
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get);
                    }),
                Select::make('global_discount_type')
                    ->label('Tipe Diskon Global')
                    ->options(PurchaseInvoice::discountTypeOptions())
                    ->default(PurchaseInvoice::DISCOUNT_TYPE_AMOUNT)
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get);
                    }),
                TextInput::make('global_discount_value')
                    ->label('Nilai Diskon Global')
                    ->prefix('Rp')
                    ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                    ->stripCharacters(['.', ','])
                    ->default(0)
                    ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get);
                    }),
                Textarea::make('fob_destination')
                    ->label('FOB Tujuan')
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('fob_shipping_point')
                    ->label('FOB Titik Pengiriman')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(4)
            ->columnSpanFull();

        $paymentsSection = Section::make('Pembayaran & Cicilan')
            ->schema([
                Repeater::make('payments')
                    ->label('Riwayat Pembayaran')
                    ->relationship('payments')
                    ->orderColumn('paid_at')
                    ->schema([
                        Select::make('payment_type')
                            ->label('Jenis Pembayaran')
                            ->options(PurchaseInvoicePayment::typeOptions())
                            ->default(PurchaseInvoicePayment::TYPE_DOWN_PAYMENT)
                            ->native(false)
                            ->columnSpan(3),
                        DatePicker::make('paid_at')
                            ->label('Tanggal Bayar')
                            ->native(false)
                            ->default(today())
                            ->columnSpan(3),
                        TextInput::make('amount')
                            ->label('Nominal')
                            ->prefix('Rp')
                            ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                            ->stripCharacters(['.', ','])
                            ->default(0)
                            ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                            ->columnSpan(3),
                        Select::make('account_id')
                            ->label('Akun Kas/Bank')
                            ->relationship('account', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('type', 'kas_bank')->orderBy('code'))
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record): string => sprintf('%s — %s', $record->code, $record->name))
                            ->searchable()
                            ->preload(15)
                            ->native(false)
                            ->columnSpan(3),
                        TextInput::make('payment_method')
                            ->label('Metode')
                            ->maxLength(40)
                            ->columnSpan(3),
                        TextInput::make('reference_number')
                            ->label('Referensi')
                            ->maxLength(60)
                            ->columnSpan(3),
                        Toggle::make('is_manual')
                            ->label('Input Manual')
                            ->inline(false)
                            ->default(true)
                            ->columnSpan(2),
                        FileUpload::make('attachments')
                            ->label('Lampiran')
                            ->directory('purchase-invoices/payments')
                            ->multiple()
                            ->maxFiles(5)
                            ->maxSize(5120)
                            ->downloadable()
                            ->previewable(false)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(12)
                    ->default([])
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get);
                    })
                    ->afterStateUpdated(function (?array $state, SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get);
                    })
                    ->createItemButtonLabel('Tambah Pembayaran'),
            ])
            ->columnSpanFull();

        $costSection = Section::make('Biaya Lainnya & Lampiran')
            ->schema([
                Repeater::make('additional_costs')
                    ->label('Biaya Tambahan')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Biaya')
                            ->maxLength(120)
                            ->required()
                            ->columnSpan(4),
                        Select::make('coa_reference')
                            ->label('COA')
                            ->options(self::getCostAccountOptions())
                            ->searchable()
                            ->native(false)
                            ->columnSpan(4),
                        Toggle::make('allocate')
                            ->label('Alokasikan ke Qty')
                            ->inline(false)
                            ->default(false)
                            ->columnSpan(2),
                        TextInput::make('amount')
                            ->label('Nominal')
                            ->prefix('Rp')
                            ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                            ->stripCharacters(['.', ','])
                            ->default(0)
                            ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                            ->columnSpan(4),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(12)
                    ->default([])
                    ->dehydrated(true)
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get, $get('items') ?? []);
                    })
                    ->afterStateUpdated(function (?array $state, SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get, $get('items') ?? []);
                    }),
                FileUpload::make('attachments')
                    ->label('Lampiran Dokumen')
                    ->directory('purchase-invoices/attachments')
                    ->multiple()
                    ->downloadable()
                    ->previewable(true)
                    ->maxFiles(10)
                    ->maxSize(5120)
                    ->helperText('Format jpg/png/pdf/docx/xlsx, maksimal 5MB per file.')
                    ->columnSpanFull(),
            ])
            ->columnSpanFull();

        return $schema
            ->components([
                $headerSection,
                Tabs::make('purchase_invoice_tabs')
                    ->tabs([
                        Tab::make('Detail Faktur')
                            ->schema([
                                $itemsSection,
                                $summarySection,
                            ]),
                        Tab::make('Pembayaran & Pajak')
                            ->schema([
                                $paymentSection,
                            ]),
                        Tab::make('Pembayaran')
                            ->schema([
                                $paymentsSection,
                            ]),
                        Tab::make('Biaya & Dokumen')
                            ->schema([
                                $costSection,
                            ]),
                    ])
                            ->columnSpanFull(),
                        self::makeLineItemDiscountPercentageAction(),
            ]);
    }

    protected static function getUnitOptions(): array
    {
        if (self::$unitOptions !== null) {
            return self::$unitOptions;
        }

        self::$unitOptions = Unit::query()
            ->active()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (Unit $unit): array => [
                strtolower($unit->code) => sprintf('%s — %s', strtoupper($unit->code), $unit->name),
            ])
            ->all();

        if (empty(self::$unitOptions)) {
            self::$unitOptions = [self::DEFAULT_UNIT => strtoupper(self::DEFAULT_UNIT)];
        }

        return self::$unitOptions;
    }

    protected static function applySupplierDefaults(int $supplierId, SchemaSet $set, SchemaGet $get): void
    {
        if (! array_key_exists($supplierId, self::$supplierDefaultWarehouseCache)) {
            $supplier = Supplier::query()->find($supplierId, ['id', 'default_warehouse_id']);
            self::$supplierDefaultWarehouseCache[$supplierId] = $supplier?->default_warehouse_id;
        }

        $defaultWarehouse = self::$supplierDefaultWarehouseCache[$supplierId];

        if ($defaultWarehouse && blank($get('destination_warehouse_id'))) {
            $set('destination_warehouse_id', $defaultWarehouse);
            self::propagateWarehouseToItems($defaultWarehouse, $set, $get);
        }
    }

    protected static function propagateWarehouseToItems(int $warehouseId, SchemaSet $set, SchemaGet $get): void
    {
        $items = Collection::make($get('items') ?? [])
            ->map(function (array $item) use ($warehouseId): array {
                if (blank($item['warehouse_id'] ?? null)) {
                    $item['warehouse_id'] = $warehouseId;
                }

                return $item;
            })
            ->all();

        $set('items', $items);
        self::syncInvoiceTotals($set, $get, $items);
    }

    protected static function applyPurchaseOrderPayload(int $purchaseOrderId, SchemaSet $set, SchemaGet $get): void
    {
        $payload = self::getPurchaseOrderPayload($purchaseOrderId);

        if (! $payload) {
            return;
        }

        $set('supplier_id', $payload['supplier_id']);
        $set('destination_warehouse_id', $payload['destination_warehouse_id']);
        $set('reference_number', $payload['reference_number']);

        $lineItems = self::mapPurchaseOrderItems($payload['line_items'], $payload['destination_warehouse_id']);

        $set('items', $lineItems);
        self::syncInvoiceTotals($set, $get, $lineItems);
    }

    protected static function applyGoodsReceiptPayload(int $goodsReceiptId, SchemaSet $set, SchemaGet $get): void
    {
        $payload = self::getGoodsReceiptPayload($goodsReceiptId);

        if (! $payload) {
            return;
        }

        $set('supplier_id', $payload['supplier_id']);
        $set('destination_warehouse_id', $payload['destination_warehouse_id']);
        $set('reference_number', $payload['reference_number']);

        $lineItems = self::mapGoodsReceiptItems(
            $payload['items'],
            $payload['destination_warehouse_id'],
            $payload['purchase_order_line_items'] ?? []
        );

        $set('items', $lineItems);
        self::syncInvoiceTotals($set, $get, $lineItems);
    }

    protected static function getPurchaseOrderPayload(int $purchaseOrderId): ?array
    {
        if (! array_key_exists($purchaseOrderId, self::$purchaseOrderCache)) {
            $order = LiveChickenPurchaseOrder::query()
                ->withCasts(['metadata' => 'array'])
                ->find($purchaseOrderId, ['id', 'po_number', 'supplier_id', 'destination_warehouse_id', 'metadata']);

            self::$purchaseOrderCache[$purchaseOrderId] = $order
                ? [
                    'supplier_id' => $order->supplier_id,
                    'destination_warehouse_id' => $order->destination_warehouse_id,
                    'reference_number' => $order->po_number,
                    'line_items' => data_get($order->metadata ?? [], 'line_items', []),
                ]
                : null;
        }

        return self::$purchaseOrderCache[$purchaseOrderId];
    }

    protected static function mapPurchaseOrderItems(array $items, ?int $defaultWarehouseId): array
    {
        return Collection::make($items)
            ->map(function (array $item) use ($defaultWarehouseId): array {
                $unit = strtolower((string) ($item['unit'] ?? self::DEFAULT_UNIT));
                $quantity = sanitize_positive_decimal($item['quantity'] ?? 0, 3);

                if ($unit === 'kg') {
                    $quantity = sanitize_positive_decimal($item['weight_kg'] ?? ($item['quantity_kg'] ?? $quantity), 3);
                }

                return [
                    'product_id' => $item['product_id'] ?? null,
                    'item_code' => $item['item_code'] ?? ($item['code'] ?? null),
                    'item_name' => normalize_item_name($item['item_name'] ?? ($item['name'] ?? 'Item Faktur')),
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'unit_price' => sanitize_decimal($item['unit_price'] ?? 0),
                    'discount_type' => strtolower((string) ($item['discount_type'] ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT)),
                    'discount_value' => sanitize_decimal($item['discount_value'] ?? 0),
                    'apply_tax' => (bool) ($item['apply_tax'] ?? false),
                    'tax_rate' => sanitize_decimal($item['tax_rate'] ?? 11, 2),
                    'warehouse_id' => $defaultWarehouseId,
                    'notes' => $item['notes'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    protected static function getGoodsReceiptPayload(int $goodsReceiptId): ?array
    {
        if (! array_key_exists($goodsReceiptId, self::$goodsReceiptCache)) {
            $receipt = GoodsReceipt::query()
                ->with([
                    'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'goods_receipt_id',
                        'product_id',
                        'warehouse_id',
                        'item_code',
                        'item_name',
                        'unit',
                        'received_quantity',
                        'received_weight_kg',
                        'metadata',
                        ]),
                    'purchaseOrder' => fn ($query) => $query->select('id', 'metadata'),
                ])
                ->find($goodsReceiptId, ['id', 'receipt_number', 'supplier_id', 'destination_warehouse_id', 'live_chicken_purchase_order_id']);

            self::$goodsReceiptCache[$goodsReceiptId] = $receipt
                ? [
                    'supplier_id' => $receipt->supplier_id,
                    'destination_warehouse_id' => $receipt->destination_warehouse_id,
                    'reference_number' => $receipt->receipt_number,
                    'items' => $receipt->items
                        ->map(fn ($item) => [
                            'product_id' => $item->product_id,
                            'warehouse_id' => $item->warehouse_id,
                            'item_code' => $item->item_code,
                            'item_name' => $item->item_name,
                            'unit' => $item->unit,
                            'received_quantity' => $item->received_quantity,
                            'received_weight_kg' => $item->received_weight_kg,
                            'metadata' => $item->metadata ?? [],
                        ])
                        ->all(),
                    'purchase_order_line_items' => data_get($receipt->purchaseOrder?->metadata ?? [], 'line_items', []),
                ]
                : null;
        }

        return self::$goodsReceiptCache[$goodsReceiptId];
    }

    protected static function mapGoodsReceiptItems(array $items, ?int $defaultWarehouseId, array $purchaseOrderItems = []): array
    {
        return Collection::make($items)
            ->map(function (array $item) use ($defaultWarehouseId, $purchaseOrderItems): array {
                $unit = strtolower((string) ($item['unit'] ?? self::DEFAULT_UNIT));
                $quantity = sanitize_positive_decimal($item['received_quantity'] ?? 0, 3);

                if ($unit === 'kg') {
                    $quantity = sanitize_positive_decimal($item['received_weight_kg'] ?? $quantity, 3);
                }

                return [
                    'product_id' => $item['product_id'] ?? null,
                    'item_code' => $item['item_code'] ?? null,
                    'item_name' => normalize_item_name($item['item_name'] ?? 'Item Penerimaan'),
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'unit_price' => self::resolveUnitPriceFromPurchaseOrderItems($item, $purchaseOrderItems),
                    'discount_type' => PurchaseInvoice::DISCOUNT_TYPE_AMOUNT,
                    'discount_value' => 0,
                    'apply_tax' => (bool) data_get($item, 'metadata.apply_tax', false),
                    'tax_rate' => sanitize_decimal(data_get($item, 'metadata.tax_rate', 0), 2),
                    'warehouse_id' => $item['warehouse_id'] ?? $defaultWarehouseId,
                    'notes' => data_get($item, 'metadata.notes'),
                ];
            })
            ->values()
            ->all();
    }

    protected static function resolveUnitPriceFromPurchaseOrderItems(array $receiptItem, array $purchaseOrderItems): float
    {
        $productId = $receiptItem['product_id'] ?? null;
        $itemCode = $receiptItem['item_code'] ?? null;
        $itemName = normalize_item_name($receiptItem['item_name'] ?? null);

        foreach ($purchaseOrderItems as $poItem) {
            $poProductId = $poItem['product_id'] ?? null;
            $poItemCode = $poItem['item_code'] ?? ($poItem['code'] ?? null);
            $poItemName = normalize_item_name($poItem['item_name'] ?? ($poItem['name'] ?? null));

            if ($productId && $poProductId && (int) $productId === (int) $poProductId) {
                return sanitize_decimal($poItem['unit_price'] ?? 0);
            }

            if ($itemCode && $poItemCode && $itemCode === $poItemCode) {
                return sanitize_decimal($poItem['unit_price'] ?? 0);
            }

            if ($itemName && $poItemName && $itemName === $poItemName) {
                return sanitize_decimal($poItem['unit_price'] ?? 0);
            }
        }

        return sanitize_decimal(data_get($receiptItem, 'metadata.unit_price', 0));
    }

    protected static function resolveLineItemSiblingStatePath(Component $component, string $field): ?string
    {
        $statePath = $component->getStatePath();

        if (! $statePath || ! Str::contains($statePath, '.')) {
            return null;
        }

        $parentPath = (string) Str::of($statePath)->beforeLast('.');

        if ($parentPath === '') {
            return null;
        }

        return sprintf('%s.%s', $parentPath, $field);
    }

    protected static function extractRepeaterItemStatePath(?string $statePath): ?string
    {
        if (! $statePath || ! Str::contains($statePath, '.')) {
            return null;
        }

        return (string) Str::of($statePath)->beforeLast('.');
    }

    protected static function openLineItemDiscountPercentageModal(Component $component): void
    {
        self::debug('Attempting to open line item discount modal', [
            'state_path' => $component->getStatePath(),
        ]);

        $itemsComponent = self::resolveInvoiceItemsComponentFrom($component);

        if (! $itemsComponent) {
            self::debug('Items repeater not resolved when opening modal');
            return;
        }

        $itemStatePath = self::extractRepeaterItemStatePath($component->getStatePath());

        if (! $itemStatePath) {
            self::debug('Item state path missing for discount modal');
            return;
        }

        $itemState = self::getInvoiceItemStateByKey($itemsComponent, $itemStatePath);

        if (! $itemState) {
            self::debug('Item state not found for discount modal', [
                'item_key' => $itemStatePath,
            ]);
            return;
        }

        $arguments = [
            'item_key' => $itemStatePath,
            'percentage' => $itemState['discount_percentage'] ?? null,
            'item_name' => $itemState['item_name'] ?? 'Item Faktur',
            'line_base' => self::calculateLineItemBaseAmount($itemState),
        ];

        self::triggerLineItemDiscountPercentageModal($arguments, $itemsComponent);
    }

    protected static function refreshLineItemPercentageDiscount(Component $component): void
    {
        self::debug('Refreshing percentage discount for line item', [
            'state_path' => $component->getStatePath(),
        ]);

        $itemsComponent = self::resolveInvoiceItemsComponentFrom($component);

        if (! $itemsComponent) {
            self::debug('Items repeater not resolved while refreshing percentage discount');
            return;
        }

        $itemStatePath = self::extractRepeaterItemStatePath($component->getStatePath());

        if (! $itemStatePath) {
            self::debug('Item state path missing while refreshing percentage discount');
            return;
        }

        $itemState = self::getInvoiceItemStateByKey($itemsComponent, $itemStatePath);

        if (
            ! $itemState ||
            ($itemState['discount_type'] ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT) !== PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE
        ) {
            self::debug('Skipped refresh because item not in percentage mode', [
                'item_key' => $itemStatePath,
                'discount_type' => $itemState['discount_type'] ?? null,
            ]);
            return;
        }

        $percentage = $itemState['discount_percentage'] ?? null;

        if ($percentage === null) {
            self::debug('Skipped refresh because percentage is null', [
                'item_key' => $itemStatePath,
            ]);
            return;
        }

        self::applyLineItemDiscountPercentage($itemsComponent, $itemStatePath, $percentage, dispatchCloseEvent: false);
    }

    protected static function normalizeInvoiceItems(array $items): array
    {
        if (empty($items)) {
            return $items;
        }

        return Collection::make($items)
            ->mapWithKeys(function (array $item, $key): array {
                if (($item['discount_type'] ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT) !== PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE) {
                    $item['discount_percentage'] = null;

                    return [$key => $item];
                }

                if (array_key_exists('discount_percentage', $item) && $item['discount_percentage'] !== null) {
                    $item['discount_percentage'] = round(max(0, min(100, (float) $item['discount_percentage'])), 4);

                    return [$key => $item];
                }

                $lineBase = self::calculateLineItemBaseAmount($item);

                if ($lineBase > 0) {
                    $discountValue = sanitize_decimal($item['discount_value'] ?? 0);
                    $percentage = round(($discountValue / $lineBase) * 100, 4);
                    $item['discount_percentage'] = max(0, min(100, $percentage));
                } else {
                    $item['discount_percentage'] = null;
                }

                return [$key => $item];
            })
            ->all();
    }

    protected static function calculateLineItemBaseAmount(array $item): float
    {
        $quantity = sanitize_positive_decimal($item['quantity'] ?? 0, 3);
        $unitPrice = sanitize_decimal($item['unit_price'] ?? 0);

        return round($quantity * $unitPrice, 2);
    }

    protected static function resolveInvoiceItemsComponentFrom(Component $context): ?Repeater
    {
        $component = $context->getRootContainer()->getComponent(
            fn ($candidate): bool => $candidate instanceof Repeater
                && $candidate->getStatePath(isAbsolute: false) === 'items',
            withHidden: true,
        );

        self::debug('Resolving invoice items repeater component', [
            'context_state_path' => $context->getStatePath(),
            'resolved' => $component instanceof Repeater,
        ]);

        return $component instanceof Repeater ? $component : null;
    }

    protected static function triggerLineItemDiscountPercentageModal(array $arguments, Repeater $component): bool
    {
        $schemaComponentKey = $component->getKey();
        $livewire = $component->getLivewire();

        if (blank($schemaComponentKey) || ! $livewire) {
            self::debug('Cannot trigger modal because component key or livewire missing', [
                'key' => $schemaComponentKey,
                'has_livewire' => (bool) $livewire,
            ]);
            return false;
        }

        if (method_exists($livewire, 'dispatch') && method_exists($livewire, 'getId')) {
            $livewire->dispatch('filament::line-item-modal-requested', [
                'livewireId' => $livewire->getId(),
                'action' => 'set_line_item_discount_percentage',
                'arguments' => $arguments,
                'context' => ['schemaComponent' => $schemaComponentKey],
            ]);

            self::debug('Modal request dispatched via event', [
                'key' => $schemaComponentKey,
                'arguments' => $arguments,
            ]);

            return true;
        }

        self::debug('Failed to trigger modal: no supported dispatch method found');

        return false;
    }

    protected static function applyLineItemDiscountPercentage(
        Repeater $component,
        ?string $itemKey,
        ?float $percentage,
        bool $dispatchCloseEvent = true
    ): void {
        if ($itemKey === null || $percentage === null) {
            return;
        }

        $items = $component->getRawState() ?? [];
        $resolvedKey = self::resolveInvoiceItemKeyFromMixedKey($items, $itemKey);

        if ($resolvedKey === null || ! array_key_exists($resolvedKey, $items)) {
            return;
        }

        $percentage = round(max(0, min(100, $percentage)), 4);

        $item = $items[$resolvedKey];
        $lineBase = self::calculateLineItemBaseAmount($item);

        $discountAmount = $lineBase <= 0
            ? 0
            : round(min($lineBase, $lineBase * ($percentage / 100)), 2);

        $items[$resolvedKey]['discount_type'] = PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE;
        $items[$resolvedKey]['discount_value'] = $discountAmount; // show nominal value in the field
        $items[$resolvedKey]['discount_percentage'] = $percentage; // keep the percent for calculations

        $component->rawState($items);
        $component->callAfterStateUpdated();
        $component->partiallyRender();

        self::debug('Applied percentage discount to item', [
            'item_key' => $resolvedKey,
            'percentage' => $percentage,
            'line_base' => $lineBase,
            'discount_amount' => $discountAmount,
        ]);

        if ($dispatchCloseEvent) {
            $livewire = $component->getLivewire();

            if ($livewire && method_exists($livewire, 'dispatch')) {
                $livewire->dispatch('filament::line-item-modal-closed');
                self::debug('Dispatched modal close event');
            }
        }
    }

    protected static function debug(string $message, array $context = []): void
    {
        Log::debug('[PurchaseInvoiceForm] ' . $message, $context);
    }

    protected static function getInvoiceItemStateByKey(Repeater $component, ?string $itemKey): ?array
    {
        if ($itemKey === null) {
            return null;
        }

        $items = $component->getRawState() ?? [];

        if (array_key_exists($itemKey, $items)) {
            return $items[$itemKey];
        }

        $resolvedKey = self::resolveInvoiceItemKeyFromMixedKey($items, $itemKey);

        return $resolvedKey === null ? null : ($items[$resolvedKey] ?? null);
    }

    protected static function resolveInvoiceItemKeyFromMixedKey(array $items, ?string $itemKey): ?string
    {
        if ($itemKey === null) {
            return null;
        }

        if (array_key_exists($itemKey, $items)) {
            return (string) $itemKey;
        }

        if (ctype_digit((string) $itemKey)) {
            $orderedKeys = array_keys($items);
            $index = (int) $itemKey;

            if (isset($orderedKeys[$index])) {
                return (string) $orderedKeys[$index];
            }
        }

        $segments = preg_split('/[\.\:\|]/', (string) $itemKey, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (empty($segments)) {
            return null;
        }

        $orderedKeys = array_keys($items);

        foreach (array_reverse($segments) as $segment) {
            if ($segment === 'items') {
                continue;
            }

            if (isset($items[$segment])) {
                return (string) $segment;
            }

            if (ctype_digit($segment)) {
                $index = (int) $segment;

                if (isset($orderedKeys[$index])) {
                    return (string) $orderedKeys[$index];
                }
            }
        }

        return null;
    }

    protected static function makeLineItemDiscountPercentageAction(): Action
    {
        return Action::make('set_line_item_discount_percentage')
            ->label('Atur Diskon %')
            ->modalHeading(fn (array $arguments): string => sprintf(
                'Diskon Persen · %s',
                normalize_item_name($arguments['item_name'] ?? 'Item Faktur') ?? 'Item Faktur'
            ))
            ->modalDescription(fn (array $arguments): string => sprintf(
                'Nilai baris saat ini: Rp %s',
                self::formatCurrency((float) ($arguments['line_base'] ?? 0))
            ))
            ->modalSubmitActionLabel('Gunakan')
            ->modalWidth('md')
            ->schema([
                TextInput::make('percentage')
                    ->label('Persentase Diskon')
                    ->suffix('%')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),
            ])
            ->mountUsing(function (Schema $schema, array $arguments): void {
                $schema->fill([
                    'percentage' => $arguments['percentage'] ?? null,
                ]);
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $percentage = isset($data['percentage']) ? (float) $data['percentage'] : null;

                if ($percentage === null) {
                    return;
                }

                self::applyLineItemDiscountPercentage($component, $arguments['item_key'] ?? null, $percentage);
            })
            ->visible(false);
    }

    protected static function syncInvoiceTotals(SchemaSet $set, SchemaGet $get, ?array $items = null): void
    {
        $itemsCollection = Collection::make($items ?? $get('items') ?? []);
        $isTaxInclusive = (bool) $get('is_tax_inclusive');
        $defaultTaxRate = sanitize_decimal($get('tax_rate') ?? 0, 2);

        $lineItemTotal = $itemsCollection->count();
        $totalQuantityEkor = $itemsCollection->sum(fn (array $item): float => strtolower((string) ($item['unit'] ?? 'kg')) === 'ekor'
            ? sanitize_positive_decimal($item['quantity'] ?? 0)
            : 0);
        $totalWeightKg = $itemsCollection->sum(fn (array $item): float => strtolower((string) ($item['unit'] ?? 'kg')) === 'kg'
            ? sanitize_positive_decimal($item['quantity'] ?? 0, 3)
            : 0);

        $subtotal = 0;
        $lineDiscountTotal = 0;
        $taxTotal = 0;
        $lineNetTotal = 0;

        foreach ($itemsCollection as $item) {
            $unit = strtolower((string) ($item['unit'] ?? 'kg'));
            $quantity = sanitize_positive_decimal($item['quantity'] ?? 0, 3);
            $unitPrice = sanitize_decimal($item['unit_price'] ?? 0);
            $discountType = $item['discount_type'] ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT;
            $discountValue = sanitize_decimal($item['discount_value'] ?? 0);
            $discountPercentage = isset($item['discount_percentage']) ? sanitize_decimal($item['discount_percentage'], 4) : null;
            $applyTax = (bool) ($item['apply_tax'] ?? false);
            $itemTaxRate = sanitize_decimal($item['tax_rate'] ?? $defaultTaxRate, 2);

            $lineBase = round($quantity * $unitPrice, 2);
            $subtotal += $lineBase;

            if ($discountType === PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE) {
                $percentage = $discountPercentage ?? $discountValue;
                $lineDiscount = min($lineBase, round($lineBase * ($percentage / 100), 2));
            } else {
                $lineDiscount = min($lineBase, $discountValue);
            }

            $lineDiscountTotal += $lineDiscount;
            $lineAfterDiscount = max($lineBase - $lineDiscount, 0);

            $lineTax = 0;
            $lineTotal = $lineAfterDiscount;

            if ($applyTax && $itemTaxRate > 0) {
                if ($isTaxInclusive) {
                    $taxable = $lineAfterDiscount / (1 + ($itemTaxRate / 100));
                    $lineTax = round($lineAfterDiscount - $taxable, 2);
                    $lineTotal = $lineAfterDiscount;
                } else {
                    $lineTax = round($lineAfterDiscount * ($itemTaxRate / 100), 2);
                    $lineTotal = round($lineAfterDiscount + $lineTax, 2);
                }
            }

            $taxTotal += $lineTax;
            $lineNetTotal += $lineTotal;
        }

        $globalDiscountType = $get('global_discount_type') ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT;
        $globalDiscountValue = sanitize_decimal($get('global_discount_value') ?? 0);

        $globalDiscount = $globalDiscountType === PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE
            ? min($lineNetTotal, round($lineNetTotal * ($globalDiscountValue / 100), 2))
            : min($lineNetTotal, $globalDiscountValue);

        $discountTotal = $lineDiscountTotal + $globalDiscount;
        $netAfterDiscount = max($lineNetTotal - $globalDiscount, 0);

        $additionalCostTotal = Collection::make($get('additional_costs') ?? [])
            ->sum(fn ($cost): float => sanitize_decimal($cost['amount'] ?? 0));

        $grandTotal = max($netAfterDiscount + $additionalCostTotal, 0);
        $paymentsTotal = Collection::make($get('payments') ?? [])
            ->sum(fn ($payment): float => sanitize_decimal($payment['amount'] ?? 0));
        $balanceDue = max($grandTotal - $paymentsTotal, 0);

        $set('line_item_total', $lineItemTotal);
        $set('line_item_total_display', self::formatNumber($lineItemTotal, 0));
        $set('total_quantity_ea', $totalQuantityEkor);
        $set('total_quantity_ea_display', self::formatNumber($totalQuantityEkor, 3));
        $set('total_weight_kg', $totalWeightKg);
        $set('total_weight_kg_display', self::formatNumber($totalWeightKg, 3));
        $set('subtotal', $subtotal);
        $set('subtotal_display', self::formatCurrency($subtotal));
        $set('discount_total', $discountTotal);
        $set('discount_total_display', self::formatCurrency($discountTotal));
        $set('tax_total', $taxTotal);
        $set('tax_total_display', self::formatCurrency($taxTotal));
        $set('additional_cost_total', $additionalCostTotal);
        $set('additional_cost_total_display', self::formatCurrency($additionalCostTotal));
        $set('grand_total', $grandTotal);
        $set('grand_total_display', self::formatCurrency($grandTotal));
        $set('paid_total', $paymentsTotal);
        $set('paid_total_display', self::formatCurrency($paymentsTotal));
        $set('balance_due', $balanceDue);
        $set('balance_due_display', self::formatCurrency($balanceDue));
    }

    protected static function syncDueDate(SchemaSet $set, SchemaGet $get): void
    {
        $term = $get('payment_term') ?? 'cod';
        $invoiceDate = $get('invoice_date');

        if (! $invoiceDate) {
            return;
        }

        $daysMap = [
            'manual' => 0,
            'cod' => 0,
            'net_7' => 7,
            'net_15' => 15,
            'net_30' => 30,
            'net_45' => 45,
            'net_60' => 60,
        ];

        $days = $daysMap[$term] ?? 0;
        $dueDate = Carbon::parse($invoiceDate)->addDays($days)->toDateString();

        $set('due_date', $dueDate);
    }

    protected static function formatCurrency(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    protected static function formatNumber(float|int $value, int $precision = 2): string
    {
        return number_format($value, $precision, ',', '.');
    }

    protected static function getCostAccountOptions(): array
    {
        return ChartOfAccount::query()
            ->orderBy('code')
            ->pluck('name', 'code')
            ->map(fn (string $name, string $code): string => sprintf('%s — %s', $code, $name))
            ->all();
    }
}
