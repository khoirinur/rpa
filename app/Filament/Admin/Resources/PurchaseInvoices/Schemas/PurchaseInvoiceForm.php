<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices\Schemas;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\LiveChickenPurchaseOrder;
use App\Models\Product;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Closure;
use Throwable;
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
    protected static array $productOptionCache = [];
    protected static array $productDetailsCache = [];
    protected static array $unitCodeCache = [];
    protected static array $accountLabelCache = [];
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
                    ->disabled(fn (Component $component): bool => $component->getContainer()->getOperation() === 'edit')
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
                    ->relationship('purchaseOrder', 'po_number', modifyQueryUsing: function (Builder $query, SchemaGet $get): Builder {
                        $current = $get('live_chicken_purchase_order_id');
                        $usedIds = PurchaseInvoice::query()
                            ->whereNotNull('live_chicken_purchase_order_id')
                            ->pluck('live_chicken_purchase_order_id');

                        return $query
                            ->latest('order_date')
                            ->where(function (Builder $inner) use ($usedIds, $current): void {
                                $inner->whereNotIn('id', $usedIds);

                                if ($current) {
                                    $inner->orWhere('id', $current);
                                }
                            });
                    })
                    ->visible(fn (SchemaGet $get): bool => $get('reference_type') === PurchaseInvoice::REFERENCE_TYPE_PURCHASE_ORDER)
                    ->preload(15)
                    ->searchable()
                    ->native(false)
                    ->disabled(fn (Component $component): bool => $component->getContainer()->getOperation() === 'edit')
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
                    ->relationship('goodsReceipt', 'receipt_number', modifyQueryUsing: function (Builder $query, SchemaGet $get): Builder {
                        $current = $get('goods_receipt_id');
                        $usedIds = PurchaseInvoice::query()
                            ->whereNotNull('goods_receipt_id')
                            ->pluck('goods_receipt_id');

                        return $query
                            ->latest('received_at')
                            ->where(function (Builder $inner) use ($usedIds, $current): void {
                                $inner->whereNotIn('id', $usedIds);

                                if ($current) {
                                    $inner->orWhere('id', $current);
                                }
                            });
                    })
                    ->visible(fn (SchemaGet $get): bool => $get('reference_type') === PurchaseInvoice::REFERENCE_TYPE_GOODS_RECEIPT)
                    ->preload(15)
                    ->searchable()
                    ->native(false)
                    ->disabled(fn (Component $component): bool => $component->getContainer()->getOperation() === 'edit')
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
                    ->disabled(fn (Component $component): bool => $component->getContainer()->getOperation() === 'edit')
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
                Hidden::make('pending_invoice_item_payload')
                    ->dehydrated(false),
                Select::make('invoice_item_search')
                    ->label('Cari & Tambah Barang')
                    ->placeholder('Ketik kode/nama produk atau pilih Tambah manual')
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->live()
                    ->preload()
                    ->options(fn (): array => self::getAllProductOptions())
                    ->dehydrated(false)
                    ->disabled(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                    ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get, Select $component): void {
                        if (! $state) {
                            return;
                        }

                        $payload = $state === '__manual'
                            ? self::defaultInvoiceItemState(draft: true)
                            : self::buildPendingInvoiceItemPayloadFromProduct((int) $state);

                        $set('invoice_item_search', null);

                        $itemsComponent = self::resolveInvoiceItemsComponentFrom($component);

                        if ($itemsComponent && self::triggerPendingInvoiceItemModal($payload, $itemsComponent)) {
                            return;
                        }

                        $set('pending_invoice_item_payload', $payload);
                    })
                    ->columnSpanFull(),
                Placeholder::make('items_gate_notice')
                    ->hiddenLabel()
                    ->content('Pilih supplier terlebih dahulu untuk mengaktifkan rincian barang.')
                    ->visible(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'text-sm font-medium text-danger-600']),
                Repeater::make('items')
                    ->label('Tabel List Barang')
                    ->relationship('items')
                    ->orderColumn('line_number')
                    ->schema(self::invoiceItemTableSchema())
                    ->table(self::invoiceItemTableColumns())
                    ->default([])
                    ->columns(12)
                    ->columnSpanFull()
                    ->cloneable(true)
                    ->deletable(true)
                    ->addable(false)
                    ->reorderable()
                    ->extraItemActions([
                        self::makeEditInvoiceItemAction(),
                        self::makeInlineDiscountPercentageAction(),
                    ])
                    ->extraAttributes(['data-row-click-action' => 'edit_invoice_item'])
                    ->disabled(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                        $normalizedItems = self::normalizeInvoiceItems($state ?? []);

                        if ($normalizedItems !== ($state ?? [])) {
                            $set('items', $normalizedItems);
                        }

                        self::syncInvoiceTotals($set, $get, $normalizedItems);
                        self::processPendingInvoiceItemPayload($set, $get, $component);
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
                    ->default('11')
                    ->native(false)
                    ->formatStateUsing(fn ($state): ?string => $state === null ? null : (string) (float) $state)
                    ->afterStateHydrated(function ($state, callable $set): void {
                        $set('tax_rate', $state === null ? null : (string) (float) $state);
                    })
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
                Hidden::make('pending_payment_payload')
                    ->dehydrated(false),
                Repeater::make('payments')
                    ->label('Riwayat Pembayaran')
                    ->relationship('payments')
                    ->orderColumn('paid_at')
                    ->schema(self::paymentTableSchema())
                    ->table(self::paymentTableColumns())
                    ->columns(12)
                    ->default([])
                    ->cloneable(true)
                    ->deletable(true)
                    ->addable(false)
                    ->reorderable()
                    ->extraItemActions([
                        self::makeEditPaymentAction(),
                    ])
                    ->extraAttributes(['data-row-click-action' => 'edit_payment'])
                    ->afterStateHydrated(function (?array $state, SchemaSet $set, SchemaGet $get, Repeater $component): void {
                        self::syncInvoiceTotals($set, $get, $get('items') ?? []);
                        self::processPendingPaymentPayload($set, $get, $component);
                    })
                    ->afterStateUpdated(function (?array $state, SchemaSet $set, SchemaGet $get): void {
                        self::syncInvoiceTotals($set, $get, $get('items') ?? []);
                    }),
            ])
            ->headerActions([
                Action::make('add_payment')
                    ->label('Tambah Pembayaran')
                    ->button()
                    ->color('primary')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Tambah Pembayaran')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg')
                    ->schema(self::paymentFields())
                    ->disabled(fn (SchemaGet $get): bool => blank($get('supplier_id')))
                    ->mountUsing(function (Schema $schema): void {
                        $schema->fill(self::defaultPaymentState());
                    })
                    ->action(function (array $data, Component $component): void {
                        $paymentsComponent = self::resolvePaymentsComponentFrom($component);

                        if (! $paymentsComponent) {
                            return;
                        }

                        self::upsertPaymentState($paymentsComponent, self::preparePaymentPayload($data));
                    }),
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
                $summarySection,

            ]);
    }

    protected static function defaultInvoiceItemState(bool $draft = false): array
    {
        return [
            'product_id' => null,
            'item_code' => null,
            'item_name' => null,
            'unit' => self::DEFAULT_UNIT,
            'quantity' => 0,
            'unit_price' => 0,
            'discount_type' => PurchaseInvoice::DISCOUNT_TYPE_AMOUNT,
            'discount_value' => 0,
            'discount_percentage' => null,
            'apply_tax' => false,
            'tax_rate' => 11,
            'warehouse_id' => null,
            'notes' => null,
            '__draft' => $draft,
        ];
    }

    protected static function invoiceItemTableSchema(): array
    {
        return [
            Hidden::make('product_id'),
            Hidden::make('item_code'),
            Hidden::make('item_name'),
            Hidden::make('unit'),
            Hidden::make('quantity'),
            Hidden::make('unit_price'),
            Hidden::make('discount_type'),
            Hidden::make('discount_value'),
            Hidden::make('discount_percentage'),
            Hidden::make('apply_tax'),
            Hidden::make('tax_rate'),
            Hidden::make('warehouse_id'),
            Hidden::make('notes'),
            Placeholder::make('table_item_summary')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $label = normalize_item_name($get('item_name')) ?? 'Item faktur tanpa nama';
                    $code = $get('item_code');
                    $notes = $get('notes');

                    $segments = array_filter([
                        $label,
                        $code ? sprintf('[%s]', $code) : null,
                        $notes,
                    ]);

                    $lines = array_map(
                        fn (string $segment): string => str_replace(["\r\n", "\r", "\n"], '<br>', e($segment)),
                        array_values($segments)
                    );

                    return new HtmlString(implode('<br>', $lines));
                })
                ->html()
                ->color('primary')
                ->extraAttributes([
                    'class' => 'leading-tight text-sm font-medium',
                ]),
            Placeholder::make('table_quantity')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatNumber(sanitize_positive_decimal($get('quantity')), 3))
                ->extraAttributes(['class' => 'text-right tabular-nums text-sm text-gray-700']),
            Placeholder::make('table_unit')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => strtoupper((string) ($get('unit') ?? 'N/A')))
                ->extraAttributes(['class' => 'text-sm text-gray-700 uppercase']),
            Placeholder::make('table_unit_price')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrencyWithPrefix(sanitize_decimal($get('unit_price'))))
                ->extraAttributes(['class' => 'text-right tabular-nums text-sm text-gray-700']),
            Placeholder::make('table_discount')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): string {
                    $type = $get('discount_type') ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT;
                    $value = sanitize_decimal($get('discount_value'));
                    $percentage = sanitize_decimal($get('discount_percentage') ?? null, 4);

                    if ($type === PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE) {
                        return sprintf('%.2f%%', min($percentage ?: $value, 100));
                    }

                    if ($value <= 0) {
                        return 'N/A';
                    }

                    return self::formatCurrencyWithPrefix($value);
                })
                ->extraAttributes(['class' => 'text-right tabular-nums text-sm text-gray-700']),
            Placeholder::make('table_tax')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): string {
                    if (! $get('apply_tax')) {
                        return 'Non PPN';
                    }

                    $rate = sanitize_decimal($get('tax_rate') ?? 0, 2);

                    return $rate > 0 ? sprintf('PPN %.2f%%', $rate) : 'PPN';
                })
                ->extraAttributes(['class' => 'text-sm text-gray-700 text-center']),
            Placeholder::make('table_warehouse')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => $get('warehouse_id') ? 'Warehouse #' . $get('warehouse_id') : '-')
                ->extraAttributes(['class' => 'text-sm text-gray-700 text-center']),
            Placeholder::make('table_line_total')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrencyWithPrefix(self::calculateInvoiceLineDisplayTotal($get)))
                ->extraAttributes(['class' => 'text-right font-semibold tabular-nums text-sm text-gray-900']),
        ];
    }

    protected static function invoiceItemTableColumns(): array
    {
        return [
            TableColumn::make('Barang')->width('28rem'),
            TableColumn::make('Kuantitas')->width('7rem'),
            TableColumn::make('Satuan')->width('6rem'),
            TableColumn::make('@Harga')->width('10rem'),
            TableColumn::make('Diskon')->width('9rem'),
            TableColumn::make('PPN')->width('9rem'),
            TableColumn::make('Gudang')->width('9rem'),
            TableColumn::make('Total')->width('10rem'),
        ];
    }

    protected static function invoiceItemFields(): array
    {
        return [
            Hidden::make('product_id'),
            TextInput::make('item_code')
                ->label('Kode #')
                ->inlineLabel()
                ->maxLength(60)
                ->readOnly()
                ->dehydrated()
                ->columnSpanFull(),
            TextInput::make('item_name')
                ->label('Nama Item')
                ->inlineLabel()
                ->required()
                ->maxLength(180)
                ->columnSpanFull(),
            Select::make('unit')
                ->label('Satuan')
                ->inlineLabel()
                ->options(fn (): array => self::getUnitOptions())
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->default(self::DEFAULT_UNIT)
                ->columnSpanFull(),
            TextInput::make('quantity')
                ->label('Kuantitas')
                ->inlineLabel()
                ->type('text')
                ->required()
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                    if (sanitize_decimal($value) < 0.01) {
                        $fail('Kuantitas minimal 0,01.');
                    }
                })
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS
                ))
                ->stripCharacters(['.', ','])
                ->live()
                ->columnSpanFull(),
            TextInput::make('unit_price')
                ->label('@Harga')
                ->inlineLabel()
                ->type('text')
                ->required()
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                    if (sanitize_decimal($value) < 0) {
                        $fail('Harga tidak boleh negatif.');
                    }
                })
                ->prefix('Rp')
                ->formatStateUsing(fn ($state): ?string => $state === null
                    ? null
                    : number_format((float) $state, 0, ',', '.'))
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS
                ))
                ->stripCharacters(['.', ','])
                ->dehydrateStateUsing(function ($state) {
                    if (is_numeric($state)) {
                        return (float) $state;
                    }

                    return sanitize_rupiah($state ?? 0);
                })
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, SchemaSet $set, SchemaGet $get, TextInput $component): void {
                    self::refreshLineItemPercentageDiscount($component);
                })
                ->columnSpanFull(),
            Select::make('discount_type')
                ->label('Tipe Diskon')
                ->inlineLabel()
                ->options(PurchaseInvoice::discountTypeOptions())
                ->default(PurchaseInvoice::DISCOUNT_TYPE_AMOUNT)
                ->native(false)
                ->live()
                ->afterStateUpdated(function (?string $state, SchemaSet $set): void {
                    if ($state !== PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE) {
                        $set('discount_percentage', null);
                    }
                })
                ->columnSpanFull(),
            TextInput::make('discount_value')
                ->label('Nilai Diskon')
                ->inlineLabel()
                ->prefix('Rp')
                ->formatStateUsing(fn ($state): ?string => $state === null
                    ? null
                    : number_format((float) sanitize_decimal($state ?? 0), 0, ',', '.'))
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                ->stripCharacters(['.', ','])
                ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                ->default(0)
                ->columnSpanFull(),
            Hidden::make('discount_percentage')
                ->default(null)
                ->dehydrated(false),
            Toggle::make('apply_tax')
                ->label('Kenakan Pajak')
                ->inline(false)
                ->columnSpanFull(),
            Select::make('tax_rate')
                ->label('Tarif Pajak')
                ->inlineLabel()
                ->options(PurchaseInvoice::taxRateOptions())
                ->default('11')
                ->native(false)
                ->formatStateUsing(fn ($state): ?string => $state === null ? null : (string) (float) $state)
                ->afterStateHydrated(function ($state, callable $set): void {
                    $set('tax_rate', $state === null ? null : (string) (float) $state);
                })
                ->columnSpanFull(),
            Select::make('warehouse_id')
                ->label('Gudang Item')
                ->inlineLabel()
                ->relationship('warehouse', 'name')
                ->native(false)
                ->preload(15)
                ->searchable()
                ->columnSpanFull(),
            Placeholder::make('computed_total')
                ->label('Total Harga')
                ->inlineLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrencyWithPrefix(self::calculateInvoiceLineDisplayTotal($get)))
                ->columnSpanFull(),
            Textarea::make('notes')
                ->label('Catatan Item')
                ->inlineLabel()
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    protected static function makeEditInvoiceItemAction(): Action
    {
        return Action::make('edit_invoice_item')
            ->label('Ubah Item')
            ->modalHeading('Detail Item Faktur')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('xl')
            ->schema(self::invoiceItemFields())
            ->extraAttributes(['data-row-trigger-only' => true])
            ->mountUsing(function (Schema $schema, array $arguments, Repeater $component): void {
                if (! empty($arguments['pending']) && is_array($arguments['payload'] ?? null)) {
                    $schema->fill($arguments['payload']);

                    return;
                }

                $itemKey = $arguments['item'] ?? null;
                $state = self::getInvoiceItemStateByKey($component, $itemKey) ?? self::defaultInvoiceItemState();

                $schema->fill($state);
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $itemKey = $arguments['item'] ?? null;
                $isPending = (bool) ($arguments['pending'] ?? false);

                if ($arguments['delete_invoice_item'] ?? false) {
                    self::removeInvoiceItemState($component, $itemKey);

                    return;
                }

                if ($isPending) {
                    $data['__draft'] = false;
                    self::upsertInvoiceItemState($component, self::prepareInvoiceItemPayload($data));

                    return;
                }

                self::upsertInvoiceItemState($component, self::prepareInvoiceItemPayload($data), $itemKey);
            })
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('delete_invoice_item', arguments: ['delete_invoice_item' => true])
                    ->label('Hapus')
                    ->color('danger')
                    ->requiresConfirmation(),
            ]);
    }

    protected static function makeInlineDiscountPercentageAction(): Action
    {
        return Action::make('set_line_discount_percentage')
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
            });
    }

    protected static function defaultPaymentState(bool $draft = false): array
    {
        return [
            'payment_type' => PurchaseInvoicePayment::TYPE_DOWN_PAYMENT,
            'paid_at' => today()->toDateString(),
            'amount' => 0,
            'account_id' => 132,
            'payment_method' => PurchaseInvoicePayment::METHOD_CASH,
            'reference_number' => null,
            'is_manual' => true,
            'attachments' => [],
            'notes' => null,
            '__draft' => $draft,
        ];
    }

    protected static function paymentTableSchema(): array
    {
        return [
            Hidden::make('payment_type'),
            DatePicker::make('paid_at')
                ->label('Tanggal Bayar')
                ->inlineLabel()
                ->native(false)
                ->displayFormat('d-m-Y')
                ->dehydrateStateUsing(function ($state) {
                    if ($state instanceof \Illuminate\Support\Carbon) {
                        return $state->toDateString();
                    }
                    if (blank($state)) {
                        return today()->toDateString();
                    }
                    return \Illuminate\Support\Carbon::parse($state)->toDateString();
                })
                ->default(today())
                ->columnSpanFull(),
            Hidden::make('amount'),
            Hidden::make('account_id'),
            Hidden::make('payment_method'),
            Hidden::make('reference_number'),
            Hidden::make('is_manual'),
            Hidden::make('attachments'),
            Hidden::make('notes'),
            Placeholder::make('table_payment_meta')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): HtmlString {
                    $type = PurchaseInvoicePayment::typeOptions()[$get('payment_type') ?? ''] ?? 'Pembayaran';
                    $method = PurchaseInvoicePayment::methodOptions()[$get('payment_method') ?? ''] ?? '';
                    $ref = $get('reference_number');
                    $notes = $get('notes');

                    $segments = array_filter([
                        $type,
                        $method ? sprintf('[%s]', $method) : null,
                        $ref,
                        $notes,
                    ]);

                    $lines = array_map(
                        fn (string $segment): string => str_replace(["\r\n", "\r", "\n"], '<br>', e($segment)),
                        array_values($segments)
                    );

                    return new HtmlString(implode('<br>', $lines));
                })
                ->html()
                ->extraAttributes(['class' => 'leading-tight text-sm text-gray-800']),
            Placeholder::make('table_paid_at')
                ->hiddenLabel()
                ->content(function (SchemaGet $get): string {
                    $raw = $get('paid_at');
                    if (blank($raw)) return '-';
                    try {
                        return \Illuminate\Support\Carbon::parse($raw)->format('d-m-Y');
                    } catch (\Exception $e) {
                        return (string) $raw;
                    }
                })
                ->extraAttributes(['class' => 'text-sm text-gray-700 text-center']),
            Placeholder::make('table_amount')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatCurrencyWithPrefix(sanitize_decimal($get('amount') ?? 0)))
                ->extraAttributes(['class' => 'text-right font-semibold tabular-nums text-sm text-gray-900']),
            Placeholder::make('table_account')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => self::formatAccountLabel($get('account_id')))
                ->extraAttributes(['class' => 'text-sm text-gray-700 text-center']),
            Placeholder::make('table_manual')
                ->hiddenLabel()
                ->content(fn (SchemaGet $get): string => $get('is_manual') ? 'Manual' : 'Auto')
                ->extraAttributes(['class' => 'text-sm text-gray-700 text-center']),
        ];
    }

    protected static function paymentTableColumns(): array
    {
        return [
            TableColumn::make('Pembayaran')->width('26rem'),
            TableColumn::make('Tanggal')->width('9rem'),
            TableColumn::make('Nominal')->width('10rem'),
            TableColumn::make('Akun')->width('10rem'),
            TableColumn::make('Input')->width('8rem'),
        ];
    }

    protected static function paymentFields(): array
    {
        return [
            Select::make('payment_type')
                ->label('Jenis Pembayaran')
                ->inlineLabel()
                ->options(PurchaseInvoicePayment::typeOptions())
                ->default(PurchaseInvoicePayment::TYPE_DOWN_PAYMENT)
                ->native(false)
                ->columnSpanFull(),
            DatePicker::make('paid_at')
                ->label('Tanggal Bayar')
                ->inlineLabel()
                ->native(false)
                ->displayFormat('d-m-Y')
                ->dehydrateStateUsing(function ($state) {
                    if ($state instanceof Carbon) {
                        return $state->toDateString();
                    }
                    if (blank($state)) {
                        return today()->toDateString();
                    }
                    return Carbon::parse($state)->toDateString();
                })
                ->default(today())
                ->columnSpanFull(),
            TextInput::make('amount')
                ->label('Nominal')
                ->inlineLabel()
                ->prefix('Rp')
                ->mask(RawJs::make(<<<'JS'
$money($input, ',', '.', 0)
JS))
                ->stripCharacters(['.', ','])
                ->default(0)
                // Fix: when opening modal, if value is 3000000 but table shows 30000, divide by 100 if needed
                ->formatStateUsing(function ($state) {
                    // If state is null or 0, just return 0
                    if (blank($state)) return 0;
                    // If state is string, try to parse to float
                    $val = is_numeric($state) ? (float)$state : (float)preg_replace('/[^\d.]/', '', $state);
                    // If value is > 999999 and ends with three zeros, likely double-multiplied, so divide by 100
                    if ($val > 999999 && substr((string)intval($val), -3) === '000') {
                        $val = $val / 100;
                    }
                    // If value is > 9999999 and ends with three zeros, divide by 1000 (legacy bug)
                    if ($val > 9999999 && substr((string)intval($val), -3) === '000') {
                        $val = $val / 1000;
                    }
                    return $val;
                })
                ->dehydrateStateUsing(fn ($state): float => sanitize_rupiah($state ?? 0))
                ->rule(fn () => ['numeric', 'min:0', 'max:9999999999999999.99'])
                ->columnSpanFull(),
            Select::make('account_id')
                ->label('Akun Kas/Bank')
                ->inlineLabel()
                ->options(fn (): array => self::getCashBankAccountOptions())
                ->searchable()
                ->preload()
                ->native(false)
                ->default(132)
                ->columnSpanFull(),
            Select::make('payment_method')
                ->label('Metode')
                ->inlineLabel()
                ->options(PurchaseInvoicePayment::methodOptions())
                ->default(PurchaseInvoicePayment::METHOD_CASH)
                ->native(false)
                ->columnSpanFull(),
            TextInput::make('reference_number')
                ->label('Referensi')
                ->inlineLabel()
                ->maxLength(60)
                ->columnSpanFull(),
            Toggle::make('is_manual')
                ->label('Input Manual')
                ->inlineLabel()
                ->inline(false)
                ->default(true)
                ->columnSpanFull(),
            FileUpload::make('attachments')
                ->label('Lampiran')
                ->inlineLabel()
                ->directory('purchase-invoices/payments')
                ->multiple()
                ->maxFiles(5)
                ->maxSize(5120)
                ->downloadable()
                ->previewable(false)
                ->columnSpanFull(),
            Textarea::make('notes')
                ->label('Catatan')
                ->inlineLabel()
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    protected static function makeEditPaymentAction(): Action
    {
        return Action::make('edit_payment')
            ->label('Ubah Pembayaran')
            ->modalHeading('Detail Pembayaran')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('lg')
            ->schema(self::paymentFields())
            ->extraAttributes(['data-row-trigger-only' => true])
            ->mountUsing(function (Schema $schema, array $arguments, Repeater $component): void {
                if (! empty($arguments['pending']) && is_array($arguments['payload'] ?? null)) {
                    $schema->fill($arguments['payload']);

                    return;
                }

                $itemKey = $arguments['item'] ?? null;
                $state = self::getPaymentStateByKey($component, $itemKey) ?? self::defaultPaymentState();

                $schema->fill($state);
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                \Illuminate\Support\Facades\Log::debug('[DEBUG] Modal Edit Payment', [
                    'raw_paid_at' => $data['paid_at'] ?? null,
                    'data' => $data,
                ]);
                $itemKey = $arguments['item'] ?? null;
                $isPending = (bool) ($arguments['pending'] ?? false);

                if ($arguments['delete_payment'] ?? false) {
                    self::removePaymentState($component, $itemKey);
                    return;
                }

                if ($isPending) {
                    $data['__draft'] = false;
                    self::upsertPaymentState($component, self::preparePaymentPayload($data));
                    return;
                }

                self::upsertPaymentState($component, self::preparePaymentPayload($data), $itemKey);
            })
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('delete_payment', arguments: ['delete_payment' => true])
                    ->label('Hapus')
                    ->color('danger')
                    ->requiresConfirmation(),
            ]);
    }

    protected static function upsertPaymentState(Repeater $component, array $payload, ?string $itemKey = null): void
    {
        $items = $component->getRawState() ?? [];
        $key = $itemKey ?: (string) Str::uuid();


        // Debug: log payload before saving to state
        \Illuminate\Support\Facades\Log::debug('[DEBUG] upsertPaymentState payload', [
            'key' => $key,
            'payload' => $payload,
        ]);

        // Force paid_at to string (Y-m-d) before saving to state
        if (isset($payload['paid_at']) && $payload['paid_at'] instanceof \Illuminate\Support\Carbon) {
            $payload['paid_at'] = $payload['paid_at']->toDateString();
        } elseif (isset($payload['paid_at']) && !is_string($payload['paid_at'])) {
            $payload['paid_at'] = (string) $payload['paid_at'];
        }

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

    protected static function removePaymentState(Repeater $component, ?string $itemKey): void
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

    protected static function getPaymentStateByKey(Repeater $component, ?string $itemKey): ?array
    {
        if ($itemKey === null) {
            return null;
        }

        $items = $component->getRawState() ?? [];

        if (array_key_exists($itemKey, $items)) {
            return $items[$itemKey];
        }

        if (ctype_digit((string) $itemKey)) {
            $orderedKeys = array_keys($items);
            $index = (int) $itemKey;

            if (isset($orderedKeys[$index])) {
                return $items[$orderedKeys[$index]] ?? null;
            }
        }

        return null;
    }

    protected static function processPendingPaymentPayload(SchemaSet $set, SchemaGet $get, Repeater $component): void
    {
        $payload = $get('pending_payment_payload');

        if (! is_array($payload)) {
            return;
        }

        $set('pending_payment_payload', null);

        if (! self::triggerPendingPaymentModal($payload, $component)) {
            $set('pending_payment_payload', $payload);
        }
    }

    protected static function triggerPendingPaymentModal(array $payload, Repeater $component): bool
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
                $livewire->mountAction('edit_payment', $arguments, [
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
                'action' => 'edit_payment',
                'arguments' => $arguments,
                'context' => ['schemaComponent' => $schemaComponentKey],
            ]);

            return true;
        }

        return false;
    }

    protected static function resolvePaymentsComponentFrom(Component $context): ?Repeater
    {
        $component = $context->getRootContainer()->getComponent(
            fn ($candidate): bool => $candidate instanceof Repeater
                && $candidate->getStatePath(isAbsolute: false) === 'payments',
            withHidden: true,
        );

        return $component instanceof Repeater ? $component : null;
    }

    protected static function preparePaymentPayload(array $data): array
    {
        $paidAt = $data['paid_at'] ?? null;

        if ($paidAt instanceof Carbon) {
            $paidAt = $paidAt->toDateString();
        } elseif (is_string($paidAt) && filled($paidAt)) {
            try {
                $paidAt = Carbon::parse($paidAt)->toDateString();
            } catch (\Exception $e) {
                $paidAt = today()->toDateString();
            }
        } elseif (is_numeric($paidAt)) {
            // If paidAt is a timestamp (int/float), treat as Y-m-d
            try {
                $paidAt = Carbon::createFromTimestamp((int)$paidAt)->toDateString();
            } catch (\Exception $e) {
                $paidAt = today()->toDateString();
            }
        } else {
            $paidAt = today()->toDateString();
        }

        // Make sure paidAt is string (Y-m-d)
        if (!is_string($paidAt)) {
            $paidAt = today()->toDateString();
        }

        $amount = $data['amount'] ?? 0;
        if (is_string($amount)) {
            $amount = (float) str_replace([',', '.'], '', $amount);
        }
        $amount = (float) sanitize_rupiah($amount);

        \Illuminate\Support\Facades\Log::debug('[DEBUG] preparePaymentPayload', [
            'input_paid_at' => $data['paid_at'] ?? null,
            'normalized_paid_at' => $paidAt,
            'normalized_paid_at_type' => gettype($paidAt),
            'input_amount' => $data['amount'] ?? null,
            'normalized_amount' => $amount,
        ]);

        return [
            'payment_type' => $data['payment_type'] ?? PurchaseInvoicePayment::TYPE_DOWN_PAYMENT,
            'paid_at' => $paidAt,
            'amount' => $amount,
            'account_id' => $data['account_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? PurchaseInvoicePayment::METHOD_CASH,
            'reference_number' => $data['reference_number'] ?? null,
            'is_manual' => (bool) ($data['is_manual'] ?? false),
            'attachments' => $data['attachments'] ?? [],
            'notes' => $data['notes'] ?? null,
            '__draft' => (bool) ($data['__draft'] ?? false),
        ];
    }

    protected static function upsertInvoiceItemState(Repeater $component, array $payload, ?string $itemKey = null): void
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

    protected static function removeInvoiceItemState(Repeater $component, ?string $itemKey): void
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

    protected static function processPendingInvoiceItemPayload(SchemaSet $set, SchemaGet $get, Repeater $component): void
    {
        $payload = $get('pending_invoice_item_payload');

        if (! is_array($payload)) {
            return;
        }

        $set('pending_invoice_item_payload', null);

        if (! self::triggerPendingInvoiceItemModal($payload, $component)) {
            $set('pending_invoice_item_payload', $payload);
        }
    }

    protected static function buildPendingInvoiceItemPayloadFromProduct(int $productId): array
    {
        $data = self::defaultInvoiceItemState(draft: true);
        $data['product_id'] = $productId;

        $details = self::getProductDetails($productId);

        if ($details) {
            $data['item_name'] = $details['name'] ?? null;
            $data['item_code'] = $details['code'] ?? null;
            $data['unit'] = self::resolveProductUnitCode($details['unit_id'] ?? null);
        }

        return $data;
    }

    protected static function triggerPendingInvoiceItemModal(array $payload, Repeater $component): bool
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
                $livewire->mountAction('edit_invoice_item', $arguments, [
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
                'action' => 'edit_invoice_item',
                'arguments' => $arguments,
                'context' => ['schemaComponent' => $schemaComponentKey],
            ]);

            return true;
        }

        return false;
    }

    protected static function prepareInvoiceItemPayload(array $data): array
    {
        $details = self::getProductDetails($data['product_id'] ?? null);

        $resolvedName = $data['item_name'] ?? $details['name'] ?? null;
        $resolvedName = normalize_item_name($resolvedName);

        $payload = [
            'product_id' => $data['product_id'] ?? $details['id'] ?? null,
            'item_code' => $data['item_code'] ?? $details['code'] ?? null,
            'item_name' => $resolvedName ?: null,
            'unit' => strtolower($data['unit'] ?? self::DEFAULT_UNIT),
            'quantity' => sanitize_positive_decimal($data['quantity'] ?? 0, 3),
            'unit_price' => sanitize_decimal($data['unit_price'] ?? 0),
            'discount_type' => $data['discount_type'] ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT,
            'discount_value' => sanitize_decimal($data['discount_value'] ?? 0),
            'discount_percentage' => isset($data['discount_percentage']) ? sanitize_decimal($data['discount_percentage'], 4) : null,
            'apply_tax' => (bool) ($data['apply_tax'] ?? false),
            'tax_rate' => sanitize_decimal($data['tax_rate'] ?? 0, 2),
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            '__draft' => (bool) ($data['__draft'] ?? false),
        ];

        if ($payload['discount_type'] !== PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE) {
            $payload['discount_percentage'] = null;
        }

        return $payload;
    }

    protected static function calculateInvoiceLineDisplayTotal(SchemaGet $get): float
    {
        $quantity = sanitize_positive_decimal($get('quantity') ?? 0, 3);
        $unitPrice = sanitize_decimal($get('unit_price') ?? 0);
        $discountType = $get('discount_type') ?? PurchaseInvoice::DISCOUNT_TYPE_AMOUNT;
        $discountValue = sanitize_decimal($get('discount_value') ?? 0);
        $discountPercentage = sanitize_decimal($get('discount_percentage') ?? null, 4);
        $applyTax = (bool) ($get('apply_tax') ?? false);
        $itemTaxRate = sanitize_decimal($get('tax_rate') ?? ($get('../../tax_rate') ?? 0), 2);
        $isTaxInclusive = (bool) ($get('../../is_tax_inclusive') ?? $get('is_tax_inclusive') ?? false);

        $lineBase = round($quantity * $unitPrice, 2);

        $lineDiscount = $discountType === PurchaseInvoice::DISCOUNT_TYPE_PERCENTAGE
            ? min($lineBase, round($lineBase * (min($discountPercentage ?: $discountValue, 100) / 100), 2))
            : min($lineBase, $discountValue);

        $afterDiscount = max($lineBase - $lineDiscount, 0);

        if (! $applyTax || $itemTaxRate <= 0) {
            return $afterDiscount;
        }

        if ($isTaxInclusive) {
            return $afterDiscount;
        }

        return round($afterDiscount * (1 + ($itemTaxRate / 100)), 2);
    }

    protected static function formatCurrencyWithPrefix(float $value): string
    {
        return 'Rp ' . self::formatCurrency($value);
    }

    protected static function getAllProductOptions(): array
    {
        if (empty(self::$productOptionCache)) {
            self::$productOptionCache = ['__manual' => '[Tambah item manual]'] + self::searchProducts();
        }

        return self::$productOptionCache;
    }

    protected static function searchProducts(?string $search = null): array
    {
        return Product::query()
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

    protected static function getProductDetails(?int $productId): ?array
    {
        if (! $productId) {
            return null;
        }

        if (! array_key_exists($productId, self::$productDetailsCache)) {
            self::$productDetailsCache[$productId] = Product::query()
                ->find($productId, ['id', 'name', 'code', 'unit_id'])
                ?->toArray();
        }

        return self::$productDetailsCache[$productId];
    }

    protected static function resolveProductUnitCode(?int $unitId): string
    {
        if (! $unitId) {
            return self::DEFAULT_UNIT;
        }

        if (! array_key_exists($unitId, self::$unitCodeCache)) {
            self::$unitCodeCache[$unitId] = Unit::query()
                ->find($unitId, ['code'])
                ?->code;
        }

        return strtolower(self::$unitCodeCache[$unitId] ?? self::DEFAULT_UNIT);
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

    protected static function getCashBankAccountOptions(): array
    {
        if (! empty(self::$accountLabelCache)) {
            return self::$accountLabelCache;
        }

        self::$accountLabelCache = ChartOfAccount::query()
            ->where('type', 'kas_bank')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (ChartOfAccount $record): array => [
                $record->id => sprintf('%s — %s', $record->code, $record->name),
            ])
            ->all();

        return self::$accountLabelCache;
    }

    protected static function formatAccountLabel($accountId): string
    {
        if (! $accountId) {
            return '-';
        }

        $options = self::getCashBankAccountOptions();

        return $options[$accountId] ?? sprintf('Akun #%s', $accountId);
    }
}
