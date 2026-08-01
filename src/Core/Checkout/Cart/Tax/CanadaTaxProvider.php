<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
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

    private PromotionTaxApportioner $promotionTaxApportioner;

    public function __construct(
        TaxConfigService $taxConfigService,
        ?PromotionTaxApportioner $promotionTaxApportioner = null
    ) {
        $this->taxConfigService = $taxConfigService;
        $this->promotionTaxApportioner = $promotionTaxApportioner ?? new PromotionTaxApportioner();
    }

    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        $lineItemTaxes = [];
        $deliveryTaxes = [];
        $aggregatedCartTaxes = [];
        $finalCartTaxes = [];

        $freightTaxable = $this->taxConfigService->isFreightTaxable($context->getSalesChannelId()) ?? 1;
        $taxDecimals = $this->taxConfigService->getTaxDecimals($context->getSalesChannelId()) ?? 2;

        $address = $context->getShippingLocation()->getAddress();
        if (!$address || strtoupper($address->getCountry()?->getIso()) !== Constants::DEFAULT_COUNTRY) {
            return new TaxProviderResult([]);
        }

        $proviceShortCode = substr(strtoupper($address->getCountryState()->getShortCode()), -2);
        $province = CanadianProvince::from($proviceShortCode ?? Constants::DEFAULT_PROVINCE);

        // Promotion lines are handled in a second pass: a discount has to be
        // taxed at the rates of the lines it discounted, so we must know those
        // rates before we can resolve it. See PromotionTaxApportioner.
        $ratesByLineId = [];
        $priceByLineId = [];
        $promotionLineItems = [];

        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE) {
                $promotionLineItems[] = $lineItem;
                continue;
            }

            if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[3]['id']) {
                $taxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
            } elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[2]['id']) {
                $taxRates = ['GST' => $this->getDefaultRateByTaxType('GST-ONLY')];
            } else {
                $taxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
            }

            $price = $lineItem->getPrice()->getTotalPrice();

            // A 0% "TAX-FREE" rate is not a tax group a discount can be reversed
            // against, so it must not act as an apportioning target.
            $ratesByLineId[$lineItem->getId()] = array_filter(
                $taxRates,
                static fn ($rate): bool => (float) $rate > 0.0
            );
            $priceByLineId[$lineItem->getId()] = $price;

            $this->applyTaxes($lineItem, $taxRates, $price, $taxDecimals, $aggregatedCartTaxes, $lineItemTaxes);
        }

        foreach ($promotionLineItems as $lineItem) {
            $price = $lineItem->getPrice()->getTotalPrice();
            $composition = $lineItem->getPayloadValue('composition');

            $apportioned = $this->promotionTaxApportioner->apportion(
                $price,
                \is_array($composition) ? $composition : [],
                $ratesByLineId,
                $priceByLineId
            );

            $calculatedTaxes = [];
            $lineItemTaxInfo = [];

            foreach ($apportioned as $taxName => $group) {
                $taxRate = (float) $group['rate'];
                $base = (float) $group['base'];
                $tax = round($base * $taxRate / 100, $taxDecimals);

                $calculatedTax = new CalculatedTax($tax, $taxRate, $base);
                $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
                $calculatedTaxes[] = $calculatedTax;

                if (!isset($aggregatedCartTaxes[$taxName])) {
                    $aggregatedCartTaxes[$taxName] = ['rate' => $taxRate, 'tax' => 0, 'price' => 0];
                }

                $aggregatedCartTaxes[$taxName]['tax'] += $tax;
                $aggregatedCartTaxes[$taxName]['price'] += $base;
                $lineItemTaxInfo[] = ['name' => $taxName, 'rate' => $taxRate, 'tax' => $tax];
            }

            $payload = $lineItem->getPayload();
            $payload['inoceanCanadaTaxInfo'] = $lineItemTaxInfo;
            $lineItem->setPayload($payload);

            $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new CanadaCalculatedTaxCollection($calculatedTaxes);
        }

        if ($freightTaxable) {

            $delivery = $cart->getDeliveries()->first();
            if ($delivery && $delivery->getShippingCosts()->getTotalPrice() > 0) {
                $shippingTotalPrice = $delivery->getShippingCosts()->getTotalPrice();
                $taxId = $delivery->getShippingMethod()->getTaxId();
                $aggregatedShippingTaxesPayload = [];
                $deliveryTaxRates = [];
                $calculatedDeliveryTaxes = [];

                if ($taxId === Constants::TAXES[3]['id']) {
                    $deliveryTaxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
                } elseif ($taxId === Constants::TAXES[2]['id']) {
                    $deliveryTaxRates = ['GST' => $this->getDefaultRateByTaxType('GST-ONLY')];
                } else {
                    $deliveryTaxRates = $this->taxConfigService->getProvinceTaxRates($province, $context->getSalesChannelId());
                }

                foreach ($deliveryTaxRates as $deliveryTaxName => $deliveryTaxRate) {
                    $deliveryTaxAmount = round($shippingTotalPrice * $deliveryTaxRate / 100, $taxDecimals);
                    $calculatedDeliveryTax = new CalculatedTax($deliveryTaxAmount, $deliveryTaxRate, $shippingTotalPrice);
                    $calculatedDeliveryTax->addExtension('taxName', new ArrayEntity(['name' => $deliveryTaxName]));
                    $calculatedDeliveryTaxes[] = $calculatedDeliveryTax;
        
                    if (!isset($aggregatedCartTaxes[$deliveryTaxName])) {
                        $aggregatedCartTaxes[$deliveryTaxName] = ['rate' => $deliveryTaxRate, 'tax' => 0, 'price' => 0];
                    }
                    $aggregatedCartTaxes[$deliveryTaxName]['tax'] += $deliveryTaxAmount;
                    $aggregatedCartTaxes[$deliveryTaxName]['price'] += $shippingTotalPrice;
        
                    $aggregatedShippingTaxesPayload[$deliveryTaxName] = [
                        'name' => $deliveryTaxName, 
                        'rate' => $deliveryTaxRate, 
                        'tax' => $deliveryTaxAmount
                    ];
                }

                if (!empty($calculatedDeliveryTaxes) && $delivery->getPositions()->first()) {
                    $deliveryTaxes[$delivery->getPositions()->first()->getIdentifier()] = new CanadaCalculatedTaxCollection($calculatedDeliveryTaxes);
                }

                if (!empty($aggregatedShippingTaxesPayload) && $cart->getLineItems()->first()) {
                    $payload = $cart->getLineItems()->first()->getPayload();
                    $payload['inoceanShippingTaxInfo'] = array_values($aggregatedShippingTaxesPayload);
                    $cart->getLineItems()->first()->setPayload($payload);
                }
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
            new CanadaCalculatedTaxCollection($finalCartTaxes)
        );
    }

    /**
     * Apply a resolved rate set to one line item, recording both the per-line
     * collection and the running cart aggregate.
     *
     * @param array<string, float|int>                             $taxRates
     * @param array<string, array{rate: float|int, tax: float, price: float}> $aggregatedCartTaxes
     * @param array<string, CanadaCalculatedTaxCollection>         $lineItemTaxes
     */
    private function applyTaxes(
        LineItem $lineItem,
        array $taxRates,
        float $price,
        int $taxDecimals,
        array &$aggregatedCartTaxes,
        array &$lineItemTaxes
    ): void {
        $calculatedTaxes = [];
        $lineItemTaxInfo = [];

        foreach ($taxRates as $taxName => $taxRate) {
            $tax = round($price * $taxRate / 100, $taxDecimals);
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