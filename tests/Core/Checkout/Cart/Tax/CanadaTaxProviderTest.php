<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Tests\Core\Checkout\Cart\Tax;

use InoceanSalesTaxesCanada\Config\Constants;
use InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax\CanadaTaxProvider;
use InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax\PromotionTaxApportioner;
use InoceanSalesTaxesCanada\Service\TaxConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(CanadaTaxProvider::class)]
class CanadaTaxProviderTest extends TestCase
{
    private const GST_ONLY_TAX_ID = '0197c94d91ed734a97ba618256bd262f';

    private const TAX_FREE_TAX_ID = '0197c94d91ed734a97ba618257c2185e';

    private const DEFAULT_CONFIG = [
        'InoceanSalesTaxesCanada.config.FreightTaxable' => true,
        'InoceanSalesTaxesCanada.config.TaxDecimals' => 2,
        'InoceanSalesTaxesCanada.config.TaxGstBC' => 5,
        'InoceanSalesTaxesCanada.config.TaxPstBC' => 7,
        'InoceanSalesTaxesCanada.config.TaxHstON' => 13,
    ];

    // --- province resolution -------------------------------------------------

    public function testStandardLineIsTaxedAtTheProvinceRates(): void
    {
        $cart = $this->cartWithProduct(100.0);

        $result = $this->provider()->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertSame(
            [['name' => 'GST', 'rate' => 5.0, 'tax' => 5.0], ['name' => 'PST', 'rate' => 7.0, 'tax' => 7.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
        static::assertTrue($result->declaresTaxes());
    }

    public function testHstProvinceIsTaxedAtTheSingleHarmonisedRate(): void
    {
        $cart = $this->cartWithProduct(100.0);

        $this->provider()->provide($cart, $this->context('CA', 'CA-ON'));

        static::assertSame(
            [['name' => 'HST', 'rate' => 13.0, 'tax' => 13.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    /**
     * A Canadian address with no province must not take the checkout down.
     * Shopware re-throws whatever a tax provider throws, so this used to be a
     * hard 500 at the till.
     */
    public function testAddressWithoutProvinceFallsBackToTheDefaultProvinceAndLogsAnError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $cart = $this->cartWithProduct(100.0);

        $this->provider(logger: $logger)->provide($cart, $this->context('CA', null));

        // Constants::DEFAULT_PROVINCE is BC.
        static::assertSame(
            [['name' => 'GST', 'rate' => 5.0, 'tax' => 5.0], ['name' => 'PST', 'rate' => 7.0, 'tax' => 7.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    public function testUnknownProvinceCodeFallsBackToTheDefaultProvinceAndLogsAnError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $cart = $this->cartWithProduct(100.0);

        $this->provider(logger: $logger)->provide($cart, $this->context('CA', 'CA-XX'));

        static::assertSame(
            [['name' => 'GST', 'rate' => 5.0, 'tax' => 5.0], ['name' => 'PST', 'rate' => 7.0, 'tax' => 7.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    // --- scope -------------------------------------------------------------

    public function testNonCanadianAddressIsLeftToShopwareNativeTaxConfiguration(): void
    {
        $cart = $this->cartWithProduct(100.0);

        $result = $this->provider()->provide($cart, $this->context('US', null));

        static::assertFalse($result->declaresTaxes());
    }

    public function testCartWithoutAddressIsLeftToShopwareNativeTaxConfiguration(): void
    {
        $cart = $this->cartWithProduct(100.0);

        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(new ShippingLocation(new CountryEntity(), null, null));
        $context->method('getSalesChannelId')->willReturn('sales-channel');
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_NET);

        static::assertFalse($this->provider()->provide($cart, $context)->declaresTaxes());
    }

    /**
     * The provider adds tax on top of the line total, which is only true when
     * prices are net. On a gross cart Shopware derives net = gross - tax, so
     * doing the same arithmetic would overstate the tax and understate the net.
     * Hand back to Shopware rather than invent money.
     */
    public function testGrossPricedCartIsLeftToShopwareAndLogsAnError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $cart = $this->cartWithProduct(100.0);

        $result = $this->provider(logger: $logger)
            ->provide($cart, $this->context('CA', 'CA-BC', CartPrice::TAX_STATE_GROSS));

        static::assertFalse($result->declaresTaxes());
    }

    // --- tax categories ------------------------------------------------------

    public function testGstOnlyLineFollowsTheConfiguredProvinceGstRate(): void
    {
        $cart = $this->cartWithProduct(100.0, self::GST_ONLY_TAX_ID);

        $this->provider(config: ['InoceanSalesTaxesCanada.config.TaxGstBC' => 6] + self::DEFAULT_CONFIG)
            ->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertSame(
            [['name' => 'GST', 'rate' => 6.0, 'tax' => 6.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    /**
     * HST provinces have no province level GST field, so a GST-only line there
     * has to fall back to the federal rate rather than to zero.
     */
    public function testGstOnlyLineInAnHstProvinceUsesTheFederalGstRate(): void
    {
        $cart = $this->cartWithProduct(100.0, self::GST_ONLY_TAX_ID);

        $this->provider(config: ['InoceanSalesTaxesCanada.config.TaxGstFederal' => 6] + self::DEFAULT_CONFIG)
            ->provide($cart, $this->context('CA', 'CA-ON'));

        static::assertSame(
            [['name' => 'GST', 'rate' => 6.0, 'tax' => 6.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    /**
     * A GST/PST province has its own GST field, so whatever the merchant put
     * there wins - including a deliberate 0. Falling back to the federal rate
     * here would override the province configuration, which is the very thing
     * the hardcoded 5% used to do.
     */
    public function testGstOnlyLineInAGstProvinceHonoursAZeroGstConfiguration(): void
    {
        $cart = $this->cartWithProduct(100.0, self::GST_ONLY_TAX_ID);

        $this->provider(config: [
            'InoceanSalesTaxesCanada.config.TaxGstBC' => 0,
            'InoceanSalesTaxesCanada.config.TaxGstFederal' => 5,
        ] + self::DEFAULT_CONFIG)->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertSame(
            [['name' => 'GST', 'rate' => 0.0, 'tax' => 0.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    public function testTaxFreeLineCarriesNoTax(): void
    {
        $cart = $this->cartWithProduct(100.0, self::TAX_FREE_TAX_ID);

        $this->provider()->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertSame(
            [['name' => 'TAX-FREE', 'rate' => 0.0, 'tax' => 0.0]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    // --- promotions and shipping --------------------------------------------

    /**
     * A discount is a reduction of things already sold, so it must be reversed
     * at the rates of the lines it actually reduced. Here only the merch line
     * carries PST, so only its share of the discount may reverse PST.
     */
    public function testPromotionReversesTaxAtTheRatesOfTheLinesItDiscounted(): void
    {
        $cart = new Cart('test-token');
        $cart->add($this->productLineItem('dish', 40.0, self::GST_ONLY_TAX_ID));
        $cart->add($this->productLineItem('merch', 80.0));

        $promotion = new LineItem('promo', LineItem::PROMOTION_LINE_ITEM_TYPE);
        $promotion->setPrice(new CalculatedPrice(-12.0, -12.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $promotion->setPayloadValue('composition', [
            ['id' => 'dish', 'discount' => 4.0, 'quantity' => 1],
            ['id' => 'merch', 'discount' => 8.0, 'quantity' => 1],
        ]);
        $cart->add($promotion);

        $this->provider()->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertSame(
            [
                // whole discount carries GST, only the merch share carries PST
                ['name' => 'GST', 'rate' => 5.0, 'tax' => -0.6],
                ['name' => 'PST', 'rate' => 7.0, 'tax' => -0.56],
            ],
            $promotion->getPayloadValue('inoceanCanadaTaxInfo')
        );
    }

    public function testShippingIsTaxedAtProvinceRatesAndRecordedOnTheFirstLineItem(): void
    {
        $cart = $this->cartWithProduct(100.0);
        $cart->setDeliveries(new DeliveryCollection([$this->delivery(10.0)]));

        $result = $this->provider()->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertSame(
            [['name' => 'GST', 'rate' => 5.0, 'tax' => 0.5], ['name' => 'PST', 'rate' => 7.0, 'tax' => 0.7]],
            $cart->getLineItems()->first()?->getPayloadValue('inoceanShippingTaxInfo')
        );
        static::assertNotEmpty($result->getDeliveryTaxes());
    }

    public function testFreeShippingIsNotTaxed(): void
    {
        $cart = $this->cartWithProduct(100.0);
        $cart->setDeliveries(new DeliveryCollection([$this->delivery(0.0)]));

        $this->provider()->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertFalse($cart->getLineItems()->first()?->hasPayloadValue('inoceanShippingTaxInfo'));
    }

    // --- stale payload -------------------------------------------------------

    /**
     * Shopware never removes custom payload keys (replacePayload is an
     * array_replace), and POS refunds read inoceanCanadaTaxInfo as their first
     * source of truth. Leaving Canadian tax on a cart that is no longer
     * Canadian refunds tax that was never charged.
     */
    public function testCanadaTaxPayloadIsClearedWhenTheCartLeavesCanada(): void
    {
        $cart = $this->cartWithProduct(100.0);
        $cart->getLineItems()->first()?->setPayloadValue(
            'inoceanCanadaTaxInfo',
            [['name' => 'GST', 'rate' => 5.0, 'tax' => 5.0]]
        );

        $this->provider()->provide($cart, $this->context('US', null));

        static::assertArrayNotHasKey('inoceanCanadaTaxInfo', $cart->getLineItems()->first()?->getPayload() ?? []);
    }

    public function testShippingTaxPayloadIsClearedWhenFreightStopsBeingTaxable(): void
    {
        $cart = $this->cartWithProduct(100.0);
        $cart->getLineItems()->first()?->setPayloadValue(
            'inoceanShippingTaxInfo',
            [['name' => 'GST', 'rate' => 5.0, 'tax' => 0.5]]
        );

        $this->provider(config: ['InoceanSalesTaxesCanada.config.FreightTaxable' => false] + self::DEFAULT_CONFIG)
            ->provide($cart, $this->context('CA', 'CA-BC'));

        static::assertArrayNotHasKey('inoceanShippingTaxInfo', $cart->getLineItems()->first()?->getPayload() ?? []);
    }

    // --- helpers -------------------------------------------------------------

    private function provider(array $config = self::DEFAULT_CONFIG, ?LoggerInterface $logger = null): CanadaTaxProvider
    {
        $systemConfig = $this->createStub(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(static fn (string $key) => $config[$key] ?? null);

        $logger ??= $this->createStub(LoggerInterface::class);

        return new CanadaTaxProvider(
            new TaxConfigService($systemConfig, $logger),
            new PromotionTaxApportioner(),
            $logger
        );
    }

    private function cartWithProduct(float $totalPrice, ?string $taxId = null): Cart
    {
        $cart = new Cart('test-token');
        $cart->add($this->productLineItem('line-1', $totalPrice, $taxId));

        return $cart;
    }

    private function productLineItem(string $id, float $totalPrice, ?string $taxId = null): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPrice(new CalculatedPrice(
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection([new CalculatedTax(0.0, 0.0, $totalPrice)]),
            new TaxRuleCollection()
        ));

        if ($taxId !== null) {
            $lineItem->setPayloadValue('taxId', $taxId);
        }

        return $lineItem;
    }

    private function delivery(float $shippingCosts): Delivery
    {
        $deliveryDate = new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable());
        $price = new CalculatedPrice(
            $shippingCosts,
            $shippingCosts,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('00000000000000000000000000000004');

        $position = new DeliveryPosition('delivery-position-1', $this->productLineItem('line-1', 100.0), 1, $price, $deliveryDate);

        return new Delivery(
            new DeliveryPositionCollection([$position]),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            $price
        );
    }

    private function context(
        string $countryIso,
        ?string $stateShortCode,
        string $taxState = CartPrice::TAX_STATE_NET
    ): SalesChannelContext {
        $country = new CountryEntity();
        $country->setId('00000000000000000000000000000001');
        $country->setIso($countryIso);

        $address = new CustomerAddressEntity();
        $address->setId('00000000000000000000000000000002');
        $address->setCountry($country);

        if ($stateShortCode !== null) {
            $state = new CountryStateEntity();
            $state->setId('00000000000000000000000000000003');
            $state->setShortCode($stateShortCode);
            $address->setCountryState($state);
        }

        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromAddress($address));
        $context->method('getSalesChannelId')->willReturn('sales-channel');
        $context->method('getTaxState')->willReturn($taxState);

        return $context;
    }

    public function testDefaultProvinceConstantIsAKnownProvince(): void
    {
        static::assertNotNull(\InoceanSalesTaxesCanada\Config\CanadianProvince::tryFrom(Constants::DEFAULT_PROVINCE));
    }
}
