import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()

function openDatePicker(input) {
    if (!input || typeof input.showPicker !== 'function' || input.disabled || input.readOnly) {
        return
    }

    input.showPicker()
}

document.addEventListener('pointerdown', (event) => {
    const input = event.target.closest('input[type="date"]')
    if (!input) {
        return
    }

    event.preventDefault()
    input.focus()
    openDatePicker(input)
})

document.addEventListener('focusin', (event) => {
    const input = event.target.closest('input[type="date"]')
    if (!input) {
        return
    }

    openDatePicker(input)
})
