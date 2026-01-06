<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Models\InventoryBalance;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Throwable;

class ProductForm
{
    protected static array $warehouseStockCache = [];
    protected static array $unitLabelCache = [];

    public static function configure(Schema $schema): Schema
    {
        $sections = self::baseSections();

        if (self::isViewRoute()) {
            return $schema
                ->components([
                    Tabs::make('product_view_tabs')
                        ->tabs([
                            Tab::make('Detail Produk')
                                ->schema($sections),
                            Tab::make('Stok Gudang')
                                ->schema(self::warehouseStockTabSchema()),
                        ])
                        ->columnSpanFull(),
                ]);
        }

        return $schema->components($sections);
    }

    protected static function baseSections(): array
    {
        return [
            Section::make('Identitas Produk')
                ->schema([
                    Hidden::make('id')
                        ->dehydrated(false),
                    TextInput::make('code')
                        ->label('Kode Produk')
                        ->required()
                        ->maxLength(20)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText('Kode mengikuti format P-XXXX dan harus unik.')
                        ->suffixAction(
                            Action::make('generate_code')
                                ->label('Generate')
                                ->icon('heroicon-m-sparkles')
                                ->action(function (Set $set): void {
                                    $set('code', sprintf('P-%04d', random_int(1, 9999)));
                                }),
                        ),
                    TextInput::make('name')
                        ->label('Nama Produk')
                        ->required()
                        ->maxLength(150),
                    Select::make('type')
                        ->label('Jenis Produk')
                        ->options(Product::typeOptions())
                        ->required()
                        ->default('persediaan')
                        ->native(false)
                        ->helperText('Gunakan jenis Persediaan/Jasa/Non-Persediaan sesuai kebutuhan akuntansi.'),
                    Select::make('unit_id')
                        ->label('Satuan')
                        ->relationship('unit', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Opsional — gunakan jika produk memiliki satuan baku dari Master Units.'),
                    Select::make('product_category_id')
                        ->label('Kategori Produk')
                        ->relationship('productCategory', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->required()
                        ->helperText('Data diambil dari Master Kategori Produk.'),
                ])
                ->columns(2),
            Section::make('Gudang & Status')
                ->schema([
                    Select::make('default_warehouse_id')
                        ->label('Gudang Default')
                        ->relationship('defaultWarehouse', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Digunakan saat transaksi tidak memilih gudang secara eksplisit.'),
                    Toggle::make('is_active')
                        ->label('Status Aktif')
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2),
            Section::make('Catatan')
                ->schema([
                    Textarea::make('description')
                        ->label('Catatan Tambahan')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected static function warehouseStockTabSchema(): array
    {
        return [
            Section::make('Distribusi Stok Gudang')
                ->description('Menampilkan ringkasan stok per gudang berdasarkan Inventory Balance terbaru.')
                ->schema([
                    Placeholder::make('warehouse_stock_overview')
                        ->label('Ringkasan Stok')
                        ->content(fn (SchemaGet $get): HtmlString => self::renderWarehouseStockOverview((int) ($get('id') ?? 0)))
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'text-sm']),
                ])
                ->columns(1),
        ];
    }

    protected static function renderWarehouseStockOverview(?int $productId): HtmlString
    {
        if (! $productId) {
            return new HtmlString('<div class="text-sm text-gray-500">Data stok per gudang akan muncul setelah produk tersimpan.</div>');
        }

        $entries = self::getWarehouseStockEntries($productId);

        if (empty($entries)) {
            return new HtmlString('<div class="text-sm text-gray-500">Belum ada pergerakan stok untuk produk ini.</div>');
        }

        $unitLabel = self::resolveProductUnitLabel($productId);
        $totalOnHand = array_reduce($entries, fn ($carry, $entry) => $carry + ($entry['on_hand'] ?? 0), 0.0);

        $totalAvailableValue = array_reduce(
            $entries,
            fn ($carry, $entry) => $carry + ($entry['available'] ?? 0),
            0.0
        );
        $totalIncomingValue = array_reduce(
            $entries,
            fn ($carry, $entry) => $carry + ($entry['incoming'] ?? 0),
            0.0
        );
        $totalReservedValue = array_reduce(
            $entries,
            fn ($carry, $entry) => $carry + ($entry['reserved'] ?? 0),
            0.0
        );

        $summaryCards = [
            ['label' => 'On Hand', 'value' => self::formatQuantity($totalOnHand), 'accent' => 'text-gray-900'],
            ['label' => 'Tersedia', 'value' => self::formatQuantity($totalAvailableValue), 'accent' => 'text-emerald-700'],
            ['label' => 'Sedang Masuk', 'value' => self::formatQuantity($totalIncomingValue), 'accent' => 'text-amber-700'],
            ['label' => 'Reservasi', 'value' => self::formatQuantity($totalReservedValue), 'accent' => 'text-rose-700'],
        ];

        $summaryGrid = '';
        foreach ($summaryCards as $card) {
            $summaryGrid .= sprintf(
                '<div class="rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">%s</p>
                    <p class="mt-1 text-xl font-bold %s">%s <span class="text-xs font-semibold text-gray-500">%s</span></p>
                </div>',
                e($card['label']),
                e($card['accent']),
                e($card['value']),
                e($unitLabel)
            );
        }

        $rows = '';

        foreach ($entries as $entry) {
            $warehouseName = e($entry['warehouse_name']);
            $code = e($entry['warehouse_code'] ?? '-');
            $badge = $entry['is_default'] ? '<span class="ml-2 inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700">Default</span>' : '';
            $lastUpdate = $entry['last_transaction_at']
                ? e($entry['last_transaction_at'])
                : 'Belum ada transaksi';
            $onHand = self::formatQuantity($entry['on_hand']);
            $available = self::formatQuantity($entry['available']);
            $reserved = self::formatQuantity($entry['reserved']);
            $incoming = self::formatQuantity($entry['incoming']);

            $rows .= <<<HTML
<tr>
    <td class="px-4 py-3 align-top text-sm font-semibold text-gray-900">
        <div class="flex items-center">{$warehouseName}{$badge}</div>
        <div class="text-xs font-normal text-gray-500">Kode: {$code}</div>
    </td>
    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{$onHand}</td>
    <td class="px-4 py-3 text-right text-sm text-gray-900">{$available}</td>
    <td class="px-4 py-3 text-right text-sm text-gray-700">{$reserved}</td>
    <td class="px-4 py-3 text-right text-sm text-gray-700">{$incoming}</td>
    <td class="px-4 py-3 text-sm text-gray-600">{$lastUpdate}</td>
</tr>
HTML;
        }

        $html = <<<HTML
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 rounded-3xl border border-gray-100 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm">
        <h4 class="text-sm font-semibold text-gray-800">Ringkasan Total</h4>
        <div class="grid gap-3 sm:grid-cols-2">{$summaryGrid}</div>
    </div>
    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
            <table class="w-full text-sm text-gray-700">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Gudang</th>
                        <th class="px-4 py-3 text-right">On Hand ({$unitLabel})</th>
                        <th class="px-4 py-3 text-right">Tersedia ({$unitLabel})</th>
                        <th class="px-4 py-3 text-right">Reservasi ({$unitLabel})</th>
                        <th class="px-4 py-3 text-right">Sedang Masuk ({$unitLabel})</th>
                        <th class="px-4 py-3 text-left">Update Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    {$rows}
                </tbody>
            </table>
        </div>
    </div>
</div>
HTML;

        return new HtmlString($html);
    }

    protected static function getWarehouseStockEntries(?int $productId): array
    {
        if (! $productId) {
            return [];
        }

        if (array_key_exists($productId, self::$warehouseStockCache)) {
            return self::$warehouseStockCache[$productId];
        }

        $balances = InventoryBalance::query()
            ->where('product_id', $productId)
            ->with(['warehouse' => fn ($query) => $query->withTrashed()->select('id', 'name', 'code', 'is_default')])
            ->orderBy('warehouse_id')
            ->get();

        $entries = $balances
            ->map(function (InventoryBalance $balance): array {
                $warehouse = $balance->warehouse;

                return [
                    'warehouse_id' => $balance->warehouse_id,
                    'warehouse_name' => $warehouse?->name ?? 'Gudang #' . $balance->warehouse_id,
                    'warehouse_code' => $warehouse?->code ?? '-',
                    'is_default' => (bool) ($warehouse?->is_default ?? false),
                    'on_hand' => (float) $balance->on_hand_quantity,
                    'available' => (float) $balance->available_quantity,
                    'incoming' => (float) $balance->incoming_quantity,
                    'reserved' => (float) $balance->reserved_quantity,
                    'last_transaction_at' => $balance->last_transaction_at?->timezone(config('app.timezone'))?->format('d M Y H:i'),
                ];
            })
            ->filter(function (array $entry): bool {
                $hasMovement = $entry['on_hand'] > 0 || $entry['available'] > 0 || $entry['incoming'] > 0 || $entry['reserved'] > 0;

                if ($hasMovement) {
                    return true;
                }

                return $entry['is_default'];
            })
            ->sortByDesc('on_hand')
            ->values()
            ->all();

        self::$warehouseStockCache[$productId] = $entries;

        return $entries;
    }

    protected static function resolveProductUnitLabel(?int $productId): string
    {
        if (! $productId) {
            return 'Unit';
        }

        if (! array_key_exists($productId, self::$unitLabelCache)) {
            $product = Product::query()
                ->with('unit:id,name')
                ->find($productId, ['id', 'unit_id']);

            self::$unitLabelCache[$productId] = $product?->unit?->name ?? 'Unit';
        }

        return self::$unitLabelCache[$productId];
    }

    protected static function formatQuantity(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',') ?: '0';
    }

    protected static function isViewRoute(): bool
    {
        try {
            return request()->routeIs('filament.admin.resources.products.view');
        } catch (Throwable) {
            return false;
        }
    }
}
