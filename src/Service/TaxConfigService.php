<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada\Service;

use InoceanSalesTaxesCanada\Config\CanadianProvince;
use InoceanSalesTaxesCanada\Config\TaxType;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class TaxConfigService
{
    private SystemConfigService $systemConfigService;
    
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }
    
    public function getTaxRate(TaxType $taxType, CanadianProvince $province, ?string $salesChannelId = null): float
    {
        $configFieldName = $taxType->getConfigFieldName($province);
        
        if (!$configFieldName) {
            return 0.0;
        }
        
        $configKey = 'InoceanSalesTaxesCanada.config.' . $configFieldName;
        $configValue = $this->systemConfigService->get($configKey, $salesChannelId);
        
        return $this->validateAndConvertTaxRate($configValue);
    }
    
    public function getProvinceTaxRates(CanadianProvince $province, ?string $salesChannelId = null): array
    {
        $taxRates = [];
        $taxTypes = $province->getTaxTypes();
        
        foreach ($taxTypes as $taxType) {
            $rate = $this->getTaxRate($taxType, $province, $salesChannelId);
            if ($rate > 0) {
                $taxRates[$taxType->value] = $rate;
            }
        }
        
        return $taxRates;
    }

    public function getTaxNameByProvince(string $provinceCode): array
    {
        $province = CanadianProvince::tryFrom(substr(strtoupper($provinceCode), -2));
        
        if (!$province) {
            return [];
        }

        $taxTypes = $province->getTaxTypes();

        return array_map(fn(TaxType $t) => $t->value, $taxTypes);
    }

    private function validateAndConvertTaxRate($value): float
    {
        if (empty($value)) {
            return 0.0;
        }
        
        $stringValue = (string) $value;
        $stringValue = trim($stringValue);
        
        if (!preg_match('/^\d+(\.\d{1,3})?$/', $stringValue)) {
            throw new \InvalidArgumentException("Invalid tax rate format: {$stringValue}. Expected format: 12.345");
        }
        
        $floatValue = (float) $stringValue;
        
        if ($floatValue < 0 || $floatValue > 100) {
            throw new \InvalidArgumentException("Tax rate must be between 0 and 100, got: {$floatValue}");
        }
        
        return round($floatValue, 3);
    }
    
    // Maybe it will be added in the next version.
    // public function isTaxBreakdownEnabled(?string $salesChannelId = null): bool
    // {
    //     return (bool) $this->systemConfigService->get('InoceanSalesTaxesCanada.config.TaxBreakdown', $salesChannelId);
    // }
    
    public function isFreightTaxable(?string $salesChannelId = null): bool
    {
        return (bool) $this->systemConfigService->get('InoceanSalesTaxesCanada.config.FreightTaxable', $salesChannelId);
    }

}
