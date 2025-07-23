<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

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
            if (!$order->getLineItems() || !$order->getPrice() || !$order->getPrice()->getCalculatedTaxes()) {
                continue;
            }

            $taxRateToNameMap = [];
            foreach ($order->getLineItems() as $lineItem) {
                $payload = $lineItem->getPayload();

                if (!isset($payload['inoceanCanadaTaxInfo']) || !is_array($payload['inoceanCanadaTaxInfo'])) {
                    continue;
                }

                foreach ($payload['inoceanCanadaTaxInfo'] as $customTax) {
                    $rate = $customTax['rate'] ?? null;
                    $name = $customTax['taxName'] ?? null;

                    if ($rate !== null && $name !== null) {
                        if (!isset($taxRateToNameMap[$rate])) {
                            $taxRateToNameMap[(string)$rate] = $name;
                        }
                    }
                }
            }

            if (empty($taxRateToNameMap)) {
                continue;
            }
            
            foreach ($order->getPrice()->getCalculatedTaxes() as $calculatedTax) {
                $taxRate = (string)$calculatedTax->getTaxRate();

                if (isset($taxRateToNameMap[$taxRate])) {
                    $taxName = $taxRateToNameMap[$taxRate];
                    $calculatedTax->addExtension(
                        'label', 
                        new ArrayStruct(['name' => $taxName])
                    );
                }
            }
        }
    }
}