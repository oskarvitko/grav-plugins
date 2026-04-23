<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Symfony\Component\Yaml\Yaml;

class TwigChunksPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0],
            ],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
        ];
    }

    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }
    }

    public function onTwigTemplatePaths(): void
    {
        // Register plugin templates so chunks/base.html.twig is found
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onPagesInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $path = $this->grav['uri']->path();

        if (preg_match('#^/chunk/([a-zA-Z0-9_-]+)$#', $path, $matches)) {
            $this->handleChunkRoute($matches[1]);
        }
    }

    private function loadChunk(string $chunkName): ?array
    {
        $cache = $this->grav['cache'];
        $cacheKey = 'twig-chunks-meta-' . $chunkName;

        $cached = $cache->fetch($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $chunksDir = $this->grav['locator']->findResource('theme://') . '/templates/chunks';
        $file = $chunksDir . '/' . $chunkName . '.html.twig';

        if (strpos(realpath($file) ?: '', realpath($chunksDir)) !== 0) {
            return null;
        }

        if (!is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $meta = [];

        if (preg_match('/{#---(.*?)---#}/s', $content, $matches)) {
            try {
                $meta = Yaml::parse(trim($matches[1])) ?? [];
            } catch (\Exception $e) {
                $meta = [];
            }
        }

        $chunk = [
            'file' => $file,
            'meta' => $meta,
        ];

        $cache->save($cacheKey, $chunk, 86400);

        return $chunk;
    }

    private function handleChunkRoute(string $chunkName): void
    {
        $isDebug = $this->grav['config']->get('system.debugger.enabled');

        if (($_SERVER['HTTP_X_CHUNK_REQUEST'] ?? '') !== '1' && !$isDebug) {
            http_response_code(403);
            exit;
        }

        $chunk = $this->loadChunk($chunkName);

        if ($chunk === null) {
            http_response_code(404);
            exit;
        }

        $grav = $this->grav;
        $twig = $grav['twig'];
        $config = $grav['config'];
        $themeName = $config->get('system.pages.theme');

        $rawParams = $grav['uri']->query('params');
        $params = [];
        if ($rawParams) {
            $decoded = json_decode($rawParams, true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        // Start with all vars Grav normally provides to Twig templates,
        // then add/override with chunk-specific ones
        $context = array_merge($twig->twig_vars, $params, [
            'chunk_meta' => $chunk['meta'],
            'theme_config' => $config->get('themes.' . $themeName),
            'ajax' => true,
        ]);

        if (empty($chunk['meta']['chunk'])) {
            http_response_code(500);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($chunk['file'])) . ' GMT');

        try {
            $html = $twig->processTemplate($chunk['meta']['chunk'], $context);

            $plugins = $grav['plugins'];
            /** @var HTMLMinifierPlugin $minifyPlugin */
            $minifyPlugin = $plugins->getPlugin('html-minifier');
            if ($minifyPlugin && $minifyPlugin->active) {
                if (!class_exists(\WyriHaximus\HtmlCompress\Factory::class)) {
                    require_once __DIR__ . '/../html-minifier/vendor/autoload.php';
                }
                $html = \WyriHaximus\HtmlCompress\Factory::constructFastest()->compress($html);
            }

            echo $html;
        } catch (\Exception $e) {
            http_response_code(500);
        }

        exit;
    }
}
