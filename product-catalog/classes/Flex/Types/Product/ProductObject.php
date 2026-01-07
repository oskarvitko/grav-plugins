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
use Grav\Plugin\ProductCatalogPricesPlugin;

/**
 * Class ProductObject
 * @package Grav\Common\Flex\Generic
 *
 * @extends FlexObject<string,GenericObject>
 */
class ProductObject extends GenericObject
{
    public function getUrl()
    {
        $parentRoute = Grav::instance()['config']['plugins']['product-catalog']['parent_route'];

        return $parentRoute . '/' . $this->getKey();
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
