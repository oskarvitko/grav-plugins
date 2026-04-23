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


# Usage

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

## Setting up a fallback

With a fallback shown while loading:

```twig
{% include "chunks/base.html.twig" with {
    chunk: 'answers',
    fallback: '<p>Loading...</p>'
} %}
```

With a fallback as markup block:

```twig
{% set my_fallback %}
    <div class="skeleton">
        <div class="skeleton__item"></div>
    </div>
{% endset %}

{% include "chunks/base.html.twig" with { chunk: 'answers', fallback: my_fallback } %}
```


## Using a chunk in a template

Just include chunk as template

```twig
{% include "chunks/answers.html.twig" %}
```

The same chunk can be included multiple times on the same page without conflicts.

## JavaScript events

After a chunk loads you can react to it via custom DOM events dispatched on `document`:

```js
// Fired when a chunk rendered and replaced the placeholder
document.addEventListener('chunk:loaded', function (e) {
    console.log('Loaded:', e.detail.chunk);
});

// Fired when fetch fails or server returns a non-2xx response
document.addEventListener('chunk:error', function (e) {
    console.warn('Failed:', e.detail.chunk);
});
```

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
