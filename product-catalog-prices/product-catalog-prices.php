<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Cache;
use Grav\Common\Flex\FlexCollection;
use Grav\Common\Flex\FlexObject;
use Grav\Common\Plugin;
use Monolog\Logger;
use Grav\Plugin\FlexObjects\Flex;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class ProductCatalogPricesPlugin
 * @package Grav\Plugin
 */
class ProductCatalogPricesPlugin extends Plugin
{
    public string $pathSeparator = '.';
    public string $arraySeparator = '$';
    protected string $cacheKey = 'product-catalog-prices.prices';

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
            'onFlexObjectAfterSave' => ['onFlexObjectAfterSave', 0]
        ];
    }

    public function onFlexObjectAfterSave($event)
    {
        /** @var FlexObject $object */
        $object = $event['object'];

        $objectType = $object->getFlexType();
        $configurations = $this->config->get('plugins.product-catalog-prices.collections');
        $acceptedCollections = array_map(function ($item) {
            return $item['collection'];
        }, $configurations);

        if (in_array($objectType, $acceptedCollections)) {
            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            $cache->delete($this->cacheKey);
        }
    }

    protected function getPriceId(FlexObject $item, string $path)
    {
        $segments = explode($this->pathSeparator, $path);

        $segment = array_shift($segments);

        $result = [];

        $recurse = function ($currentData, $segments, $value_path) use (&$recurse, &$result) {
            if (empty($segments)) {
                $result[$value_path] = $currentData;
                return;
            }

            $segment = array_shift($segments);

            if (is_array($currentData)) {
                foreach ($currentData as $index => $item) {
                    if (is_array($item) && array_key_exists($segment, $item)) {
                        $next_value_path = $value_path . $this->arraySeparator . $index . $this->pathSeparator . $segment;
                        $recurse($item[$segment], $segments, $next_value_path);
                    }
                }
            }
        };

        $prefix = $item->getFlexType() . $this->pathSeparator . $item->getKey() . $this->pathSeparator . $segment;
        $recurse($item->getProperty($segment), $segments, $prefix);

        return $result;
    }

    protected function getPriceIds()
    {
        $configurations = $this->config->get('plugins.product-catalog-prices.collections');

        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'];

        $allPrices = [];

        foreach ($configurations as $configuration) {
            $directory = $flex->getDirectory($configuration['collection']);
            if (!$directory) {
                continue;
            }


            /** @var FlexCollection $items */
            $items = $directory->getCollection();

            foreach ($items as $item) {
                foreach ($configuration['paths'] as $pricePath => $priceKey) {
                    $prices = $this->getPriceId($item, $pricePath);
                    $allPrices = array_merge($allPrices, $prices);
                }
            }
        }

        return $allPrices;
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


    public function getPriceList()
    {
        if (!$this->config) {
            return [];
        }

        $apiUrl = $this->config->get('plugins.product-catalog-prices.api_url');
        $cacheTime = $this->config->get('plugins.product-catalog-prices.cache_time') || 120;
        /** @var Cache $cache */
        $cache = $this->grav['cache'];
        $priceKey = 'price_rb';

        $cached = $cache->fetch($this->cacheKey);

        if ($cached) {
            return $cached;
        }

        $ids = $this->getPriceIds();
        $data = [
            "ids" => array_values($ids)
        ];

        $body = json_encode($data);
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body)
        ]);

        $response = curl_exec($ch);
        $responseData = array_reduce(json_decode($response, true), function ($result, $item) {
            $result[$item['product_id']] = $item;
            return $result;
        }, []);

        $result = [];

        foreach ($ids as $itemKey => $id) {
            if (array_key_exists($id, $responseData)) {
                $product = $responseData[$id];
                $keyParts = explode($this->pathSeparator, $itemKey);
                array_shift($keyParts);
                $itemId = array_shift($keyParts);
                $propertyPath = str_replace($this->arraySeparator, $this->pathSeparator, implode($this->pathSeparator, $keyParts));

                if (!array_key_exists($itemId, $result)) {
                    $result[$itemId] = [];
                }

                $result[$itemId][$propertyPath] = $product[$priceKey];
            }
        }

        $cache->save($this->cacheKey, $result, $cacheTime * 60);

        return $result;
    }
}
