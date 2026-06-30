import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()

function openDatePicker(input) {
    if (!input || typeof input.showPicker !== 'function' || input.disabled || input.readOnly) {
        return
    }

    try {
        input.showPicker()
    } catch (_error) {
    }
}

function getHeaderDateInput(target) {
    return target.closest('.ui-calendar-date-input')
}

document.addEventListener('pointerdown', (event) => {
    const input = getHeaderDateInput(event.target)
    if (!input) {
        return
    }

    if (event.pointerType && event.pointerType !== 'mouse') {
        return
    }

    event.preventDefault()
    input.focus()
    openDatePicker(input)
})

document.addEventListener('click', (event) => {
    const input = getHeaderDateInput(event.target)
    if (!input) {
        return
    }

    input.focus()
    openDatePicker(input)
})

document.addEventListener('focusin', (event) => {
    const input = getHeaderDateInput(event.target)
    if (!input) {
        return
    }

    openDatePicker(input)
})
