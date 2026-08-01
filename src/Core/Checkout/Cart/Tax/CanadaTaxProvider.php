<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax;

use InoceanSalesTaxesCanada\Config\CanadianProvince;
use InoceanSalesTaxesCanada\Config\Constants;
use InoceanSalesTaxesCanada\Config\TaxType;
use InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax\Struct\CanadaCalculatedTaxCollection;
use InoceanSalesTaxesCanada\Service\TaxConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CanadaTaxProvider extends AbstractTaxProvider
{
    /**
     * Per line tax breakdown, mirrored onto the payload because the store-api
     * strips the line item `price` field entirely and `priceDefinition.taxRules`
     * cannot express a GST + PST split. POS display and refunds read this.
     */
    public const PAYLOAD_LINE_ITEM_TAX = 'inoceanCanadaTaxInfo';

    public const PAYLOAD_SHIPPING_TAX = 'inoceanShippingTaxInfo';

    public function __construct(
        private readonly TaxConfigService $taxConfigService,
        private readonly PromotionTaxApportioner $promotionTaxApportioner,
        private readonly LoggerInterface $logger
    ) {
    }

    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        // Shopware merges payloads (LineItem::replacePayload is an array_replace)
        // and never removes custom keys, so whatever a previous calculation wrote
        // has to go before we decide whether this cart gets Canadian tax at all.
        // Otherwise a cart that moves out of Canada keeps a tax breakdown that was
        // never charged - and POS refunds trust that breakdown over anything else.
        $this->clearTaxPayload($cart);

        $salesChannelId = $context->getSalesChannelId();
        $address = $context->getShippingLocation()->getAddress();
        $countryIso = $address?->getCountry()?->getIso();

        if ($countryIso === null || strtoupper($countryIso) !== Constants::DEFAULT_COUNTRY) {
            // Out of scope by design: non-Canadian orders keep Shopware's own tax
            // configuration. Declaring no taxes hands the cart back to core.
            return new TaxProviderResult([]);
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            // Every rate below is applied on top of the line total, which is only
            // correct for net prices. On a gross cart core derives net = gross - tax,
            // so the same arithmetic would overstate tax and understate the net.
            $this->logger->error(
                'Canada tax: sales channel calculates gross prices, which this plugin does not support. '
                . 'Falling back to Shopware native tax calculation - the GST/PST breakdown will be missing.',
                ['salesChannelId' => $salesChannelId]
            );

            return new TaxProviderResult([]);
        }

        $province = $this->resolveProvince($address?->getCountryState(), $salesChannelId);
        $taxDecimals = $this->taxConfigService->getTaxDecimals($salesChannelId);

        $lineItemTaxes = [];
        $aggregatedCartTaxes = [];

        // Promotion lines are handled in a second pass: a discount has to be
        // taxed at the rates of the lines it discounted, so we must know those
        // rates before we can resolve it. See PromotionTaxApportioner.
        $ratesByLineId = [];
        $priceByLineId = [];
        $promotionLineItems = [];

        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getPrice() === null) {
                // Core raises its own canonical exception for this further down
                // the pipeline; we must not crash first with a worse message.
                continue;
            }

            if ($lineItem->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE) {
                $promotionLineItems[] = $lineItem;

                continue;
            }

            $taxRates = $this->resolveTaxRates($lineItem->getPayloadValue('taxId'), $province, $salesChannelId);
            $price = $lineItem->getPrice()->getTotalPrice();

            // A 0% "TAX-FREE" rate is not a tax group a discount can be reversed
            // against, so it must not act as an apportioning target.
            $ratesByLineId[$lineItem->getId()] = array_filter($taxRates, static fn ($rate): bool => (float) $rate > 0.0);
            $priceByLineId[$lineItem->getId()] = $price;

            $this->applyTaxes($lineItem, $taxRates, $price, $taxDecimals, $aggregatedCartTaxes, $lineItemTaxes);
        }

        foreach ($promotionLineItems as $lineItem) {
            $composition = $lineItem->getPayloadValue('composition');

            $apportioned = $this->promotionTaxApportioner->apportion(
                $lineItem->getPrice()?->getTotalPrice() ?? 0.0,
                \is_array($composition) ? $composition : [],
                $ratesByLineId,
                $priceByLineId
            );

            $taxRates = [];
            $bases = [];
            foreach ($apportioned as $taxName => $group) {
                $taxRates[$taxName] = (float) $group['rate'];
                $bases[$taxName] = (float) $group['base'];
            }

            $this->applyTaxes($lineItem, $taxRates, 0.0, $taxDecimals, $aggregatedCartTaxes, $lineItemTaxes, $bases);
        }

        $deliveryTaxes = $this->applyDeliveryTaxes(
            $cart,
            $province,
            $salesChannelId,
            $taxDecimals,
            $aggregatedCartTaxes
        );

        $finalCartTaxes = new CanadaCalculatedTaxCollection();
        foreach ($aggregatedCartTaxes as $taxName => $data) {
            $calculatedTax = new CalculatedTax($data['tax'], $data['rate'], $data['price']);
            $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
            $finalCartTaxes->add($calculatedTax);
        }

        return new TaxProviderResult($lineItemTaxes, $deliveryTaxes, $finalCartTaxes);
    }

    /**
     * Which tax bands a line carries, based on the tax category assigned to the
     * product (or shipping method).
     *
     * @return array<string, float>
     */
    private function resolveTaxRates(mixed $taxId, CanadianProvince $province, ?string $salesChannelId): array
    {
        if ($taxId === Constants::TAXES[3]['id']) {
            return ['TAX-FREE' => 0.0];
        }

        if ($taxId === Constants::TAXES[2]['id']) {
            return ['GST' => $this->gstOnlyRate($province, $salesChannelId)];
        }

        return $this->taxConfigService->getProvinceTaxRates($province, $salesChannelId);
    }

    /**
     * The rate for a line in the "(CA) GST only" tax category: the federal
     * component, without the provincial one.
     *
     * A GST/PST province has its own GST setting, and whatever the merchant put
     * there wins - including a deliberate 0. Only HST provinces, which have no
     * such setting at all, fall back to the channel wide federal rate. Deciding
     * on the presence of the config field rather than on the value keeps the
     * province configuration authoritative, which is the whole point of not
     * hardcoding this rate.
     */
    private function gstOnlyRate(CanadianProvince $province, ?string $salesChannelId): float
    {
        if (TaxType::GST->getConfigFieldName($province) !== null) {
            return $this->taxConfigService->getTaxRate(TaxType::GST, $province, $salesChannelId);
        }

        return $this->taxConfigService->getFederalGstRate($salesChannelId);
    }

    /**
     * A Canadian address with no usable province must not take the checkout
     * down - that is a hard failure at the till. Fall back to the default
     * province so the sale can complete, and log loudly so it gets corrected.
     */
    private function resolveProvince(?CountryStateEntity $state, ?string $salesChannelId): CanadianProvince
    {
        $shortCode = $state?->getShortCode();

        if ($shortCode !== null) {
            $province = CanadianProvince::tryFrom(substr(strtoupper($shortCode), -2));

            if ($province !== null) {
                return $province;
            }
        }

        $this->logger->error(
            'Canada tax: could not determine the province from the shipping address, '
            . 'falling back to the default province. The order may be taxed at the wrong provincial rate.',
            [
                'shortCode' => $shortCode,
                'fallbackProvince' => Constants::DEFAULT_PROVINCE,
                'salesChannelId' => $salesChannelId,
            ]
        );

        return CanadianProvince::tryFrom(Constants::DEFAULT_PROVINCE) ?? CanadianProvince::BRITISH_COLUMBIA;
    }

    /**
     * @param array<string, float>                                            $taxRates
     * @param array<string, array{rate: float, tax: float, price: float}>     $aggregatedCartTaxes
     * @param array<string, CanadaCalculatedTaxCollection>                    $lineItemTaxes
     * @param array<string, float>|null                                       $basesByTaxName per band taxable base,
     *                                                                                        used by promotion lines
     *                                                                                        where each band is
     *                                                                                        reversed against a
     *                                                                                        different share
     */
    private function applyTaxes(
        LineItem $lineItem,
        array $taxRates,
        float $price,
        int $taxDecimals,
        array &$aggregatedCartTaxes,
        array &$lineItemTaxes,
        ?array $basesByTaxName = null
    ): void {
        $calculatedTaxes = [];
        $lineItemTaxInfo = [];

        foreach ($taxRates as $taxName => $taxRate) {
            $base = $basesByTaxName[$taxName] ?? $price;
            $tax = round($base * $taxRate / 100, $taxDecimals);

            $calculatedTax = new CalculatedTax($tax, $taxRate, $base);
            $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
            $calculatedTaxes[] = $calculatedTax;

            if (!isset($aggregatedCartTaxes[$taxName])) {
                $aggregatedCartTaxes[$taxName] = ['rate' => $taxRate, 'tax' => 0.0, 'price' => 0.0];
            }

            $aggregatedCartTaxes[$taxName]['tax'] += $tax;
            $aggregatedCartTaxes[$taxName]['price'] += $base;
            $lineItemTaxInfo[] = ['name' => $taxName, 'rate' => $taxRate, 'tax' => $tax];
        }

        $lineItem->setPayloadValue(self::PAYLOAD_LINE_ITEM_TAX, $lineItemTaxInfo);

        $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new CanadaCalculatedTaxCollection($calculatedTaxes);
    }

    /**
     * @param array<string, array{rate: float, tax: float, price: float}> $aggregatedCartTaxes
     *
     * @return array<string, CanadaCalculatedTaxCollection>
     */
    private function applyDeliveryTaxes(
        Cart $cart,
        CanadianProvince $province,
        ?string $salesChannelId,
        int $taxDecimals,
        array &$aggregatedCartTaxes
    ): array {
        if (!$this->taxConfigService->isFreightTaxable($salesChannelId)) {
            return [];
        }

        $deliveryTaxes = [];
        $shippingTaxPayload = [];

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            $shippingTotalPrice = $delivery->getShippingCosts()->getTotalPrice();

            if ($shippingTotalPrice <= 0) {
                continue;
            }

            $taxRates = $this->resolveTaxRates(
                $delivery->getShippingMethod()->getTaxId(),
                $province,
                $salesChannelId
            );

            $calculatedDeliveryTaxes = [];

            foreach ($taxRates as $taxName => $taxRate) {
                $tax = round($shippingTotalPrice * $taxRate / 100, $taxDecimals);

                $calculatedTax = new CalculatedTax($tax, $taxRate, $shippingTotalPrice);
                $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
                $calculatedDeliveryTaxes[] = $calculatedTax;

                if (!isset($aggregatedCartTaxes[$taxName])) {
                    $aggregatedCartTaxes[$taxName] = ['rate' => $taxRate, 'tax' => 0.0, 'price' => 0.0];
                }

                $aggregatedCartTaxes[$taxName]['tax'] += $tax;
                $aggregatedCartTaxes[$taxName]['price'] += $shippingTotalPrice;

                if (!isset($shippingTaxPayload[$taxName])) {
                    $shippingTaxPayload[$taxName] = ['name' => $taxName, 'rate' => $taxRate, 'tax' => 0.0];
                }

                $shippingTaxPayload[$taxName]['tax'] += $tax;
            }

            $position = $delivery->getPositions()->first();

            if ($calculatedDeliveryTaxes !== [] && $position) {
                $deliveryTaxes[$position->getIdentifier()] = new CanadaCalculatedTaxCollection($calculatedDeliveryTaxes);
            }
        }

        // Shipping tax has no line item of its own, so it rides on the first
        // line item's payload for the storefront, documents and admin to read.
        // clearTaxPayload() above guarantees exactly one carrier per calculation.
        $carrier = $cart->getLineItems()->first();

        if ($shippingTaxPayload !== [] && $carrier) {
            $carrier->setPayloadValue(self::PAYLOAD_SHIPPING_TAX, array_values($shippingTaxPayload));
        }

        return $deliveryTaxes;
    }

    private function clearTaxPayload(Cart $cart): void
    {
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            // setPayload() merges key by key, so unsetting a copy of the payload
            // would not remove anything - removePayloadValue() is the only way out.
            foreach ([self::PAYLOAD_LINE_ITEM_TAX, self::PAYLOAD_SHIPPING_TAX] as $key) {
                if ($lineItem->hasPayloadValue($key)) {
                    $lineItem->removePayloadValue($key);
                }
            }
        }
    }
}
