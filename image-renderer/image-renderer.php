<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;
use Twig\Markup;
use Twig_SimpleFunction;

/**
 * Class ImageRendererPlugin
 * @package Grav\Plugin
 */
class ImageRendererPlugin extends Plugin
{
    protected $jsAdded = false;

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onAssetsInitialized' => ['onAssetsInitialized', 0],
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    public function onAssetsInitialized()
    {
        // $config = $this->config->get('plugins.image-renderer');
        // if ($config['loading'] === 'lazy-intersected') {

        // }
    }

    protected function initJs()
    {
        if (!$this->jsAdded) {
            $this->grav['assets']->addJs('plugins://image-renderer/assets/js/lazy-image.js');
        }
    }

    public function create_image($file)
    {
        $absolutePath = $this->grav['locator']->findResource(
            $file['path'],
            true
        );

        [$width, $height] = getimagesize($absolutePath);

        $items = [
            "type" => "image",
            "thumb" => "media/thumb-jpg.png",
            "mime" => $file['type'],
            "image" => [],
            "filepath" => $absolutePath,
            "filename" => $file['name'],
            "basename" => pathinfo($absolutePath, PATHINFO_FILENAME),
            "extension" => pathinfo($absolutePath, PATHINFO_EXTENSION),
            "path" => pathinfo($absolutePath, PATHINFO_DIRNAME),
            "modified" => 1770731099,
            "thumbnails" => [],
            "size" => $file['size'],
            "debug" => false,
            "width" => $width,
            "height" => $height,
        ];

        return new ImageMedium($items);
    }


    public function render_image($_src, $alt, $_options = [])
    {
        $config = $this->config->get('plugins.image-renderer');

        $options = array_merge($config, $_options);

        $src = $_src;
        if (!str_starts_with($src, 'http') && !str_starts_with($src, '/')) {
            $src = '/' . $src;
        }

        $title = $options['title'] ?? $alt;

        $attrs = [
            'src' => $src,
            'alt' => $alt,
            'title' => $title
        ];

        if (key_exists('class', $options)) {
            $attrs['class'] = $options['class'];
        }

        $extra_attrs = key_exists('attrs', $options) ? $options['attrs'] : null;
        if (isset($extra_attrs) && is_array($extra_attrs)) {
            $attrs = array_merge($attrs, $extra_attrs);
        }

        $styles = [];
        $extra_styles = key_exists('styles', $options) ? $options['styles'] : null;
        if (isset($extra_styles) && is_array($extra_styles)) {
            $styles = array_merge($styles, $extra_styles);
        }

        $loading = $options['loading'];
        if ($loading !== 'eager') {
            $attrs['loading'] = $loading;

            if ($loading === 'lazy-intersected') {
                $this->initJs();
                $placeholder = "";
                if (key_exists('placeholder', $options)) {
                    $placeholder = $options['placeholder'];
                }

                $attrs['loading'] = "eager";
                $attrs['src'] = $placeholder;
                $attrs['data-src'] = $src;
                $attrs['data-lazy-image'] = 'true';
            }
        }

        if (count($styles)) {
            $attrs['style'] = "";
            foreach ($styles as $name => $value) {
                $attrs['style'] .= "$name: $value;";
            }
        }

        $html = "<img";

        foreach ($attrs as $name => $value) {
            $html .= " $name='$value'";
        }

        $html .= ' />';

        return new Markup($html, 'utf-8');
    }


    public function onTwigSiteVariables(): void
    {
        /** @var Twig $twig */
        $twig = $this->grav['twig'];

        $twig->twig()->addFunction(
            new Twig_SimpleFunction('render_image', [$this, 'render_image'])
        );
        $twig->twig()->addFunction(
            new Twig_SimpleFunction('create_image', [$this, 'create_image'])
        );
    }
}
