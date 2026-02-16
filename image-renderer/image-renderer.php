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
            'onFlexObjectBeforeSave' => ['onFlexObjectBeforeSave', 0],
        ];
    }

    protected function getImageToGenerate($fields, $path = '', $result = [])
    {
        foreach ($fields as $propName => $propConfig) {
            if (is_array($propConfig)) {
                if (array_key_exists('type', $propConfig)) {
                    if ($propConfig['type'] === 'file') {
                        if (array_key_exists('generate_params', $propConfig)) {
                            $result[$path . $propName] = $propConfig['generate_params'];
                        }
                    }
                }

                if (array_key_exists('fields', $propConfig)) {
                    $this->getImageToGenerate($propConfig['fields'], $propName . '.', $result);
                }
            }
        }

        return $result;
    }

    public function onFlexObjectBeforeSave($event)
    {
        /** @var FlexObject $object */
        $object = $event['object'];

        $blueprint = $event['directory']->getBlueprint();
        $form = $blueprint['form'];

        $media = [];

        if (isset($form) && array_key_exists('fields', $form)) {
            $result = $this->getImageToGenerate($form['fields']);

            foreach ($result as $propPath => $generateParams) {
                $files = $object->getNestedProperty($propPath);

                $isGenerationEnabled = count($generateParams) > 0;

                if ($isGenerationEnabled) {
                    $media[$propPath] = [];
                }

                foreach ($files as $filePath => $file) {
                    $fileSpec = $this->getFileSpec($file);

                    if ($isGenerationEnabled) {
                        $media[$propPath][$filePath] = [
                            'original' => [
                                'url' => "/" . $file['path'],
                                'width' => $fileSpec['width'],
                                'height' => $fileSpec['height']
                            ]
                        ];
                    }

                    foreach ($generateParams as $resultName => $param) {
                        $image = $this->create_image_from_spec($fileSpec);

                        foreach ($param as $operation => $operationArgs) {
                            $image->{$operation}(...$operationArgs);
                        }

                        $url = $image->url();

                        [$width, $height] = getimagesize($this->grav['locator']->base . $url);

                        $media[$propPath][$filePath][$resultName] = [
                            'url' => $url,
                            'width' => $width,
                            'height' => $height
                        ];
                    }
                }
            }
        }

        if (count($media)) {
            $object->setProperty('image_media', $media);
        }
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

    protected function initJs()
    {
        if (!$this->jsAdded) {
            $this->grav['assets']->addJs('plugins://image-renderer/assets/js/lazy-image.js');
        }
    }

    protected function getFileSpec($file)
    {
        return $this->getFilePathSpec($file['path']);
    }

    protected function getFilePathSpec($path)
    {
        $absolutePath = $this->grav['locator']->findResource(
            $path,
            true
        );

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $absolutePath);

        [$width, $height] = getimagesize($absolutePath);

        return [
            "type" => "image",
            "thumb" => "media/thumb-jpg.png",
            "mime" => $mimeType,
            "image" => [],
            "filepath" => $absolutePath,
            "filename" => pathinfo($absolutePath, PATHINFO_FILENAME),
            "basename" => pathinfo($absolutePath, PATHINFO_FILENAME),
            "extension" => pathinfo($absolutePath, PATHINFO_EXTENSION),
            "path" => pathinfo($absolutePath, PATHINFO_DIRNAME),
            "modified" => filemtime($absolutePath),
            "thumbnails" => [],
            "size" => filesize($absolutePath),
            "debug" => false,
            "width" => $width,
            "height" => $height,
        ];
    }

    protected function create_image_from_spec($fileSpec)
    {
        return new ImageMedium($fileSpec);
    }

    public function create_image_from_path($path)
    {
        $fileSpec = $this->getFilePathSpec($path);

        return $this->create_image_from_spec($fileSpec);
    }

    public function create_image($file)
    {
        $fileSpec = $this->getFileSpec($file);

        return $this->create_image_from_spec($fileSpec);
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
        if ($this->isAdmin()) {
            return;
        }

        /** @var Twig $twig */
        $twig = $this->grav['twig'];

        $twig->twig()->addFunction(
            new Twig_SimpleFunction('render_image', [$this, 'render_image'])
        );
        $twig->twig()->addFunction(
            new Twig_SimpleFunction('create_image', [$this, 'create_image'])
        );
        $twig->twig()->addFunction(
            new Twig_SimpleFunction('create_image_from_path', [$this, 'create_image_from_path'])
        );
    }
}
