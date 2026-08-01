<?php declare(strict_types=1);

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