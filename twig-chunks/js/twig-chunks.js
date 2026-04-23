var CACHED_CHUNKS = {}

function loadTwigChunk(url) {
    return new Promise(function (resolve, reject) {
        var cached = CACHED_CHUNKS[url]

        if (typeof cached === 'string') {
            if (cached === 'error') return reject(cached)

            if (cached === 'pending') {
                return setTimeout(function () {
                    loadTwigChunk(url).then(resolve).catch(reject)
                }, 50)
            }

            return resolve(cached)
        }

        CACHED_CHUNKS[url] = 'pending'

        fetch(url, { headers: { 'X-Chunk-Request': '1' } })
            .then(function (response) {
                if (!response.ok) reject(response.status)

                return response.text()
            })
            .then(function (html) {
                CACHED_CHUNKS[url] = html

                resolve(html)
            })
            .catch(function (error) {
                CACHED_CHUNKS[url] = 'error'

                reject(error)
            })
    })
}

function initTwigChunk(chunkName, params, script) {
    var marker = script.previousElementSibling
    var fallbackNodes = []

    var marker = script
    do {
        marker = marker.previousElementSibling

        if (!marker.hasAttribute('data-chunk-marker')) {
            fallbackNodes.push(marker)
        }
    } while (!marker.hasAttribute('data-chunk-marker'))

    const target = marker.nextElementSibling

    var observer = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return

            obs.unobserve(target)

            var url = '/chunk/' + chunkName

            if (params && Object.keys(params).length > 0) {
                url += '?params=' + encodeURIComponent(JSON.stringify(params))
            }

            var cached = CACHED_CHUNKS[url]
            if (cached !== 'pending' && cached !== '') {
                switch (CACHED_CHUNKS[url]) {
                    case 'pending':
                }
            }

            loadTwigChunk(url)
                .then(function (html) {
                    fallbackNodes.forEach(function (node) {
                        node.remove()
                    })

                    marker.outerHTML = html

                    document.dispatchEvent(
                        new CustomEvent('chunk:loaded', {
                            detail: { chunk: chunkName, params: params },
                        }),
                    )
                })
                .catch(function () {
                    document.dispatchEvent(
                        new CustomEvent('chunk:error', {
                            detail: { chunk: chunkName, params: params },
                        }),
                    )
                })
        })
    })

    observer.observe(target)
}

document.dispatchEvent(new CustomEvent('twig-chunks:ready'))
