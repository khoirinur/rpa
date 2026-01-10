<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $metadata['title'] ?? 'Penerimaan Barang' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        :root {
            --ink: #1f2937;
            --muted: #4b5563;
            --border: #1f2937;
            --accent: #000;
            --light-gray: #f5f5f5;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Space Grotesk', 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            background: #e5e7eb;
            padding: 32px;
            color: var(--ink);
        }
        .controls {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .controls button,
        .controls a {
            border: none;
            padding: 10px 18px;
            border-radius: 999px;
            font-weight: 600;
            cursor: pointer;
            background: #0f172a;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .controls button.secondary,
        .controls a.secondary {
            background: #4b5563;
        }
        #clipboard-warning {
            display: none;
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 10px 16px;
            margin-bottom: 16px;
            font-size: 0.9px;
        }
        #capture-area {
            background: #fff;
            padding: 10px;
            border-radius: 18px;
            box-shadow: 0 25px 45px rgba(15, 23, 42, 0.1);
            max-width: 980px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
        }
        .company-block {
            display: flex;
            gap: 16px;
            width: 60%;
        }
        .company-block img {
            width: 90px;
            height: 90px;
            object-fit: contain;
        }
        .company-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.05em;
        }
        .company-address {
            margin: 6px 0 0;
            font-size: 12px;
            color: var(--muted);
            white-space: pre-line;
        }
        .doc-title-block {
            text-align: right;
        }
        .doc-title {
            font-size: 16px;
            font-weight: 700;
            border: 2px solid #000;
            padding: 6px 18px;
            display: inline-block;
            margin-bottom: 12px;
        }
        .doc-meta table {
            border-collapse: collapse;
            width: 100%;
        }
        .doc-meta td {
            font-size: 12px;
            padding: 2px 0;
        }
        .doc-meta td:first-child {
            padding-right: 16px;
        }
        .info-row {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            font-size: 14px;
        }
        .info-label {
            font-weight: 600;
            min-width: 120px;
        }
        table.detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0 12px;
        }
        table.detail-table th,
        table.detail-table td {
            border: 1px solid #000;
            padding: 2px;
            font-size: 12px;
        }
        table.detail-table th {
            background: var(--light-gray);
            text-align: center;
            font-size: 12px;
        }
        table.detail-table td.numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .notes-summary {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }
        .notes {
            font-size: 12px;
        }
        table.summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.summary-table td {
            padding: 2px 0;
            font-size: 12px;
        }
        table.summary-table td:last-child {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border: 1px solid #000;
            gap: 0px;
            margin-top: 10px;
            text-align: center;
            font-size: 12px;
        }
        .signature-cell {
            padding: 12px 12px 12px;
        }
        .footer {
            margin-top: 16px;
            font-size: 12px;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .controls,
            #clipboard-warning {
                display: none !important;
            }

            #capture-area {
                max-width: none;
                width: 100%;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    @php
        $companyAddress = trim($metadata['company_address'] ?? config('app.company_address', 'Jl. Totok Kerot, Suko, Menang, Kec. Pagu Kab. Kediri Jawa Timur 64183 Indonesia'));
        $companyAddressLines = array_values(array_filter([
            $companyAddress,
            $metadata['company_city'] ?? null,
            ! empty($metadata['company_phone']) ? 'Telp: ' . $metadata['company_phone'] : null,
        ]));
        $supplierAddress = trim($metadata['supplier_address'] ?? '');
        $supplierAddressLines = $supplierAddress !== ''
            ? array_values(array_filter(preg_split("/(\r\n|\r|\n)/", $supplierAddress) ?: []))
            : [];
    @endphp
    <div class="controls">
        <button id="btn-copy" type="button">Salin Gambar</button>
        <button id="btn-download" type="button">Unduh Gambar</button>
        <button type="button" class="secondary" onclick="window.print()">Cetak / PDF</button>
    </div>

    <p id="clipboard-warning"></p>

    <div id="capture-area">
        <div class="header">
            <div class="company-block">
                <img src="{{ asset('logo.jpeg') }}" alt="Logo">
                <div>
                    <p class="company-name">{{ strtoupper($metadata['company_name'] ?? config('app.company_name', 'Perusahaan Anda')) }}</p>
                    <p class="company-address">{!! implode('<br>', array_map(fn ($line) => e(trim($line)), $companyAddressLines)) !!}</p>
                </div>
            </div>
            <div class="doc-title-block">
                <div class="doc-title">Penerimaan Barang</div>
                <div class="doc-meta">
                    <table>
                        <tr>
                            <td>No. Form :</td>
                            <td>{{ $metadata['form_number'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td>No. Faktur :</td>
                            <td>{{ $metadata['invoice_number'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td>Tanggal :</td>
                            <td>{{ $metadata['received_date'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td>No. Polisi :</td>
                            <td>{{ $metadata['vehicle_plate'] ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <div><span class="info-label">Terima dari</span>: <b>{{ $metadata['supplier_name'] ?? '—' }}</b></div>
            </div>
            <div class="info-row" style="margin-top: 6px;">
                <div>
                    <span class="info-label">Alamat</span>:
                    <span style="white-space: pre-line;">{!! $supplierAddressLines ? implode('<br>', array_map(fn ($line) => e(trim($line)), $supplierAddressLines)) : '—' !!}</span>
                </div>
            </div>
        </div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>No. PO</th>
                    <th>Gudang</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Qty.</th>
                    <th>Sat.</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lineItems as $item)
                    <tr>
                        <td class="numeric">{{ $item['index'] }}</td>
                        <td>{{ $item['po_number'] ?? '—' }}</td>
                        <td>{{ $item['warehouse'] }}</td>
                        <td>{{ $item['item_code'] }}</td>
                        <td>{{ $item['item_name'] }}</td>
                        <td class="numeric">{{ $item['quantity_display'] }}</td>
                        <td style="text-align:center;">{{ $item['unit'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:18px;">
                            Belum ada rincian barang.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="notes-summary">
            <div class="">
                <div class="notes">
                    Keterangan :
                    <br>
                    {!! nl2br(e($metadata['arrival_notes'] ?? '-')) !!}
                    <br><br>
                </div>
                <div class="signature-grid">
                    <div class="signature-cell">
                        Bag. Gudang,
                        <div style="margin-top:80px; border-top:1px dotted #000;"></div>
                    </div>
                    <div class="signature-cell">
                        Admin,
                        <div style="margin-top:80px; border-top:1px dotted #000;"></div>
                    </div>
                    <div class="signature-cell">
                        Bag. Pembelian,
                        <div style="margin-top:80px; border-top:1px dotted #000;"></div>
                    </div>
                </div>
            </div>
            <div>
                <table class="summary-table">
                    @foreach($summary as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['formatted'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        
        <div class="footer">
            <span>Dicetak pada {{ $metadata['document_generated_at'] ?? now()->translatedFormat('d/m/Y H:i') }}</span>
            <span>Aplikasi RPA SKMeat.ID</span>
        </div>
    </div>

    <script>
        const metadata = @json($metadata ?? []);
        const btnCopy = document.getElementById('btn-copy');
        const btnDownload = document.getElementById('btn-download');
        const captureArea = document.getElementById('capture-area');
        const clipboardWarning = document.getElementById('clipboard-warning');
        const clipboardSupported = window.isSecureContext
            && typeof window.ClipboardItem !== 'undefined'
            && navigator.clipboard
            && typeof navigator.clipboard.write === 'function';

        if (!clipboardSupported && clipboardWarning && btnCopy) {
            clipboardWarning.style.display = 'block';
            clipboardWarning.textContent = 'Clipboard API belum tersedia pada konteks ini. Gunakan tombol "Unduh Gambar" atau akses melalui HTTPS/localhost.';
            btnCopy.disabled = true;
            btnCopy.textContent = 'Clipboard tidak didukung';
        }

        if (btnCopy && captureArea && clipboardSupported) {
            btnCopy.addEventListener('click', () => {
                btnCopy.disabled = true;
                btnCopy.textContent = 'Memproses...';

                html2canvas(captureArea, { scale: 2, backgroundColor: '#ffffff', useCORS: true, allowTaint: true }).then(canvas => {
                    canvas.toBlob(blob => {
                        if (!blob) {
                            return resetCopyButton();
                        }

                        try {
                            const item = new ClipboardItem({ 'image/png': blob });
                            navigator.clipboard.write([item])
                                .then(() => {
                                    btnCopy.textContent = 'Tersalin';
                                    setTimeout(resetCopyButton, 1600);
                                })
                                .catch(() => {
                                    alert('Gagal menyalin ke clipboard. Pastikan izin clipboard diaktifkan.');
                                    resetCopyButton();
                                });
                        } catch (error) {
                            alert('Clipboard API belum tersedia. Gunakan tombol "Unduh Gambar".');
                            resetCopyButton();
                        }
                    });
                }).catch(() => {
                    alert('Gagal memproses tampilan. Coba ulang atau gunakan tombol "Unduh Gambar".');
                    resetCopyButton();
                });
            });
        }

        function resetCopyButton() {
            btnCopy.disabled = false;
            btnCopy.textContent = 'Salin Gambar';
        }

        if (btnDownload && captureArea) {
            btnDownload.addEventListener('click', () => {
                btnDownload.disabled = true;
                btnDownload.textContent = 'Menyiapkan...';

                html2canvas(captureArea, { scale: 2, backgroundColor: '#ffffff', useCORS: true, allowTaint: true })
                    .then(canvas => {
                        const link = document.createElement('a');
                        const fallback = metadata.form_number ? `${metadata.form_number}.png` : 'goods-receipt.png';
                        link.download = fallback;
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    })
                    .catch(() => alert('Gagal membuat gambar untuk diunduh.'))
                    .finally(() => {
                        btnDownload.disabled = false;
                        btnDownload.textContent = '💾 Unduh Gambar';
                    });
            });
        }
    </script>
</body>
</html>
