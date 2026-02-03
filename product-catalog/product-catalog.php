<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Events\FlexRegisterEvent;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Twig\Twig;
use Grav\Events\PageEvent;
use Grav\Framework\RequestHandler\Exception\NotFoundException;
use Grav\Framework\RequestHandler\Exception\RequestException;
use Grav\Plugin\Admin\Admin;
use Grav\Plugin\Admin\AdminController;
use Grav\Plugin\FlexObjects\Flex;
use Grav\Plugin\ProductCatalog\Flex\Types\Product\ProductCollection;
use Grav\Plugin\ProductCatalog\Flex\Types\Product\ProductObject;
use Monolog\Logger;

/**
 * Class ProductCatalogPlugin
 * @package Grav\Plugin
 */
class ProductCatalogPlugin extends Plugin
{
    /** @var AdminController */
    protected $controller;
    /** @var Admin */
    protected $admin;
    public $features = [
        'blueprints' => 0,
    ];

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
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ],
            FlexRegisterEvent::class => [['onRegisterFlex', 1]],
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

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            $this->enable([
                'onAssetsInitialized' => ['onAssetsInitialized', 0],
            ]);

            return;
        }

        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onSitemapProcessed' => ['onSitemapProcessed'],
        ]);
    }

    public function onAssetsInitialized()
    {
        $this->grav['assets']->addJs('plugins://product-catalog/assets/js/live-slug.js');
    }

    protected function handleNotFound($notFoundRoute)
    {
        if ($notFoundRoute) {
            return $this->grav->redirect($notFoundRoute);
        }

        $request = $this->grav['request'];
        $exception = new RequestException($request, 'Page Not Found', 404);

        $event = new PageEvent([
            'page' => null,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'exception' => $exception,
            'route' => '/asd',
            'request' => $request
        ]);

        $this->grav->fireEvent('onPageNotFound', $event);

        if (isset($event->page)) {
            unset($this->grav['page']);
            $this->grav['page'] = $event->page;
        }
    }

    protected function handleItemRouting(
        $path,
        $parentRoute,
        $parentAccepted,
        $renderRoute,
        $notFoundRoute,
        $collectionName,
        $objectName
    ) {
        $id = substr($path, strlen($parentRoute));
        $id = rtrim($id, '/');
        $id = explode('/', $id);
        $id = end($id);

        if (!$id) {
            if (!$parentAccepted) {
                return $this->handleNotFound($notFoundRoute);
            }
        } else {
            /** @var Flex $flex */
            $flex = $this->grav['flex_objects'];
            $directory = $flex->getDirectory($collectionName);
            /** @var ProductCollection $collection */
            $collection = $directory->getCollection();

            /** @var ProductObject $flex */
            $item = $collection->find($id, 'slug');
            if (!$item) {
                $item = $collection->get($id);
            }

            if ($item) {
                /** @var Twig $twig */
                $twig = $this->grav['twig'];
                $twig->twig_vars[$objectName] = $item;
                /** @var Page $found */
                $page = $this->grav['pages']->find($renderRoute);
                $page->header()->title = $item->getProperty('name');
                unset($this->grav['page']);

                $metadata = $item->getProperty('meta');

                if ($metadata && is_array($metadata)) {
                    $baseMetadata = $page->metadata();

                    foreach ($metadata as $key => $value) {
                        $metadata[$key] = [
                            'name' => $key,
                            'content' => $value
                        ];
                    }

                    $page->metadata(array_merge($baseMetadata, $metadata));
                }

                $this->grav['page'] = $page;
            } else {
                return $this->handleNotFound($notFoundRoute);
            }
        }
    }

    protected function getConfig($name)
    {
        $config = $this->config->get('plugins.product-catalog');
        $result = [];

        foreach ($config as $key => $value) {
            if (str_starts_with($key, $name)) {
                $result[str_replace($name . '_', '', $key)] = $value;
            }
        }

        return $result;
    }

    public function onPageInitialized()
    {
        $productConfig = $this->getConfig('product');
        $categoryConfig = $this->getConfig('category');

        $uri = $this->grav['uri'];
        $path = $uri->path();
        if ($path === $productConfig['render_route']) {
            return $this->handleNotFound($productConfig['not_found_route']);
        }

        if (str_starts_with($path, $productConfig['parent_route'])) {
            return $this->handleItemRouting(
                $path,
                $productConfig['parent_route'],
                $productConfig['parent_accepted'],
                $productConfig['render_route'],
                $productConfig['not_found_route'],
                'product',
                'product',
            );
        }

        if ($categoryConfig['enabled']) {
            if (str_starts_with($path, $categoryConfig['parent_route'])) {
                return $this->handleItemRouting(
                    $path,
                    $categoryConfig['parent_route'],
                    $categoryConfig['parent_accepted'],
                    $categoryConfig['render_route'],
                    $categoryConfig['not_found_route'],
                    'category',
                    'category',
                );
            }
        }
    }

    public function onSitemapProcessed(Event $e)
    {
        $configs = [
            ['type' => 'product', 'config' => $this->getConfig('product')],
            ['type' => 'category', 'config' => $this->getConfig('category')],
        ];

        $sitemap = $e['sitemap'];

        foreach ($configs as $config) {
            if (isset($config['config']['enabled']) && !$config['config']['enabled']) {
                continue;
            }

            /** @var Flex $flex */
            $flex = $this->grav['flex_objects'];
            $directory = $flex->getDirectory($config['type']);

            /** @var ProductObject[] $items */
            $items = $directory->getCollection();

            foreach ($items as $item) {
                $date = date('Y-m-d', $item->getTimestamp());
                $sitemap = $this->addSiteMapEntry($sitemap, $item->getUrl(), $date, '0.75');
            }

            if (!$config['config']['parent_accepted']) {
                unset($sitemap[$config['config']['parent_route']]);
            }

            if ($config['config']['render_route'] !== $config['config']['parent_route']) {
                unset($sitemap[$config['config']['render_route']]);
            }
        }

        $e['sitemap'] = $sitemap;
    }

    protected function addSiteMapEntry($sitemap, $route, $lastmod, $priority)
    {
        $location = \Grav\Common\Utils::url($route, true);
        $sitemap[$route] = new \Grav\Plugin\Sitemap\SitemapEntry($location, $lastmod, 'daily', $priority);
        return $sitemap;
    }

    protected function debug($message)
    {
        /** @var Logger $logger */
        $logger = $this->grav['log'];
        $logger->addDebug($message, ['ProductCatalogPlugin']);
    }

    public function onRegisterFlex($event): void
    {
        $flex = $event->flex;

        $flex->addDirectoryType(
            'product',
            'blueprints://flex-objects/product.yaml'
        );

        if ($this->getConfig('category')['enabled']) {
            $flex->addDirectoryType(
                'category',
                'blueprints://flex-objects/category.yaml'
            );
        }

        if ($this->getConfig('extra_product')['enabled']) {
            $flex->addDirectoryType(
                'extra-product',
                'blueprints://flex-objects/extra-product.yaml'
            );
        }

        if ($this->getConfig('review')['enabled']) {
            $flex->addDirectoryType(
                'review',
                'blueprints://flex-objects/review.yaml'
            );
        }
    }

    public static function getCategories()
    {
        $grav = Grav::instance();
        /** @var Flex $flex */
        $flex = $grav['flex_objects'];
        $directory = $flex->getDirectory('category');
        $categories = $directory->getCollection();

        $result = [];

        foreach ($categories as $category) {
            $result[$category->getKey()] = $category->getProperty('name');
        }

        return $result;
    }
}
