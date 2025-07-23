<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;

class CanadaCalculatedTaxCollection extends CalculatedTaxCollection
{
    /**
     * @param CalculatedTax[] $elements
     */
    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function add($element): void
    {
        $this->elements[] = $element;
    }

    protected function getElementKey(CalculatedTax $element): string
    {
        return spl_object_hash($element);
    }
}
