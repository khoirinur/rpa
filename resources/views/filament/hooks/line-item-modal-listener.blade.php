@once
    <script>
        if (! window.__poLineItemListenerRegistered) {
            window.__poLineItemListenerRegistered = true

            const handler = (event) => {
                const detail = event.detail ?? {}

                const livewireId = detail.livewireId ?? event.target?.__livewire?.id

                if (! livewireId || ! window.Livewire?.find) {
                    console.warn('[PO] Livewire component not found for modal request', {
                        livewireId,
                        hasLivewireGlobal: Boolean(window.Livewire?.find),
                        detail,
                    })

                    return
                }

                const component = window.Livewire.find(livewireId)

                if (! component?.mountAction) {
                    console.warn('[PO] mountAction is unavailable on Livewire component', {
                        livewireId,
                        component,
                    })

                    return
                }

                const action = detail.action ?? 'edit_line_item'
                const args = detail.arguments ?? {}
                const context = detail.context ?? {}

                component.mountAction(action, args, context)
            }

            window.addEventListener('filament::line-item-modal-requested', handler)
            document.addEventListener('filament::line-item-modal-requested', handler)
        }
    </script>
@endonce
