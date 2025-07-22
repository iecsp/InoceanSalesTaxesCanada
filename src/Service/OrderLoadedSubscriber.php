<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'order.loaded' => 'onOrderLoaded',
        ];
    }

    public function onOrderLoaded(EntityLoadedEvent $event): void
    {
        /** @var OrderEntity $order */
        foreach ($event->getEntities() as $order) {
            // 确保订单有关联的行项目和计算后的税费
            if (!$order->getLineItems() || !$order->getPrice() || !$order->getPrice()->getCalculatedTaxes()) {
                continue;
            }

            // 1. 从所有 line_item 的 payload 中构建一个 税率 => 税名 的映射
            $taxRateToNameMap = [];
            foreach ($order->getLineItems() as $lineItem) {
                $payload = $lineItem->getPayload();

                // !!! 请根据您的实际payload结构修改这里的 'canadian_taxes' !!!
                if (!isset($payload['inoceanCanadaTaxInfo']) || !is_array($payload['inoceanCanadaTaxInfo'])) {
                    continue;
                }

                foreach ($payload['inoceanCanadaTaxInfo'] as $customTax) {
                    // !!! 请根据您的实际payload结构修改这里的 'rate' 和 'name' !!!
                    $rate = $customTax['rate'] ?? null;
                    $name = $customTax['taxName'] ?? null;

                    if ($rate !== null && $name !== null) {
                        // 将税率和名称存入映射表，如果已存在则不会覆盖
                        if (!isset($taxRateToNameMap[$rate])) {
                            $taxRateToNameMap[(string)$rate] = $name;
                        }
                    }
                }
            }

            // 如果没有从payload中找到任何自定义税率信息，则直接返回
            if (empty($taxRateToNameMap)) {
                continue;
            }
            
            // 2. 遍历顶层的 calculatedTaxes 并使用映射表注入税名
            foreach ($order->getPrice()->getCalculatedTaxes() as $calculatedTax) {
                $taxRate = (string)$calculatedTax->getTaxRate();

                if (isset($taxRateToNameMap[$taxRate])) {
                    $taxName = $taxRateToNameMap[$taxRate];

                    // 将税名作为扩展属性注入，以便Twig可以访问
                    $calculatedTax->addExtension(
                        'label', 
                        new ArrayStruct(['name' => $taxName])
                    );
                }
            }
        }
    }
}