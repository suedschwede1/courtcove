const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])'

// The page scroll lock is on exactly while at least one modal is open, so switching
// straight from one modal into another never leaves the page locked or unlocked.
export function syncScrollLock(doc = document) {
    doc.documentElement.classList.toggle('ds-scroll-lock', doc.querySelector('.ds-modal[data-open]') !== null)
}

export function dsModal() {
    return {
        opener: null,
        wasOpen: false,

        sync(isOpen) {
            if (isOpen && !this.wasOpen) {
                this.wasOpen = true
                this.opener = document.activeElement
                this.$nextTick(() => {
                    const panel = this.$el.querySelector('.ds-modal__panel')
                    const target = panel && (Array.from(panel.querySelectorAll('[data-modal-initial]')).find((el) => el.getClientRects().length > 0) || panel.querySelector('.ds-modal__close') || panel.querySelector('iframe'))
                    if (target) {
                        target.focus()
                    }
                })
            } else if (!isOpen && this.wasOpen) {
                this.wasOpen = false
                const opener = this.opener
                this.opener = null
                if (opener && opener.isConnected && typeof opener.focus === 'function') {
                    // Another modal may have taken focus already; only restore when nothing inside a modal has it.
                    const active = document.activeElement
                    if (!active || active === document.body || this.$el.contains(active)) {
                        opener.focus()
                    }
                }
            }

            // x-bind:data-open is applied after x-effect runs, so read the DOM on the next tick.
            this.$nextTick(() => syncScrollLock())
        },

        trap(event) {
            const items = Array.from(this.$el.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null || el === document.activeElement)
            if (items.length === 0) {
                event.preventDefault()
                return
            }
            const first = items[0]
            const last = items[items.length - 1]
            const active = document.activeElement
            if (event.shiftKey && (active === first || !this.$el.contains(active))) {
                event.preventDefault()
                last.focus()
            } else if (!event.shiftKey && (active === last || !this.$el.contains(active))) {
                event.preventDefault()
                first.focus()
            }
        },
    }
}
