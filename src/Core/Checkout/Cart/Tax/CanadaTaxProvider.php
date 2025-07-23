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
        $deliveryTaxes = [];
        $aggregatedCartTaxes = [];

        $address = $context->getShippingLocation()->getAddress();
        if (!$address || strtoupper($address->getCountry()?->getIso()) !== Constants::DEFAULT_COUNTRY) {
            return new TaxProviderResult([]);
        }

        $proviceShortCode = substr(strtoupper($address->getCountryState()->getShortCode()), -2);
        $province = CanadianProvince::from($proviceShortCode ?? Constants::DEFAULT_PROVINCE);

        // --- Process Product Line Items ---
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[3]['id']) { // TAX-FREE
                $taxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
            } elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[2]['id']) { // GST-ONLY
                $taxRates = ['GST' => $this->getDefaultRateByTaxType('GST-ONLY')];
            } else {
                $taxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
            }

            $price = $lineItem->getPrice()->getTotalPrice();
            $calculatedTaxes = [];
            $lineItemTaxInfo = [];

            foreach ($taxRates as $taxName => $taxRate) {
                $tax = $price * $taxRate / 100;
                $calculatedTax = new CalculatedTax($tax, $taxRate, $price);
                $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
                $calculatedTaxes[] = $calculatedTax;

                if (!isset($aggregatedCartTaxes[$taxName])) {
                    $aggregatedCartTaxes[$taxName] = ['rate' => $taxRate, 'tax' => 0, 'price' => 0];
                }
                $aggregatedCartTaxes[$taxName]['tax'] += $tax;
                $aggregatedCartTaxes[$taxName]['price'] += $price;

                $lineItemTaxInfo[] = ['name' => $taxName, 'rate' => $taxRate, 'tax' => $tax];
            }

            $payload = $lineItem->getPayload();
            $payload['inoceanCanadaTaxInfo'] = $lineItemTaxInfo;
            $lineItem->setPayload($payload);

            $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new CanadaCalculatedTaxCollection($calculatedTaxes);
        }

        // --- Process Shipping Costs ---
        if ($this->taxConfigService->isFreightTaxable($context->getSalesChannelId())) {
            $aggregatedShippingTaxesPayload = [];

            $delivery = $cart->getDeliveries()->first();
            foreach ($delivery->getPositions() as $position) {
                $shippingTotalPrice = $delivery->getShippingCosts()->getTotalPrice();
                if ($shippingTotalPrice <= 0) {
                    continue;
                }

                $taxId = $delivery->getShippingMethod()->getTaxId();
                if ($taxId === Constants::TAXES[3]['id']) { // TAX-FREE
                    $deliveryTaxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
                } elseif ($taxId === Constants::TAXES[2]['id']) { // GST-ONLY
                    $deliveryTaxRates = ['GST' => $this->getDefaultRateByTaxType('GST-ONLY')];
                } else {
                    $deliveryTaxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
                }

                $calculatedDeliveryTaxes = [];
                foreach ($deliveryTaxRates as $deliveryTaxName => $deliveryTaxRate) {
                    $deliveryTaxedPrice = $shippingTotalPrice * $deliveryTaxRate / 100;
                    $calculatedDeliveryTax = new CalculatedTax($deliveryTaxedPrice, $deliveryTaxRate, $shippingTotalPrice);
                    $calculatedDeliveryTax->addExtension('taxName', new ArrayEntity(['name' => $deliveryTaxName]));
                    $calculatedDeliveryTaxes[] = $calculatedDeliveryTax;

                    // Aggregate for the final cart summary display
                    if (!isset($aggregatedCartTaxes[$deliveryTaxName])) {
                        $aggregatedCartTaxes[$deliveryTaxName] = ['rate' => $deliveryTaxRate, 'tax' => 0, 'price' => 0];
                    }
                    $aggregatedCartTaxes[$deliveryTaxName]['tax'] += $deliveryTaxedPrice;
                    $aggregatedCartTaxes[$deliveryTaxName]['price'] += $shippingTotalPrice;

                    // Aggregate for the payload
                    if (!isset($aggregatedShippingTaxesPayload[$deliveryTaxName])) {
                        $aggregatedShippingTaxesPayload[$deliveryTaxName] = ['name' => $deliveryTaxName, 'rate' => $deliveryTaxRate, 'tax' => 0];
                    }
                    $aggregatedShippingTaxesPayload[$deliveryTaxName]['tax'] += $deliveryTaxedPrice;
                }

                if (!empty($calculatedDeliveryTaxes)) {
                    $deliveryTaxes[$position->getIdentifier()] = new CanadaCalculatedTaxCollection($calculatedDeliveryTaxes);
                }
            }

            // Persist the aggregated shipping tax info into the single shipping line item's payload
            if (!empty($aggregatedShippingTaxesPayload)) {
                $payload = $cart->getLineItems()->first()->getPayload();
                $payload['inoceanShippingTaxInfo'] = array_values($aggregatedShippingTaxesPayload);
                $cart->getLineItems()->first()->setPayload($payload);
            }
        }

        $finalCartTaxes = new CanadaCalculatedTaxCollection();
        foreach ($aggregatedCartTaxes as $taxName => $data) {
            $calculatedTax = new CalculatedTax($data['tax'], $data['rate'], $data['price']);
            $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
            $finalCartTaxes->add($calculatedTax);
        }

        return new TaxProviderResult(
            $lineItemTaxes,
            $deliveryTaxes,
            $finalCartTaxes
        );
    }

    private function getDefaultRateByTaxType(string $type): int
    {
        foreach (Constants::TAXES as $tax) {
            if ($tax['tax_type'] === $type) {
                return $tax['tax_rate'];
            }
        }
        return 0;
    }
}