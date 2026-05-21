<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Plugin\ProductCatalog\Flex\Types\Product;

use Grav\Common\Flex\Types\Generic\GenericCollection;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Plugin\ProductCatalog\Flex\Types\BaseCatalogCollection;
use Grav\Plugin\ProductCatalogPricesPlugin;


class ProductCollection extends BaseCatalogCollection
{
    public function applyPrice()
    {
        foreach ($this as $item) {
            if (method_exists($item, 'applyPrice')) {
                $item->applyPrice();
            }
        }

        return $this;
    }
}