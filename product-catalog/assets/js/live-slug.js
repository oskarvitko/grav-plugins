const intervalId = setInterval(() => {
    if ($ && $.slugify) {
        clearInterval(intervalId)

        const targets = document.querySelectorAll('[class*="live-slug-from"]')
        targets.forEach((target) => {
            const sourceName = target.classList
                .toString()
                .split(' ')
                .filter((className) => className.includes('live-slug-from'))[0]

            if (sourceName) {
                const sourceSelector = `input[name="data[${sourceName
                    .split('-')
                    .at(-1)}]"]`
                const source = document.querySelector(sourceSelector)

                if (source) {
                    function touch() {
                        target.setAttribute('touched', '')
                    }

                    function handleChange() {
                        if (
                            target.getAttribute('touched') === null ||
                            !target.value
                        ) {
                            if (!target.value) {
                                target.removeAttribute('touched')
                            }

                            target.value = $.slugify(source.value)
                        }
                    }

                    if (target.value) {
                        touch()
                    }

                    source.addEventListener('input', handleChange)
                    handleChange()

                    target.addEventListener('input', touch, { once: true })
                }
            }
        })
    }
}, 50)
