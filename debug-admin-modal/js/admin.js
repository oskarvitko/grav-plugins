function init() {
    const container = document.createElement('div')
    container.classList.add('debug-admin-modal-container')

    document.body.append(container)

    window.showDebugModal = function (value, source) {
        const modal = document.createElement('div')

        const content = document.createElement('pre')
        content.textContent = JSON.stringify(value, null, 1)

        const closeButton = document.createElement('button')
        closeButton.textContent = 'X'

        const closeButtonContainer = document.createElement('div')
        closeButtonContainer.append(source)
        closeButtonContainer.append(closeButton)

        content.prepend(closeButtonContainer)

        modal.append(content)

        closeButton.addEventListener('click', () => {
            container.removeChild(modal)
        })

        container.append(modal)
    }

    document.dispatchEvent(new Event('debug-admin-modal'))
}

document.addEventListener('DOMContentLoaded', init)
