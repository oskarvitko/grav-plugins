# Twig Chunks Plugin

The **Twig Chunks** plugin for [Grav CMS](https://github.com/getgrav/grav) enables lazy-loading of Twig template fragments (chunks) via fetch. Each chunk is loaded only when it scrolls into the viewport, reducing initial page load time.

## How it works

1. You place a chunk placeholder in your theme template using `chunks/base.html.twig`
2. When the placeholder enters the viewport, a fetch request is made to `/chunk/{name}`
3. The plugin finds the corresponding template in your theme's `templates/chunks/` folder, renders it, and returns HTML
4. The placeholder is replaced with the rendered HTML in-place

## Installation

Copy or clone this plugin into `user/plugins/twig-chunks` and make sure it is enabled in `user/plugins/twig-chunks/twig-chunks.yaml`:

```yaml
enabled: true
```

## Creating a chunk

Create a Twig file in your theme at `user/themes/{your-theme}/templates/chunks/{name}.html.twig`.

The file **must** contain a metadata comment block and reference the actual template to render:

```twig
{#---
chunk: partials/blocks/answers.html.twig
---#}

{% include "chunks/base.html.twig" with { chunk: 'answers' } %}
```

The `chunk` key in the metadata block is the template path that will be rendered when the route is requested.

> Chunk names may only contain letters, numbers, hyphens and underscores (`[a-zA-Z0-9_-]`).

## Usage

### Minimal

```twig
{% include "chunks/answers.html.twig" %}
```

### With params

```twig
{% include "chunks/answers.html.twig" with {
    params: { category: 'phones', page: 1 }
} %}
```

Inside the chunk template, `params` is available in the Twig context:

```twig
{# catalog.html.twig #}
{% include "chunks/products.html.twig" with {
    params: { category: 'phones', page: 1 }
} %}


{# partials/blocks/products.html.twig #}
{% set category = category %} {# 'phones' #}
{% set page = page|default(1) %} {# 1 #}
```

The same chunk can be included multiple times on the same page without conflicts.

### `base.html.twig` parameters

| Parameter  | Type            | Required | Description |
|------------|-----------------|----------|-------------|
| `chunk`    | `string`        | yes      | Chunk name (filename without extension). Used to build the fetch URL `/chunk/{name}`. |
| `fallback` | `string\|html`  | no       | Content shown immediately while the chunk loads. Removed and replaced once the fetch completes. Supports arbitrary HTML including multiple sibling elements. |
| `params`   | `object/array`  | no       | Key-value pairs passed to the chunk template as the `params` variable. Serialized as JSON and sent as `?params=...` query string. |

### With a string fallback

```twig
{% include "chunks/base.html.twig" with {
    chunk: 'answers',
    fallback: '<p>Loading...</p>'
} %}
```

### With a markup fallback block

```twig
{% set my_fallback %}
    <div class="skeleton">
        <div class="skeleton__item"></div>
        <div class="skeleton__item"></div>
    </div>
{% endset %}

{% include "chunks/base.html.twig" with { chunk: 'answers', fallback: my_fallback } %}
```

The fallback may contain any number of sibling elements — all of them are removed and replaced with the loaded HTML when the fetch completes.


## JavaScript events

After a chunk loads, custom DOM events are dispatched on `document`:

```js
// Fired when a chunk is loaded and replaces the placeholder
document.addEventListener('chunk:loaded', function (e) {
    console.log('Loaded:', e.detail.chunk);
    console.log('Params:', e.detail.params);
});

// Fired when fetch fails or server returns a non-2xx response
document.addEventListener('chunk:error', function (e) {
    console.warn('Failed:', e.detail.chunk);
    console.warn('Params:', e.detail.params);
});
```

Both events include `e.detail.chunk` (chunk name) and `e.detail.params` (the params object passed to the template).

## Caching

- **Server-side:** chunk metadata (file path + parsed meta) is cached for 24 hours using Grav's built-in cache. Cleared with `bin/grav clearcache` or via the Admin panel.
- **Browser-side:** responses include `Cache-Control: public, max-age=86400`, `Expires`, and `Last-Modified` headers.

## Security

- Requests without the `X-Chunk-Request: 1` header receive a **403** response, preventing direct browser navigation to chunk URLs.
- Chunk names are validated by regex before any file lookup — only `[a-zA-Z0-9_-]` characters are accepted.
- Resolved file paths are verified to stay within the theme's `templates/chunks/` directory.


## Plugin templates

`chunks/base.html.twig` is provided by this plugin and is available globally in Twig. It can be overridden by placing a `templates/chunks/base.html.twig` file in your theme.


If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.
