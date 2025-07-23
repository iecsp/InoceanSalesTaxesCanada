<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use InoceanSalesTaxesCanada\Service\TaxConfigService;

class TaxNameExtension extends AbstractExtension
{
    private TaxConfigService $taxConfigService;

    public function __construct(TaxConfigService $taxConfigService)
    {
        $this->taxConfigService = $taxConfigService;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('getTaxName', [$this, 'getTaxName']),
        ];
    }

    public function getTaxName(string $proviceCode): array
    {
        
        return $this->taxConfigService->getTaxNameByProvince($proviceCode);

    }

}