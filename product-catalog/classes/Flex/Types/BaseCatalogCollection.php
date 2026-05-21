<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Plugin\ProductCatalog\Flex\Types;

use Grav\Common\Flex\Types\Generic\GenericCollection;
use Grav\Common\Grav;
use Grav\Plugin\ProductCatalog\Flex\Types\FilterCriteria;

/**
 * Class ProductCollection
 * @package Grav\Common\Flex\Generic
 *
 * @extends FlexCollection<string,GenericObject>
 */

class BaseCatalogCollection extends GenericCollection
{
    public function filterBy(array $filters)
    {
        $builder = new FilterCriteria();
        $predicate = $builder->buildPredicate($filters);

        /** @phpstan-var static */
        return $this->filter($predicate);
    }


    public function filterByVariantParams(array $params): static
    {
        $params = array_filter($params, fn($values) => !empty($values));

        if (empty($params)) {
            return $this;
        }

        return $this->filter(function ($item) use ($params) {
            $variants = $item->getProperty('variants') ?? [];

            foreach ($variants as $variant) {
                $variantMap = [];
                foreach ($variant['params'] ?? [] as $p) {
                    $variantMap[$p['param']] = $p['value'];
                }

                $matches = true;
                foreach ($params as $paramKey => $allowedValues) {
                    if (!isset($variantMap[$paramKey]) || !in_array($variantMap[$paramKey], $allowedValues, true)) {
                        $matches = false;
                        break;
                    }
                }

                if ($matches) {
                    return true;
                }
            }

            return false;
        });
    }

    public function byType($type)
    {
        $grav = Grav::instance();
        $productTypes = $grav['config']['plugins']['product-catalog']['product_types'] ?? [];
        $defaultType = array_key_first($productTypes) ?? '';

        if ($type === "undefined") {
            return $this->filterBy([
                'type' => [
                    'notIn' => array_keys($productTypes)
                ]
            ]);
        }

        $filters = [
            'or' => [
                ['type' => $type]
            ]
        ];

        if ($type === $defaultType) {
            array_push($filters['or'], ['type' => ['isMissingOrNull' => true]]);
        }

        return $this->filterBy($filters);
    }
}