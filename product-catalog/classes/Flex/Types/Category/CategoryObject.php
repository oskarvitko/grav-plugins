<?php
declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */



namespace Grav\Plugin\ProductCatalog\Flex\Types\Category;

use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Grav;

/**
 * Class ProductObject
 * @package Grav\Common\Flex\Generic
 *
 * @extends FlexObject<string,GenericObject>
 */
class CategoryObject extends GenericObject
{
    public function getUrl()
    {
        $parentRoute = Grav::instance()['config']['plugins']['product-catalog']['category_parent_route'];
        $slug = $this->getProperty('slug');
        $urlSlug = $slug ? $slug : $this->getKey();

        return join('/', [$parentRoute, $urlSlug]) . '/';
    }
}