<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Tests\Service;

use InoceanSalesTaxesCanada\Config\CanadianProvince;
use InoceanSalesTaxesCanada\Config\TaxType;
use InoceanSalesTaxesCanada\Service\TaxConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * A tax rate lives in a free-text admin field. Whatever a human types there,
 * reading it back must never take the checkout down — this service runs inside
 * the cart calculation, and anything it throws is re-thrown by Shopware's
 * TaxProviderProcessor as a hard checkout failure.
 */
#[CoversClass(TaxConfigService::class)]
class TaxConfigServiceTest extends TestCase
{
    private function service(array $config, ?LoggerInterface $logger = null): TaxConfigService
    {
        $systemConfig = $this->createStub(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(
            static fn (string $key) => $config[$key] ?? null
        );

        return new TaxConfigService($systemConfig, $logger ?? $this->createStub(LoggerInterface::class));
    }

    public function testCommaDecimalSeparatorIsAcceptedInsteadOfBreakingCheckout(): void
    {
        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxQstQC' => '9,975']);

        static::assertSame(9.975, $service->getTaxRate(TaxType::QST, CanadianProvince::QUEBEC));
    }

    public function testExtraDecimalsAreRoundedInsteadOfRejected(): void
    {
        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxQstQC' => '9.9755']);

        static::assertSame(9.976, $service->getTaxRate(TaxType::QST, CanadianProvince::QUEBEC));
    }

    public function testUnparsableRateLogsAnErrorAndReturnsZeroWithoutThrowing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxPstBC' => 'seven percent'], $logger);

        static::assertSame(0.0, $service->getTaxRate(TaxType::PST, CanadianProvince::BRITISH_COLUMBIA));
    }

    public function testNegativeRateLogsAnErrorAndReturnsZeroWithoutThrowing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxPstBC' => '-7'], $logger);

        static::assertSame(0.0, $service->getTaxRate(TaxType::PST, CanadianProvince::BRITISH_COLUMBIA));
    }

    public function testRateAboveOneHundredLogsAnErrorAndReturnsZeroWithoutThrowing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxPstBC' => '107'], $logger);

        static::assertSame(0.0, $service->getTaxRate(TaxType::PST, CanadianProvince::BRITISH_COLUMBIA));
    }

    public function testUnsetRateIsZeroAndIsNotReportedAsAnError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())->method('error');

        $service = $this->service([], $logger);

        static::assertSame(0.0, $service->getTaxRate(TaxType::PST, CanadianProvince::ALBERTA));
    }

    /**
     * A province/tax-type pair with no config field is a mapping bug in
     * TaxType::getConfigFieldName(). Its symptom — a whole tax band silently
     * dropped by getProvinceTaxRates() — is invisible, so it must be logged.
     */
    public function testTaxTypeWithNoConfigFieldForTheProvinceLogsAnError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $service = $this->service([], $logger);

        static::assertSame(0.0, $service->getTaxRate(TaxType::HST, CanadianProvince::BRITISH_COLUMBIA));
    }

    public function testTaxDecimalsFallsBackToTwoWhenUnset(): void
    {
        static::assertSame(2, $this->service([])->getTaxDecimals());
    }

    public function testTaxDecimalsIsFlooredAtTwoWhenClearedToZero(): void
    {
        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxDecimals' => 0]);

        static::assertSame(2, $service->getTaxDecimals());
    }

    public function testTaxDecimalsIsCappedAtFour(): void
    {
        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxDecimals' => 9]);

        static::assertSame(4, $service->getTaxDecimals());
    }

    public function testFederalGstRateFallsBackToTheFederalDefaultWhenUnset(): void
    {
        static::assertSame(5.0, $this->service([])->getFederalGstRate());
    }

    public function testFederalGstRateHonoursConfiguration(): void
    {
        $service = $this->service(['InoceanSalesTaxesCanada.config.TaxGstFederal' => 6]);

        static::assertSame(6.0, $service->getFederalGstRate());
    }

    public function testProvinceRatesDropZeroBands(): void
    {
        $service = $this->service([
            'InoceanSalesTaxesCanada.config.TaxGstAB' => 5,
            'InoceanSalesTaxesCanada.config.TaxPstAB' => 0,
        ]);

        static::assertSame(['GST' => 5.0], $service->getProvinceTaxRates(CanadianProvince::ALBERTA));
    }
}
