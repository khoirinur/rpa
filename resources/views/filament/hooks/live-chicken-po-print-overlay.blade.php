@once
<div x-data="liveChickenPoPrintOverlay()" x-cloak>
    <div
        class="lcpo-overlay"
        x-show="open"
        x-transition.opacity
        aria-live="polite"
        role="dialog"
        aria-modal="true"
    >
        <div class="lcpo-shell">
            <div class="lcpo-bar">
                <div>
                    <p class="lcpo-label">Print Preview</p>
                    <p class="lcpo-title" x-text="title || 'Print Dokumen'"></p>
                </div>
                <div class="lcpo-actions">
                    <button type="button" class="lcpo-close" @click="close()">Tutup</button>
                </div>
            </div>
            <iframe x-ref="frame" class="lcpo-frame" :src="src" title="Print Preview" frameborder="0"></iframe>
        </div>
    </div>
</div>

<style>
    .lcpo-overlay {
        position: fixed;
        inset: 0;
        background: var(--gray-800);
        z-index: 9999;
        padding: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .lcpo-shell {
        width: min(1200px, 100%);
        height: min(900px, 100%);
        background: #0b1120;
        border-radius: 24px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .lcpo-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 24px;
        background: var(--gray-900);
        color: #e0e7ff;
        gap: 12px;
        flex-wrap: wrap;
    }
    .lcpo-label {
        margin: 0;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
    }
    .lcpo-title {
        margin: 2px 0 0;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .lcpo-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .lcpo-actions button {
        border: none;
        border-radius: 999px;
        padding: 8px 16px;
        font-weight: 600;
        cursor: pointer;
        background: #1d4ed8;
        color: #fff;
    }
    .lcpo-actions button.lcpo-close {
        background: #ff0000;
    }
    .lcpo-frame {
        flex: 1;
        border: none;
        width: 100%;
        background: var(--gray-900);
    }
    .lcpo-no-scroll {
        overflow: hidden;
    }
    @media (max-width: 768px) {
        .lcpo-shell {
            border-radius: 0;
            width: 100%;
            height: 100%;
        }
        .lcpo-overlay {
            padding: 0;
        }
    }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('liveChickenPoPrintOverlay', () => ({
            open: false,
            src: null,
            title: null,
            init() {
                const openHandler = (event) => {
                    const detail = event.detail || {};
                    this.src = detail.url || null;
                    this.title = detail.title || 'Print Dokumen';
                    this.open = Boolean(this.src);
                    document.body.classList.toggle('lcpo-no-scroll', this.open);
                };

                window.addEventListener('live-chicken-po-print-open', openHandler);
                window.addEventListener('goods-receipt-print-open', openHandler);
                window.addEventListener('live-chicken-po-print-close', () => this.close());
                window.addEventListener('goods-receipt-print-close', () => this.close());
            },
            close() {
                this.open = false;
                this.src = null;
                this.title = null;
                document.body.classList.remove('lcpo-no-scroll');
            },
        }));
    });
</script>
@endonce
