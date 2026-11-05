// Header dropdown (disclosure): a button that shows a list of links. Escape closes it and
// returns focus to the button; a click outside or tabbing away closes it; the arrow
// keys open it and move through its links.
export function dsNavGroup() {
    return {
        open: false,

        toggle() {
            this.open = !this.open
        },

        close(focusTrigger = false) {
            if (!this.open) {
                return
            }
            this.open = false
            if (focusTrigger) {
                this.$refs.trigger.focus()
            }
        },

        leave(event) {
            if (!this.$root.contains(event.relatedTarget)) {
                this.open = false
            }
        },

        links() {
            return Array.from(this.$root.querySelectorAll('.ds-header__menu a'))
        },

        move(step) {
            if (!this.open) {
                this.open = true
                this.$nextTick(() => this.links()[0]?.focus())

                return
            }
            const links = this.links()
            if (links.length === 0) {
                return
            }
            const current = links.indexOf(document.activeElement)
            const next = current === -1 ? (step > 0 ? 0 : links.length - 1) : (current + step + links.length) % links.length
            links[next].focus()
        },
    }
}
