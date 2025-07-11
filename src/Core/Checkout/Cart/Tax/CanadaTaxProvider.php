<?php declare(strict_types=1);

namespace Inocean\SalesTaxesCanada\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Inocean\SalesTaxesCanada\Config\Constants;

class CanadaTaxProvider extends AbstractTaxProvider
{

    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {

        $lineItemTaxes = [];
        $cartPriceTaxes = [];
        $totalPrice = 0;

        $address = $context->getShippingLocation()->getAddress();
        if (!$address || strtoupper($address->getCountry()?->getIso()) !== Constants::DEFAULT_COUNTRY) {
            return new TaxProviderResult([]);
        }

        $province = $address->getCountryState()->getShortCode() ?? Constants::DEFAULT_PROVINCE;

        foreach ($cart->getLineItems() as $lineItem) {
            $originalTaxRate = $lineItem->getPrice()->getCalculatedTaxes()->first()?->getTaxRate() ?? $this->getTaxRateByName('TAX-FREE');
            
            if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[3]['id']) {
                $taxRates = [$this->getTaxRateByName('TAX-FREE')];
            } else if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[2]['id']) {
                $taxRates = [$this->getTaxRateByName('GST only')];
            } elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[1]['id']) {
                $taxRates = $this->getTaxRatesByProvince($province);
            } elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[0]['id']) {
                $taxRates = $this->getTaxRatesByProvince($province);
            } else {
                $taxRates = [$originalTaxRate];
            }

            $price = $lineItem->getPrice()->getTotalPrice();
            $calculatedTaxes = [];
            $totalPrice += $price;

            foreach ($taxRates as $taxRate) {
                $tax = $price * $taxRate / 100;
                $calculatedTaxes[] = new CalculatedTax($tax, $taxRate, $price);   
            }

            $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new CalculatedTaxCollection($calculatedTaxes);
            
            foreach ($calculatedTaxes as $calculatedTax) {
                $existingTax = null;
                foreach ($cartPriceTaxes as $cartPriceTax) {
                    if ($cartPriceTax->getTaxRate() === $calculatedTax->getTaxRate()) {
                        $existingTax = $cartPriceTax;
                        break;
                    }
                }
                if ($existingTax) {
                    $existingTax->setTax($existingTax->getTax() + $calculatedTax->getTax());
                } else {
                    $cartPriceTaxes[] = new CalculatedTax($calculatedTax->getTax(), $calculatedTax->getTaxRate(), $totalPrice);
                }
            }

        }

        $calculatedDeliveryTaxes = [];
        $deliveryTaxes = [];
        $shippingTotalPrice = $cart->getShippingCosts()->getTotalPrice() ?? 0;
        foreach ($cart->getDeliveries() as $delivery) {
            
            $shippingMethod = $delivery->getShippingMethod();
            $taxId = $shippingMethod->getTaxId();
            $originalDeliveryTaxRate = $shippingMethod->getTax()->getTaxRate();
            $taxId = $delivery->getShippingMethod()->getTaxId();
            if ($taxId === Constants::TAXES[3]['id']) {
                $deliveryTaxRates = [$this->getTaxRateByName('TAX-FREE')];
            } else if ($taxId === Constants::TAXES[2]['id']) {
                $deliveryTaxRates = [$this->getTaxRateByName('GST only')];
            } elseif ($taxId === Constants::TAXES[1]['id']) {
                $deliveryTaxRates = $this->getTaxRatesByProvince($province);
            } elseif ($taxId === Constants::TAXES[0]['id']) {
                $deliveryTaxRates = $this->getTaxRatesByProvince($province);
            } else {
                $deliveryTaxRates = [$originalDeliveryTaxRate];
            }
            foreach ($deliveryTaxRates as $deliveryTaxRate) {
                $deliveryTaxedPrice = $shippingTotalPrice * $deliveryTaxRate / 100;
                $calculatedDeliveryTaxes[] = new CalculatedTax($deliveryTaxedPrice, $deliveryTaxRate, $shippingTotalPrice);   
            }

            foreach ($delivery->getPositions() as $position) {
                $deliveryTaxes[] = new CalculatedTaxCollection($calculatedDeliveryTaxes);
            }
        }

        return new TaxProviderResult(
            $lineItemTaxes,
            $deliveryTaxes,
            // new CalculatedTaxCollection($cartPriceTaxes)
        );
    }

    private function getTaxRatesByProvince(string $province): array
    {
        $provinceCode = substr(strtoupper($province), -2);
	    $configValue = str_replace(' ', '', (string)$this->systemConfigService->get('SalesTaxesCanada.config.CanadaTax'.$provinceCode));
        if (!$configValue) {
            return [$this->getTaxRateByName('GST only')];
        }
        return array_map('floatval', explode(',', $configValue));
    }

    private function getTaxRateByName(string $name): int {
        foreach (Constants::TAXES as $tax) {
            if ($tax['name'] === $name) {
                return $tax['tax_rate'];
            }
        }
        return 0;
    }

}