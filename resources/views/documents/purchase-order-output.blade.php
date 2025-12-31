<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $metadata['document_title'] ?? 'Output PO' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaPHLGdPk5JJ0N0g7AnCM1j403rwhhtjfiPgk41IrT9jp+1rssZCvGSqtEtFPd0PfrxGXMcYeliKZm5wOY5hQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        :root {
            --surface: #fdfaf6;
            --accent: #183153;
            --accent-muted: #4b6b94;
            --border: rgba(0,0,0,0.08);
            --text: #0f172a;
            --muted: #475569;
            --badge-bg: rgba(24,49,83,0.08);
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Space Grotesk', 'Segoe UI', Tahoma, sans-serif;
            background: var(--surface);
            color: var(--text);
            margin: 0;
            padding: 2rem;
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .toolbar button,
        .toolbar a {
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(15,23,42,0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .toolbar button.secondary,
        .toolbar a.secondary {
            background: var(--accent-muted);
        }
        .toolbar button:hover,
        .toolbar a:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(15,23,42,0.2);
        }
        .document-shell {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 20px 45px rgba(15,23,42,0.12);
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            gap: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .doc-header h1 {
            margin: 0;
            font-size: 1.75rem;
            letter-spacing: -0.01em;
        }
        .doc-chip {
            font-size: 0.85rem;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            background: var(--badge-bg);
            color: var(--accent);
            font-weight: 600;
            text-transform: uppercase;
        }
        .doc-meta {
            font-size: 0.95rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .doc-section {
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .doc-section h3 {
            margin: 0 0 0.75rem 0;
            letter-spacing: 0.08em;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--muted);
        }
        .doc-section--split .doc-section__body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
        }
        .doc-section--table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        table thead {
            background: rgba(24,49,83,0.05);
        }
        table th, table td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(15,23,42,0.08);
            text-align: left;
        }
        table th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
        }
        table td.numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
        }
        .summary-card {
            border: 1px dashed var(--border);
            border-radius: 14px;
            padding: 0.85rem 1rem;
            background: rgba(24,49,83,0.02);
        }
        .summary-card span {
            display: block;
        }
        .summary-label {
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 0.35rem;
        }
        .summary-value {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text);
        }
        .attachment-list {
            display: grid;
            gap: 0.75rem;
        }
        .attachment-card {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 0.85rem 1rem;
        }
        .attachment-card strong {
            display: block;
            margin-bottom: 0.25rem;
        }
        .attachment-card small {
            display: block;
            color: var(--muted);
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .toolbar {
                display: none;
            }
            .document-shell {
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    @php($printRoute = route('purchase-order-outputs.print', ['purchaseOrderOutput' => $output, 'format' => 'pdf']))
    <div class="toolbar">
        <button id="btn-copy" type="button">📋 Salin Tampilan</button>
        <button type="button" class="secondary" onclick="window.print()">🖨️ Cetak</button>
        <a href="{{ $printRoute }}" target="_blank" rel="noopener" class="secondary">⬇️ Pratinjau PDF</a>
        <a href="{{ $printRoute }}&amp;download=1" target="_blank" rel="noopener">💾 Unduh PDF</a>
    </div>

    <div class="document-shell" id="capture-area">
        <header class="doc-header">
            <div>
                <div class="doc-chip">{{ $metadata['document_status'] ?? 'Draft' }}</div>
                <h1>{{ $metadata['document_title'] ?? 'Output PO' }}</h1>
                <div class="doc-meta">
                    <div>No. Dokumen: <strong>{{ $metadata['document_number'] ?? '-' }}</strong></div>
                    <div>Tanggal Dokumen: <strong>{{ $metadata['document_date'] ?? '-' }}</strong></div>
                    <div>Nomor PO: <strong>{{ $metadata['po_number'] ?? '-' }}</strong></div>
                </div>
            </div>
            <div class="doc-meta">
                <div>Supplier: <strong>{{ $metadata['supplier_name'] ?? '-' }}</strong></div>
                <div>Gudang: <strong>{{ $metadata['warehouse_name'] ?? ($metadata['destination_warehouse'] ?? '-') }}</strong></div>
                <div>Dicetak oleh: <strong>{{ $metadata['printed_by'] ?? '—' }}</strong></div>
                <div>Dicetak pada: <strong>{{ $metadata['printed_at'] ?? '—' }}</strong></div>
            </div>
        </header>

        @foreach($sections as $section)
            <section class="doc-section doc-section--{{ $section['layout'] === 'table' ? 'table' : ($section['layout'] === 'split' ? 'split' : 'full') }}">
                <h3>{{ $section['title'] }}</h3>
                <div class="doc-section__body">
                    @foreach($section['blocks'] as $block)
                        @if($block['type'] === 'text')
                            <p>{!! nl2br(e($block['data'])) !!}</p>
                        @elseif($block['type'] === 'table')
                            @php($items = $block['data'])
                            @if(count($items) === 0)
                                <p>Belum ada data barang.</p>
                            @else
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Barang</th>
                                            <th>Kode</th>
                                            <th>Qty</th>
                                            <th>Satuan</th>
                                            <th>@ Harga</th>
                                            <th>Diskon</th>
                                            <th>PPN</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($items as $item)
                                            <tr>
                                                <td>
                                                    <strong>{{ $item['item_name'] }}</strong>
                                                    @if(!empty($item['notes']))
                                                        <div style="color: var(--muted); font-size: 0.8rem;">{{ $item['notes'] }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $item['item_code'] ?? '—' }}</td>
                                                <td class="numeric">{{ $item['quantity_display'] }}</td>
                                                <td>{{ $item['unit'] }}</td>
                                                <td class="numeric">{{ $item['unit_price_display'] }}</td>
                                                <td class="numeric">{{ $item['discount_label'] }}</td>
                                                <td>{{ $item['apply_tax'] ? 'PPN 11%' : 'Non PPN' }}</td>
                                                <td class="numeric">{{ $item['line_total_display'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        @elseif($block['type'] === 'summary')
                            <div class="summary-grid">
                                @foreach($block['data'] as $row)
                                    <div class="summary-card">
                                        <span class="summary-label">{{ $row['label'] }}</span>
                                        <span class="summary-value">{{ $row['formatted'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($block['type'] === 'attachments')
                            @php($list = $block['data'])
                            @if(count($list) === 0)
                                <p>Tidak ada lampiran.</p>
                            @else
                                <div class="attachment-list">
                                    @foreach($list as $attachment)
                                        <div class="attachment-card">
                                            <strong>{{ $attachment['label'] ?? 'Lampiran' }}</strong>
                                            @if(!empty($attachment['description']))
                                                <small>{{ $attachment['description'] }}</small>
                                            @endif
                                            @if(!empty($attachment['file_path']))
                                                <small>Ref: {{ $attachment['file_path'] }}</small>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach

        @if(!empty($metadata['notes_internal']))
            <section class="doc-section">
                <h3>Catatan Internal</h3>
                <p>{{ $metadata['notes_internal'] }}</p>
            </section>
        @endif
    </div>

    <script>
        window.documentMetadata = @json($metadata ?? []);
        const captureArea = document.getElementById('capture-area');
        const copyBtn = document.getElementById('btn-copy');

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                copyBtn.disabled = true;
                copyBtn.textContent = 'Memproses...';
                html2canvas(captureArea, { scale: 2, backgroundColor: '#ffffff' }).then(canvas => {
                    canvas.toBlob(blob => {
                        navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })])
                            .then(() => {
                                copyBtn.textContent = '✅ Tersalin';
                                setTimeout(() => {
                                    copyBtn.textContent = '📋 Salin Tampilan';
                                    copyBtn.disabled = false;
                                }, 1800);
                            })
                            .catch(() => {
                                alert('Gagal menyalin. Pastikan browser mendukung Clipboard API.');
                                copyBtn.textContent = '📋 Salin Tampilan';
                                copyBtn.disabled = false;
                            });
                    });
                });
            });
        }
    </script>
</body>
</html>
