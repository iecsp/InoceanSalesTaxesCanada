<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Service;

use InoceanSalesTaxesCanada\Config\CanadianProvince;
use InoceanSalesTaxesCanada\Config\Constants;
use InoceanSalesTaxesCanada\Config\TaxType;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class TaxConfigService
{
    private const CONFIG_PREFIX = 'InoceanSalesTaxesCanada.config.';

    /**
     * Currency has two decimals, so rounding tax to fewer than two is never
     * meaningful; more than four is noise.
     */
    private const MIN_TAX_DECIMALS = 2;

    private const MAX_TAX_DECIMALS = 4;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getTaxRate(TaxType $taxType, CanadianProvince $province, ?string $salesChannelId = null): float
    {
        $configFieldName = $taxType->getConfigFieldName($province);

        if (!$configFieldName) {
            // Not a merchant mistake: this is a hole in TaxType::getConfigFieldName().
            // Its only symptom would be getProvinceTaxRates() silently dropping a
            // whole tax band, so it must not pass unnoticed.
            $this->logger->error('Canada tax: no configuration field mapped for tax type in province, tax band dropped.', [
                'taxType' => $taxType->value,
                'province' => $province->value,
            ]);

            return 0.0;
        }

        $configValue = $this->systemConfigService->get(self::CONFIG_PREFIX . $configFieldName, $salesChannelId);

        return $this->parseTaxRate($configValue, $configFieldName);
    }

    /**
     * @return array<string, float> tax name => percent, zero bands omitted
     */
    public function getProvinceTaxRates(CanadianProvince $province, ?string $salesChannelId = null): array
    {
        $taxRates = [];

        foreach ($province->getTaxTypes() as $taxType) {
            $rate = $this->getTaxRate($taxType, $province, $salesChannelId);
            if ($rate > 0) {
                $taxRates[$taxType->value] = $rate;
            }
        }

        return $taxRates;
    }

    /**
     * GST is federal and uniform across Canada. HST provinces have no province
     * level GST field, so goods taxed at the federal rate only (the "(CA) GST
     * only" tax category) need this channel-wide value.
     */
    public function getFederalGstRate(?string $salesChannelId = null): float
    {
        $configValue = $this->systemConfigService->get(self::CONFIG_PREFIX . 'TaxGstFederal', $salesChannelId);
        $rate = $this->parseTaxRate($configValue, 'TaxGstFederal');

        return $rate > 0 ? $rate : (float) Constants::FEDERAL_GST_RATE;
    }

    /**
     * @return array<int, string>
     */
    public function getTaxNameByProvince(string $provinceCode): array
    {
        $province = CanadianProvince::tryFrom(substr(strtoupper($provinceCode), -2));

        if (!$province) {
            return [];
        }

        return array_map(static fn (TaxType $t) => $t->value, $province->getTaxTypes());
    }

    public function isFreightTaxable(?string $salesChannelId = null): bool
    {
        return (bool) $this->systemConfigService->get(self::CONFIG_PREFIX . 'FreightTaxable', $salesChannelId);
    }

    public function getTaxDecimals(?string $salesChannelId = null): int
    {
        $value = $this->systemConfigService->get(self::CONFIG_PREFIX . 'TaxDecimals', $salesChannelId);

        if (!is_numeric($value)) {
            return self::MIN_TAX_DECIMALS;
        }

        return max(self::MIN_TAX_DECIMALS, min(self::MAX_TAX_DECIMALS, (int) $value));
    }

    /**
     * Reads a rate an administrator typed into a config field.
     *
     * This runs inside the cart calculation, and Shopware's TaxProviderProcessor
     * re-throws anything a tax provider throws as a hard checkout failure. So a
     * typo in the admin must never become a store outage: accept what a human
     * plausibly means (comma separator, extra decimals, surrounding space), and
     * for anything genuinely unusable log loudly and fall back to zero.
     */
    private function parseTaxRate(mixed $value, string $configFieldName): float
    {
        if ($value === null || $value === '' || $value === false) {
            return 0.0;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        if (!is_numeric($normalized)) {
            $this->logger->error('Canada tax: configured tax rate is not a number, falling back to 0%.', [
                'field' => $configFieldName,
                'value' => $normalized,
            ]);

            return 0.0;
        }

        $rate = (float) $normalized;

        if ($rate < 0 || $rate > 100) {
            $this->logger->error('Canada tax: configured tax rate is out of the 0-100 range, falling back to 0%.', [
                'field' => $configFieldName,
                'value' => $rate,
            ]);

            return 0.0;
        }

        return round($rate, 3);
    }
}
