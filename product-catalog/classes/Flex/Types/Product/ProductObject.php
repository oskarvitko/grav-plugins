<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Plugin\ProductCatalog\Flex\Types\Product;

use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Plugin\ProductCatalogPlugin;
use Grav\Plugin\ProductCatalogPricesPlugin;

/**
 * Class ProductObject
 * @package Grav\Common\Flex\Generic
 *
 * @extends FlexObject<string,GenericObject>
 */
class ProductObject extends GenericObject
{
    public function getVariantsPrice()
    {
        $variants = $this->getProperty('variants');

        if (!$variants) {
            return [];
        }

        $params = ProductCatalogPlugin::getProductTypeParams($this->getProperty('type'));
        $paramKeys = array_keys($params);

        $result = [];

        foreach ($variants as $variant) {
            $path = [];

            foreach ($variant['params'] ?? [] as $param) {
                $paramIndex = array_search($param['param'], $paramKeys);
                $path[$paramIndex] = $param['value'];
            }

            $result[implode('-', $path)] = $variant['price'];
        }

        return $result;
    }

    public function getParams()
    {
        $variants = $this->getProperty('variants');

        if (!$variants) {
            return [];
        }

        $params = [];

        foreach ($variants as $variant) {
            foreach ($variant['params'] ?? [] as $param) {
                $key = $param['param'];
                $value = $param['value'];

                if (!array_key_exists($key, $params)) {
                    $params[$key] = [];
                }

                if (!in_array($value, $params[$key])) {
                    array_push($params[$key], $value);
                }
            }
        }

        return $params;
    }

    public function getCategory()
    {
        $grav = Grav::instance();
        if ($grav['config']['plugins']['product-catalog']['category_enabled']) {
            $categories = $this->getProperty('category');

            if (isset($categories) && is_array($categories)) {
                $categoryId = end($categories);
                if ($categoryId) {
                    $directory = $grav['flex_objects']->getDirectory('category');
                    if (!$directory) {
                        return null;
                    }

                    return $directory->getCollection()->get($categoryId);
                }
            }
        }

        return null;
    }

    public function getUrl()
    {
        $grav = Grav::instance();
        $parentRoute = $grav['config']['plugins']['product-catalog']['product_parent_route'];
        $slug = $this->getProperty('slug');
        $urlSlug = $slug ? $slug : $this->getKey();

        $parts = [$parentRoute];

        $category = $this->getCategory();

        if ($category) {
            array_push($parts, $category->getProperty('slug') ? $category->getProperty('slug') : $category->getKey());
        }

        array_push($parts, $urlSlug);

        return join("/", $parts) . '/';
    }

    public function applyPrice()
    {
        $grav = Grav::instance();
        /** @var Plugins $plugins */
        $plugins = $grav['plugins'];
        /** @var ProductCatalogPricesPlugin $pricePlugin */
        $pricePlugin = $plugins->getPlugin('product-catalog-prices');
        if ($pricePlugin) {
            $itemKey = $this->getKey();
            $priceList = $pricePlugin->getPriceList();

            if (isset($priceList) && array_key_exists($itemKey, $priceList)) {
                $priceInfo = $priceList[$itemKey];

                if ($priceInfo) {
                    foreach ($priceInfo as $path => $price) {
                        $this->setNestedProperty($path, $price, $pricePlugin->pathSeparator);
                    }
                }
            }
        }

        return $this;
    }
}
