<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Tests\Core\Checkout\Cart\Tax;

use InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax\PromotionTaxApportioner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromotionTaxApportioner::class)]
class PromotionTaxApportionerTest extends TestCase
{
    private PromotionTaxApportioner $apportioner;

    protected function setUp(): void
    {
        $this->apportioner = new PromotionTaxApportioner();
    }

    /**
     * The regression this class exists for: discounting a GST-only dish in a
     * GST+PST province must not reverse PST that was never charged.
     */
    public function testDiscountOnGstOnlyLineReversesGstOnly(): void
    {
        $result = $this->apportioner->apportion(
            -1.60,
            [['id' => 'dish', 'discount' => 1.6, 'quantity' => 1]],
            ['dish' => ['GST' => 5.0]],
            ['dish' => 16.0],
        );

        static::assertSame(['GST'], array_keys($result));
        static::assertSame(5.0, $result['GST']['rate']);
        static::assertEqualsWithDelta(-1.60, $result['GST']['base'], 0.0001);
    }

    public function testDiscountOnDualTaxLineReversesBothTaxes(): void
    {
        $result = $this->apportioner->apportion(
            -10.0,
            [['id' => 'goods', 'discount' => 10.0, 'quantity' => 1]],
            ['goods' => ['GST' => 5.0, 'PST' => 7.0]],
            ['goods' => 100.0],
        );

        static::assertEqualsWithDelta(-10.0, $result['GST']['base'], 0.0001);
        static::assertEqualsWithDelta(-10.0, $result['PST']['base'], 0.0001);
    }

    /**
     * A cart-scope discount spanning a GST-only dish and a GST+PST item must
     * split by what each line actually contributed, not average the rates.
     */
    public function testDiscountSplitsAcrossMixedTaxLinesByComposition(): void
    {
        $result = $this->apportioner->apportion(
            -12.0,
            [
                ['id' => 'dish', 'discount' => 4.0, 'quantity' => 1],
                ['id' => 'merch', 'discount' => 8.0, 'quantity' => 1],
            ],
            [
                'dish' => ['GST' => 5.0],
                'merch' => ['GST' => 5.0, 'PST' => 7.0],
            ],
            ['dish' => 40.0, 'merch' => 80.0],
        );

        // Whole discount carries GST; only the merch share carries PST.
        static::assertEqualsWithDelta(-12.0, $result['GST']['base'], 0.0001);
        static::assertEqualsWithDelta(-8.0, $result['PST']['base'], 0.0001);
    }

    public function testFallsBackToPriceWeightingWhenCompositionMissing(): void
    {
        $result = $this->apportioner->apportion(
            -12.0,
            [],
            [
                'dish' => ['GST' => 5.0],
                'merch' => ['GST' => 5.0, 'PST' => 7.0],
            ],
            ['dish' => 40.0, 'merch' => 80.0],
        );

        static::assertEqualsWithDelta(-12.0, $result['GST']['base'], 0.0001);
        // merch is 80 of 120 => two thirds of the discount carries PST.
        static::assertEqualsWithDelta(-8.0, $result['PST']['base'], 0.0001);
    }

    public function testCompositionPointingAtUnknownLinesFallsBackToPrices(): void
    {
        $result = $this->apportioner->apportion(
            -5.0,
            [['id' => 'gone', 'discount' => 5.0, 'quantity' => 1]],
            ['dish' => ['GST' => 5.0]],
            ['dish' => 50.0],
        );

        static::assertEqualsWithDelta(-5.0, $result['GST']['base'], 0.0001);
    }

    /**
     * Discounting an entirely tax-free cart must reverse nothing — inventing a
     * negative liability is exactly the bug this class prevents.
     */
    public function testTaxFreeCartReversesNoTax(): void
    {
        $result = $this->apportioner->apportion(
            -5.0,
            [['id' => 'gift', 'discount' => 5.0, 'quantity' => 1]],
            ['gift' => []],
            ['gift' => 50.0],
        );

        static::assertSame([], $result);
    }

    public function testDiscountAttributedToTaxFreeLineDoesNotDiluteTaxedShare(): void
    {
        // The tip line (tax-free) must not absorb part of the discount and
        // silently shrink the tax actually reversed.
        $result = $this->apportioner->apportion(
            -10.0,
            [
                ['id' => 'dish', 'discount' => 10.0, 'quantity' => 1],
                ['id' => 'tip', 'discount' => 0.0, 'quantity' => 1],
            ],
            ['dish' => ['GST' => 5.0], 'tip' => []],
            ['dish' => 100.0, 'tip' => 10.0],
        );

        static::assertEqualsWithDelta(-10.0, $result['GST']['base'], 0.0001);
    }

    public function testNoTaxableLinesAtAllReturnsNothing(): void
    {
        static::assertSame([], $this->apportioner->apportion(-5.0, [], [], []));
    }
}
