<?php declare(strict_types=1);

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
