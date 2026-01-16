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
        foreach ($this as $item) {
            if (method_exists($item, 'applyPrice')) {
                $item->applyPrice();
            }
        }

        return $this;
    }

    public function applyFilters($filters)
    {
        $collection = $this;

        $filterExists = function ($key) use (&$filters) {
            return array_key_exists($key, $filters) && isset($filters[$key]) && count($filters[$key]);
        };

        $filtrateIn = function ($collection, $key, $prop) use ($filterExists, $filters) {
            if ($filterExists($key)) {
                return $collection->filter(function ($product) use ($filters, $key, $prop) {
                    $value = $product->getProperty($prop);
                    if (is_array($value)) {
                        return count(array_intersect($filters[$key], $value)) > 0;
                    }

                    return in_array($value, $filters[$key]);
                });
            }

            return $collection;
        };

        $filtrateInRange = function ($collection, $fromKey, $toKey, $prop) use ($filterExists, $filters) {
            if ($filterExists($fromKey) && $filterExists($toKey)) {
                $from = $filters[$fromKey][0];
                $to = $filters[$toKey][0];

                return $collection->filter(function ($product) use ($from, $to, $prop) {
                    $propValue = $product->getProperty($prop);
                    return $from <= $propValue && $propValue <= $to;
                });
            }

            return $collection;
        };

        $collection = $filtrateIn($collection, 'f_cat', 'category');
        $collection = $filtrateIn($collection, 'f_profil', 'profil');
        $collection = $filtrateIn($collection, 'f_width', 'width');
        $collection = $filtrateIn($collection, 'f_height', 'height');
        $collection = $filtrateIn($collection, 'f_length', 'length');
        $collection = $filtrateInRange($collection, 'f_min', 'f_max', 'price');

        return $collection;
    }
}