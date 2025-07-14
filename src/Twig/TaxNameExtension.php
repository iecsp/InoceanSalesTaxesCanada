<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TaxNameExtension extends AbstractExtension
{

    public function getFilters(): array
    {
        return [
            new TwigFilter('getTaxName', [$this, 'getTaxName']),
        ];
    }

    public function getTaxName(float $value, string $language = 'fr'): string
    {
        
        $taxNames['fr'] = [
            0 => 'Sans taxe',
            5 => 'TPS',
            6 => 'TVP',
            7 => 'TVP',
            9.975 => 'TVQ',
            13 => 'TVH',
            14 => 'TVH',
            15 => 'TVH',
        ];

        $taxNames['en'] = [
            0 => 'NO-TAX',
            5 => 'GST',
            6 => 'PST',
            7 => 'PST',
            9.975 => 'QST',
            13 => 'HST',
            14 => 'HST',
            15 => 'HST',
        ];

        return $taxNames[$language][$value] ?? $taxNames['en'][$value] ?? 'Tax';
    }

}