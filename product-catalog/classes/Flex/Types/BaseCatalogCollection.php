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


    public function byType($type)
    {
        $filters = [
            'or' => [
                ['type' => $type]
            ]
        ];

        if ($type === 'teplica') {
            array_push($filters['or'], ['type' => ['isMissingOrNull' => true]]);
        }

        return $this->filterBy($filters);
    }
}