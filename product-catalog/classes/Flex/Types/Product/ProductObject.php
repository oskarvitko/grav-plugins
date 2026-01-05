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
        $parentRoute = \Grav\Common\Grav::instance()['config']['plugins']['product-catalog']['parent_route'];

        return $parentRoute . '/' . $this->getKey();
    }
}
