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
use Grav\Plugin\ProductCatalogPricesPlugin;

/**
 * Class ProductCollection
 * @package Grav\Common\Flex\Generic
 *
 * @extends FlexCollection<string,GenericObject>
 */
class ProductCollection extends GenericCollection
{
    public function applyPrice()
    {
        $this->forAll(function ($itemKey, $item) {
            if (method_exists($item, 'applyPrice')) {
                $item->applyPrice();
            }
        });

        return $this;
    }
}
