<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Framework\Struct\ArrayEntity;
use InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax\Struct\CanadaCalculatedTaxCollection;
use InoceanSalesTaxesCanada\Config\CanadianProvince;
use InoceanSalesTaxesCanada\Service\TaxConfigService;
use InoceanSalesTaxesCanada\Config\Constants;

class CanadaTaxProvider extends AbstractTaxProvider
{

    private TaxConfigService $taxConfigService;

    public function __construct(TaxConfigService $taxConfigService)
    {
        $this->taxConfigService = $taxConfigService;
    }

    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {

        $lineItemTaxes = [];
        $finalCartTaxes = [];

        $freightTaxable = $this->taxConfigService->isFreightTaxable($context->getSalesChannelId());

        $address = $context->getShippingLocation()->getAddress();
        if (!$address || strtoupper($address->getCountry()?->getIso()) !== Constants::DEFAULT_COUNTRY) {
            return new TaxProviderResult([]);
        }

        $proviceShortCode = substr(strtoupper($address->getCountryState()->getShortCode()), -2);
        $province = CanadianProvince::from($proviceShortCode ?? Constants::DEFAULT_PROVINCE);

        foreach ($cart->getLineItems() as $lineItem) {

            $originalTaxRate = $lineItem->getPrice()->getCalculatedTaxes()->first()?->getTaxRate() ?? $this->getDefaultRateByTaxType('TAX-FREE');

            if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[3]['id']) {
                $taxRates = [$this->getDefaultRateByTaxType('TAX-FREE')];
            } else if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[2]['id']) {
                $taxRates = [$this->getDefaultRateByTaxType('GST-ONLY')];
            } elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[1]['id']) {
                $taxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
            } elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[0]['id']) {
                $taxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
            } else {
                $taxRates = [$originalTaxRate];
            }

            $price = $lineItem->getPrice()->getTotalPrice();
            $calculatedTaxes = [];

            foreach ($taxRates as $taxName => $taxRate) {
                $tax = $price * $taxRate / 100;
                $calculatedTax = new CalculatedTax($tax, $taxRate, $price);
                $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
                $calculatedTaxes[] = $calculatedTax;
                $finalCartTaxes[] = $calculatedTax;
            }

            $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new CanadaCalculatedTaxCollection($calculatedTaxes);
        }

        $calculatedDeliveryTaxes = [];
        $deliveryTaxes = [];
        $shippingTotalPrice = $cart->getShippingCosts()->getTotalPrice() ?? 0;

        if ($freightTaxable) {
            foreach ($cart->getDeliveries() as $delivery) {
                $calculatedDeliveryTaxes = [];
                foreach ($delivery->getPositions() as $position) {
                    $shippingMethod = $delivery->getShippingMethod();
                    $taxId = $shippingMethod->getTaxId();
                    $originalDeliveryTaxRate = $shippingMethod->getTax()?->getTaxRate() ?? $this->getDefaultRateByTaxType('TAX-FREE');

                    if ($taxId === Constants::TAXES[3]['id']) {
                        $deliveryTaxRates = [$this->getDefaultRateByTaxType('TAX-FREE')];
                    } else if ($taxId === Constants::TAXES[2]['id']) {
                        $deliveryTaxRates = [$this->getDefaultRateByTaxType('GST-ONLY')];
                    } elseif ($taxId === Constants::TAXES[1]['id']) {
                        $deliveryTaxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
                    } elseif ($taxId === Constants::TAXES[0]['id']) {
                        $deliveryTaxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
                    } else {
                        $deliveryTaxRates = [$originalDeliveryTaxRate];
                    }

                    foreach ($deliveryTaxRates as $deliveryTaxName => $deliveryTaxRate) {
                        $deliveryTaxedPrice = $shippingTotalPrice * $deliveryTaxRate / 100;
                        $finalDeliveryTaxRate = $deliveryTaxRate;

                        $calculatedDeliveryTax = new CalculatedTax($deliveryTaxedPrice, $finalDeliveryTaxRate, $shippingTotalPrice);
                        $calculatedDeliveryTax->addExtension('taxName', new ArrayEntity(['name' => $deliveryTaxName]));
                        $calculatedDeliveryTaxes[] = $calculatedDeliveryTax;
                        $finalCartTaxes[] = $calculatedDeliveryTax;
                    }
                    $deliveryTaxes[$position->getIdentifier()] = new CanadaCalculatedTaxCollection($calculatedDeliveryTaxes);
                }
            }
        }

        return new TaxProviderResult(
            $lineItemTaxes,
            $deliveryTaxes,
            // new CanadaCalculatedTaxCollection($finalCartTaxes)
        );

    }

    private function getDefaultRateByTaxType(string $type): int {
        foreach (Constants::TAXES as $tax) {
            if ($tax['tax_type'] === $type) {
                return $tax['tax_rate'];
            }
        }
        return 0;
    }

}
