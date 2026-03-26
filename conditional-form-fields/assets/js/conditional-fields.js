function initConditionalFields() {
    function onReady(cb) {
        const interval = setInterval(() => {
            if (document.getElementById('blueprints')) {
                clearInterval(interval)
                cb()
            }
        }, 50)
    }

    function onChange(field, cb) {
        const fieldType = field
            .querySelector('[data-grav-field]')
            ?.getAttribute('data-grav-field')

        switch (fieldType) {
            case 'toggle':
                const radios = field.querySelectorAll('input[type="radio"]')

                function handleCb(radio) {
                    cb(radio.value)
                }

                radios.forEach((radio) => {
                    if (radio.checked) {
                        handleCb(radio)
                    }

                    radio.addEventListener('change', (event) => {
                        handleCb(event.target)
                    })
                })

                return
            case 'select':
                const select = field.querySelector('select')

                cb(select.value)

                new MutationObserver((entries) =>
                    entries.forEach((entry) => {
                        cb(entry.target.value)
                    }),
                ).observe(select, { childList: true })

                return
            default:
                return
        }
    }

    function memoized(cb) {
        let prevArgs = undefined

        return function (...args) {
            if (JSON.stringify(prevArgs) === JSON.stringify(args)) {
                return
            }

            prevArgs = args
            cb(...args)
        }
    }

    function showHideElements(conditionName) {
        return function (value) {
            document
                .querySelectorAll(`.${conditionName}[class*="option-"]`)
                .forEach((element) => {
                    const options = Array.from(element.classList)
                        .filter((cls) => cls.startsWith('option-'))
                        .map((cls) => cls.replace('option-', ''))

                    let initialDisplay = element.getAttribute(
                        'data-initial-display',
                    )
                    if (!initialDisplay) {
                        initialDisplay = getComputedStyle(element).display
                        element.setAttribute(
                            'data-initial-display',
                            initialDisplay,
                        )
                    }

                    element.style.display = options.includes(value)
                        ? initialDisplay
                        : 'none'
                })
        }
    }

    onReady(() => {
        const fields = document.querySelectorAll('.conditional')

        fields.forEach((field) => {
            const conditionName = Array.from(field.classList).find((cls) =>
                cls.startsWith('condition-'),
            )

            if (conditionName) {
                onChange(field, memoized(showHideElements(conditionName)))
            }
        })
    })
}

initConditionalFields()
