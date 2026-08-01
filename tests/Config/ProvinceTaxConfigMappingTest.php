<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Tests\Config;

use InoceanSalesTaxesCanada\Config\CanadianProvince;
use InoceanSalesTaxesCanada\Config\TaxType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * TaxType::getConfigFieldName() is a hand-written table of province/tax-type
 * pairs. A hole in it does not raise anything: the rate resolves to 0 and
 * getProvinceTaxRates() drops the band, so the shop quietly under-collects a
 * whole tax for one province until somebody audits the books.
 *
 * These tests make that failure mode loud at build time instead.
 */
#[CoversClass(TaxType::class)]
#[CoversClass(CanadianProvince::class)]
class ProvinceTaxConfigMappingTest extends TestCase
{
    /**
     * Config fields that exist on their own and are not reachable through a
     * province/tax-type pair.
     */
    private const STANDALONE_FIELDS = ['TaxGstFederal'];

    public function testEveryProvinceTaxTypeMapsToAConfigField(): void
    {
        foreach (CanadianProvince::cases() as $province) {
            foreach ($province->getTaxTypes() as $taxType) {
                static::assertNotNull(
                    $taxType->getConfigFieldName($province),
                    \sprintf(
                        'Province %s declares tax type %s but TaxType::getConfigFieldName() has no field for it; '
                        . 'that band would be silently dropped and never charged.',
                        $province->value,
                        $taxType->value
                    )
                );
            }
        }
    }

    public function testEveryMappedConfigFieldIsDeclaredInConfigXml(): void
    {
        $declared = $this->configXmlFieldNames();

        foreach (CanadianProvince::cases() as $province) {
            foreach ($province->getTaxTypes() as $taxType) {
                $field = $taxType->getConfigFieldName($province);

                static::assertContains(
                    $field,
                    $declared,
                    \sprintf(
                        'Config field "%s" (%s/%s) is not declared in config.xml, so it can never hold a rate.',
                        (string) $field,
                        $province->value,
                        $taxType->value
                    )
                );
            }
        }
    }

    public function testEveryTaxFieldInConfigXmlIsReachable(): void
    {
        $reachable = self::STANDALONE_FIELDS;

        foreach (CanadianProvince::cases() as $province) {
            foreach ($province->getTaxTypes() as $taxType) {
                $field = $taxType->getConfigFieldName($province);
                if ($field !== null) {
                    $reachable[] = $field;
                }
            }
        }

        foreach ($this->configXmlFieldNames() as $field) {
            if (!str_starts_with($field, 'Tax') || $field === 'TaxDecimals') {
                continue;
            }

            static::assertContains(
                $field,
                $reachable,
                \sprintf('Config field "%s" is declared in config.xml but no province/tax-type pair reads it.', $field)
            );
        }
    }

    public function testEveryProvinceDeclaresAtLeastOneTaxType(): void
    {
        foreach (CanadianProvince::cases() as $province) {
            static::assertNotEmpty(
                $province->getTaxTypes(),
                \sprintf('Province %s declares no tax types and would never be taxed.', $province->value)
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function configXmlFieldNames(): array
    {
        $path = __DIR__ . '/../../src/Resources/config/config.xml';
        static::assertFileExists($path);

        $xml = simplexml_load_file($path);
        static::assertNotFalse($xml, 'config.xml is not parsable');

        $names = [];
        foreach ($xml->xpath('//input-field/name') ?: [] as $name) {
            $names[] = (string) $name;
        }

        static::assertNotEmpty($names, 'config.xml declares no input fields');

        return $names;
    }
}
