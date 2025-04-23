<?php declare(strict_types=1);

namespace Inocean\SalesTaxesCanada\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CanadaTaxProvider extends AbstractTaxProvider
{

    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {

	    if (!$this->isCanadaTaxEnabledForSalesChannel($context)) {
    		    return new TaxProviderResult([]);
	    }

	    $address = $context->getCustomer()?->getActiveShippingAddress();
	    if (!$address || strtoupper($address->getCountry()?->getIso()) !== 'CA') {
     		   return new TaxProviderResult([]);
 	    }

        $lineItemTaxes = [];
        $province = $address->getCountryState()?->getShortCode() ?? 'BC';

        $rates = $this->getTaxRatesByProvince($province);

        foreach ($cart->getLineItems() as $item) {
            $price = $item->getPrice()->getTotalPrice();
            $taxes = [];

            foreach ($rates as $rate) {
                $taxAmount = $price * $rate / 100;
                $taxes[] = new CalculatedTax($taxAmount, $rate, $price);
            }

            $lineItemTaxes[$item->getUniqueIdentifier()] = new CalculatedTaxCollection($taxes);
        }

        return new TaxProviderResult($lineItemTaxes);
    }

    private function getProvinceCode(SalesChannelContext $context): string
    {
        $address = $context->getCustomer()?->getActiveShippingAddress();
        return $address?->getCountryState()?->getShortCode() ?? 'BC';
    }

    private function getTaxRatesByProvince(string $province): array
    {
	$configValue = $this->systemConfigService->get("CanadaTaxProvider`$province`");

        if (!$configValue) {
            return [5];
        }

        return array_map('floatval', explode(',', $configValue));
    }

    private function isCanadaTaxEnabledForSalesChannel(SalesChannelContext $context): bool
    {
	$salesChannelId = $context->getSalesChannelId();

	$useGlobally = $this->systemConfigService->get('CanadaTaxProviderUuseGlobally') ?? true;
	if ($useGlobally) {
		return true;
  	}

	$allowedChannels = $this->systemConfigService->get('CanadaTaxProviderAllowedSalesChannels') ?? [];
	return in_array($salesChannelId, $allowedChannels, true);
    }

}

