<?php

namespace App\Filament\Admin\Resources\PurchaseOrderOutputs\Schemas;

use App\Models\LiveChickenPurchaseOrder;
use App\Models\PurchaseOrderOutput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Components\Utilities\Set as SchemaSet;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderOutputForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('purchase_order_output_tabs')
                    ->tabs([
                        Tab::make('Header & Distribusi')
                            ->schema([
                                Section::make('Referensi PO & Gudang')
                                    ->schema([
                                        Select::make('live_chicken_purchase_order_id')
                                            ->label('PO Ayam Hidup')
                                            ->relationship(
                                                name: 'purchaseOrder',
                                                titleAttribute: 'po_number',
                                                modifyQueryUsing: fn (Builder $query): Builder => $query->latest('order_date')
                                            )
                                            ->helperText('Pilih PO yang ingin dicetak. Semua tab lain akan terkunci sampai pilihan ini terisi.')
                                            ->required()
                                            ->searchable()
                                            ->preload(15)
                                            ->live()
                                            ->native(false)
                                            ->afterStateUpdated(function (?int $state, SchemaSet $set, SchemaGet $get): void {
                                                if (! $state) {
                                                    return;
                                                }

                                                $purchaseOrder = LiveChickenPurchaseOrder::query()
                                                    ->withTrashed()
                                                    ->with(['supplier:id,name', 'destinationWarehouse:id,name'])
                                                    ->find($state);

                                                if (! $purchaseOrder) {
                                                    return;
                                                }

                                                if (blank($get('warehouse_id'))) {
                                                    $set('warehouse_id', $purchaseOrder->destination_warehouse_id);
                                                }

                                                if (blank($get('document_title'))) {
                                                    $set('document_title', sprintf('Output %s', $purchaseOrder->po_number));
                                                }

                                                if (blank($get('document_sections'))) {
                                                    $set('document_sections', self::defaultSections($purchaseOrder));
                                                }

                                                $metadata = $get('metadata') ?? [];
                                                foreach (self::defaultMetadata($purchaseOrder) as $key => $value) {
                                                    if (! array_key_exists($key, $metadata) || blank($metadata[$key])) {
                                                        $metadata[$key] = $value;
                                                    }
                                                }

                                                $set('metadata', $metadata);
                                            }),
                                        Select::make('warehouse_id')
                                            ->label('Gudang Cetak')
                                            ->relationship('warehouse', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload(10)
                                            ->native(false)
                                            ->helperText('Dokumen cetak disimpan per gudang agar stok terpisah sesuai aturan multi-warehouse.'),
                                        TextInput::make('document_number')
                                            ->label('No. Dokumen')
                                            ->maxLength(40)
                                            ->required()
                                            ->unique(table: PurchaseOrderOutput::class, column: 'document_number', ignoreRecord: true)
                                            ->helperText('Nomor dibuat otomatis saat simpan dan bisa disesuaikan sebelum status final.'),
                                        DatePicker::make('document_date')
                                            ->label('Tanggal Dokumen')
                                            ->default(today())
                                            ->native(false)
                                            ->required(),
                                        Select::make('status')
                                            ->label('Status')
                                            ->options(PurchaseOrderOutput::statusOptions())
                                            ->default(PurchaseOrderOutput::STATUS_DRAFT)
                                            ->required()
                                            ->native(false),
                                        Select::make('layout_template')
                                            ->label('Template Cetak')
                                            ->options(PurchaseOrderOutput::layoutTemplateOptions())
                                            ->default('standard')
                                            ->required()
                                            ->native(false),
                                        Select::make('printed_by_user_id')
                                            ->label('Operator Cetak')
                                            ->relationship('printedBy', 'name')
                                            ->searchable()
                                            ->preload(10)
                                            ->native(false),
                                        DateTimePicker::make('printed_at')
                                            ->label('Waktu Cetak')
                                            ->seconds(false)
                                            ->native(false),
                                        Textarea::make('notes')
                                            ->label('Catatan Internal')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Konten Cetak')
                            ->schema([
                                Section::make('Struktur Konten')
                                    ->schema([
                                        TextInput::make('document_title')
                                            ->label('Judul Dokumen')
                                            ->required()
                                            ->maxLength(160)
                                            ->helperText('Judul akan ditampilkan di halaman cetak dan PDF.'),
                                        Repeater::make('document_sections')
                                            ->label('Seksi Dokumen')
                                            ->schema([
                                                TextInput::make('title')
                                                    ->label('Judul Seksi')
                                                    ->required()
                                                    ->maxLength(160),
                                                Select::make('layout')
                                                    ->label('Tata Letak')
                                                    ->options([
                                                        'full' => 'Lebar Penuh',
                                                        'split' => 'Dua Kolom',
                                                        'table' => 'Tabel Item PO',
                                                    ])
                                                    ->default('full')
                                                    ->native(false),
                                                Textarea::make('content')
                                                    ->label('Konten')
                                                    ->rows(5)
                                                    ->required()
                                                    ->helperText('Gunakan placeholder seperti {{po_number}}, {{supplier_name}}, {{warehouse_name}}, [[TABEL_BARANG]], atau [[RINGKASAN_BIAYA]].'),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Seksi Dokumen')
                                            ->minItems(1)
                                            ->collapsed()
                                            ->reorderable(true)
                                            ->addActionLabel('Tambah Seksi Cetak')
                                            ->columnSpanFull()
                                            ->disabled(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id'))),
                                    ])
                                    ->columns(2)
                                    ->disabled(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id'))),
                                Section::make('Metadata Placeholder')
                                    ->schema([
                                        KeyValue::make('metadata')
                                            ->label('Metadata Output')
                                            ->keyLabel('Placeholder')
                                            ->valueLabel('Nilai')
                                            ->helperText('Nilai ini akan diteruskan ke view `contoh-view.html` untuk kebutuhan HTML2Canvas & PDF.')
                                            ->addActionLabel('Tambah Placeholder')
                                            ->reorderable()
                                            ->columnSpanFull()
                                            ->disabled(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id'))),
                                    ]),
                            ]),
                        Tab::make('Lampiran & Audit')
                            ->schema([
                                Section::make('Lampiran Dokumen')
                                    ->schema([
                                        Repeater::make('attachments')
                                            ->label('Lampiran')
                                            ->schema([
                                                TextInput::make('label')
                                                    ->label('Nama Lampiran')
                                                    ->maxLength(120)
                                                    ->required(),
                                                TextInput::make('description')
                                                    ->label('Deskripsi / Catatan')
                                                    ->maxLength(200),
                                                TextInput::make('file_path')
                                                    ->label('Referensi File / URL')
                                                    ->maxLength(200)
                                                    ->helperText('Isi path penyimpanan atau URL publik untuk kebutuhan export PDF.'),
                                            ])
                                            ->addActionLabel('Tambah Lampiran')
                                            ->collapsed()
                                            ->columnSpanFull()
                                            ->disabled(fn (SchemaGet $get): bool => blank($get('live_chicken_purchase_order_id'))),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function defaultSections(?LiveChickenPurchaseOrder $purchaseOrder = null): array
    {
        return [
            [
                'title' => 'Ringkasan PO',
                'layout' => 'split',
                'content' => implode(PHP_EOL, array_filter([
                    'No. PO       : {{po_number}}',
                    'Supplier     : ' . ($purchaseOrder?->supplier?->name ?? '{{supplier_name}}'),
                    'Gudang       : ' . ($purchaseOrder?->destinationWarehouse?->name ?? '{{warehouse_name}}'),
                    'Alamat Kirim : {{shipping_address}}',
                ])),
            ],
            [
                'title' => 'Detail Barang',
                'layout' => 'table',
                'content' => '[[TABEL_BARANG]]',
            ],
            [
                'title' => 'Ketentuan Pembayaran & Pengiriman',
                'layout' => 'full',
                'content' => implode(PHP_EOL, [
                    'Syarat Pembayaran : {{payment_term}}',
                    'Catatan PO        : {{notes}}',
                    'Jadwal Kirim      : {{delivery_date}}',
                ]),
            ],
        ];
    }

    protected static function defaultMetadata(LiveChickenPurchaseOrder $purchaseOrder): array
    {
        return [
            'po_number' => $purchaseOrder->po_number,
            'supplier_name' => $purchaseOrder->supplier?->name,
            'warehouse_name' => $purchaseOrder->destinationWarehouse?->name,
            'shipping_address' => $purchaseOrder->shipping_address,
            'payment_term' => $purchaseOrder->payment_term,
            'order_date' => optional($purchaseOrder->order_date)->format('d/m/Y'),
            'delivery_date' => optional($purchaseOrder->delivery_date)->format('d/m/Y'),
        ];
    }
}
