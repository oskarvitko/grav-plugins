<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Events\FlexRegisterEvent;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Twig\Twig;
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
            return;
        }

        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onSitemapProcessed' => ['onSitemapProcessed'],
        ]);
    }

    public function onPageInitialized()
    {
        $parentRoute = $this->config->get('plugins.product-catalog.parent_route');
        $parentAccepted = $this->config->get('plugins.product-catalog.parent_accepted', 1) == 1;
        $renderRoute = $this->config->get('plugins.product-catalog.render_route');
        $notFoundRoute = $this->config->get('plugins.product-catalog.not_found_route', '/404');

        $uri = $this->grav['uri'];
        $path = $uri->path();

        if (str_starts_with($path, $parentRoute)) {
            $id = substr($path, strlen($parentRoute));
            $id = ltrim($id, '/');

            if (!$id) {
                if (!$parentAccepted) {
                    $this->grav->redirect($notFoundRoute);
                }
            } else {
                /** @var Flex $flex */
                $flex = $this->grav['flex_objects'];
                $directory = $flex->getDirectory('product');
                /** @var ProductCollection $collection */
                $collection = $directory->getCollection();

                /** @var ProductObject $flex */
                $product = $collection->get($id);

                if ($product) {
                    /** @var Twig $twig */
                    $twig = $this->grav['twig'];
                    $twig->twig_vars['product'] = $product;
                    /** @var Page $found */
                    $page = $this->grav['pages']->find($renderRoute);
                    $page->header()->title = $product->getProperty('name');
                    unset($this->grav['page']);
                    $this->grav['page'] = $page;
                } else {
                    $this->grav->redirect($notFoundRoute);
                }
            }
        }
    }

    public function onSitemapProcessed(Event $e)
    {
        $parentRoute = $this->config->get('plugins.product-catalog.parent_route');
        $parentAccepted = $this->config->get('plugins.product-catalog.parent_accepted', 1) == 1;

        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];
        $directory = $flex->getDirectory('product');

        /** @var ProductObject[] $products */
        $products = $directory->getCollection();

        foreach ($products as $product) {
            $date = date('Y-m-d', $product->getTimestamp());
            $this->addSiteMapEntry($e, $product->getUrl(), $date, '0.8');
        }

        if (!$parentAccepted) {
            $sitemap = $e['sitemap'];
            unset($sitemap[$parentRoute]);
            $e['sitemap'] = $sitemap;
        }
    }

    protected function addSiteMapEntry($event, $route, $lastmod, $priority)
    {
        $sitemap = $event['sitemap'];
        $location = \Grav\Common\Utils::url($route, true);
        $sitemap[$route] = new \Grav\Plugin\Sitemap\SitemapEntry($location, $lastmod, 'weekly', $priority);
        $event['sitemap'] = $sitemap;
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
    }
}
