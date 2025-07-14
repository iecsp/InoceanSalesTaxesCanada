<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Tax\TaxDefinition;
use Shopware\Core\Content\Tax\TaxProviderDefinition;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;
use InoceanSalesTaxesCanada\Config\Constants;
use InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax\CanadaTaxProvider;

class InoceanSalesTaxesCanada extends Plugin
{

	public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $context = $installContext->getContext();
        $container = $this->container;

        $ruleRepository = $container->get('rule.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Constants::CANADA_RULE_ID));
        $ruleId = $ruleRepository->searchIds($criteria, $context)->firstId();
        if (!$ruleId) {
            $ruleRepository->create([[
                'id' => Constants::CANADA_RULE_ID,
                'name' => Constants::RULE_NAME,
                'priority' => 1,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]], $context);
        }

        $countryId = $this->getCountryIdByIso(Constants::DEFAULT_COUNTRY, $context);
        if ($countryId) {
            $ruleConditionRepository = $container->get('rule_condition.repository');
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('ruleId', Constants::CANADA_RULE_ID));
            $ruleConditionId = $ruleConditionRepository->searchIds($criteria, $context)->firstId();
            if (!$ruleConditionId) {
                $ruleConditionRepository->create([[
                    'id' => Uuid::randomHex(),
                    'type' => 'customerBillingCountry',
                    'ruleId' => Constants::CANADA_RULE_ID,
                    'value' => ['operator' => '=', 'countryIds' => [$countryId]],
                    'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]], $context);
            }
        }

        $taxProviderRepository = $container->get('tax_provider.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Constants::TAX_PROVIDER_ID));
        $taxProviderId = $taxProviderRepository->searchIds($criteria, $context)->firstId();
        if (!$taxProviderId) {
            $languageRepository = $this->container->get('language.repository');

            $deDeLangCriteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', 'de-DE'));
            $deDeLanguageId = $languageRepository->searchIds($deDeLangCriteria, $context)->firstId();

            $enGbLangCriteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', 'en-GB'));
            $enGbLanguageId = $languageRepository->searchIds($enGbLangCriteria, $context)->firstId();

            $taxProviderData = [
                'id' => Constants::TAX_PROVIDER_ID,
                'identifier' => CanadaTaxProvider::class,
                'name' => 'Canada Sales Tax Provider',
                'active' => true,
                'priority' => 1,
                'availabilityRuleId' => Constants::CANADA_RULE_ID,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'translations' => [],
            ];

            if ($enGbLanguageId) {
                $taxProviderData['translations'][$enGbLanguageId] = ['name' => 'Canada Sales Tax Provider'];
            }

            if ($deDeLanguageId) {
                $taxProviderData['translations'][$deDeLanguageId] = ['name' => 'Kanada Umsatzsteuer-Anbieter'];
            }

            $taxProviderRepository->create([$taxProviderData], $context);
        }
        
        $taxRepository = $container->get('tax.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', array_column(Constants::TAXES, 'id')));
        $existingTaxIds = $taxRepository->searchIds($criteria, $context)->getIds();

        foreach (Constants::TAXES as $tax) {
            if (!in_array($tax['id'], $existingTaxIds)) {
                $taxRepository->create([[
                    'id' => $tax['id'],
                    'taxRate' => $tax['tax_rate'],
                    'name' => $tax['name'],
                    'position' => $tax['position'],
                    'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]], $context);
            }
        }

    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Once the plugin has set product associations, it cannot be uninstalled, so the following delete methods are temporarily commented out.
        // $context = Context::createDefaultContext();

        // $taxRepo = $this->container->get('tax.repository');
        // $criteria = new Criteria();
        // $criteria->addFilter(new EqualsAnyFilter('id', array_column(Constants::TAXES, 'id')));
        // $taxIds = $taxRepo->searchIds($criteria, $context);
        // $taxIdsToDelete = array_map(function($id) {
        //     return ['id' => $id];
        // }, $taxIds->getIds());
        // if (!empty($taxIdsToDelete)) {
        //     $taxRepo->delete($taxIdsToDelete, $context);
        // }

        // $taxProviderRepo = $this->container->get('tax_provider.repository');
        // $criteria = new Criteria();
        // $criteria->addFilter(new EqualsFilter('id', Constants::TAX_PROVIDER_ID));
        // $taxProviderIds = $taxProviderRepo->searchIds($criteria, $context);
        // $taxProviderIdsToDelete = array_map(function($id) {
        //     return ['id' => $id];
        // }, $taxProviderIds->getIds());
        // if (!empty($taxProviderIdsToDelete)) {
        //     $taxProviderRepo->delete($taxProviderIdsToDelete, $context);
        // }

        // $ruleConditionRepo = $this->container->get('rule_condition.repository');        
        // $criteria = new Criteria();
        // $criteria->addFilter(new EqualsFilter('ruleId', Constants::CANADA_RULE_ID));
        // $ruleConditionIds = $ruleConditionRepo->searchIds($criteria, $context);
        // $ruleConditionIdsToDelete = array_map(function($id) {
        //     return ['id' => $id];
        // }, $ruleConditionIds->getIds());
        // if (!empty($ruleConditionIdsToDelete)) {
        //     $ruleConditionRepo->delete($ruleConditionIdsToDelete, $context);
        // }
        
        // $ruleRepo = $this->container->get('rule.repository');
        // $criteria = new Criteria();
        // $criteria->addFilter(new EqualsFilter('id', Constants::CANADA_RULE_ID));
        // $ruleIds = $ruleRepo->searchIds($criteria, $context);
        // $ruleIdsToDelete = array_map(function($id) {
        //     return ['id' => $id];
        // }, $ruleIds->getIds());
        // if (!empty($ruleIdsToDelete)) {
        //     $ruleRepo->delete($ruleIdsToDelete, $context);
        // }

    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->setActiveFlagForTaxProvider(true, $activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
        $this->setActiveFlagForTaxProvider(false, $deactivateContext->getContext());
    }

    private function setActiveFlagForTaxProvider(bool $active, Context $context): void
    {
        $taxProviderRepository = $this->container->get('tax_provider.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('id', Constants::TAX_PROVIDER_ID));
        $taxProviderId = $taxProviderRepository->searchIds($criteria, $context)->firstId();
        if ($taxProviderId) {
            $taxProviderRepository->update([
                [
                    'id' => $taxProviderId,
                    'active' => $active,
                ],
            ], $context);
        }
    }

    private function getCountryIdByIso(string $iso, Context $context): ?string
    {

        $countryRepository = $this->container->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $iso));
        $countryIds = $countryRepository->searchIds($criteria, $context);
        return $countryIds->getIds()[0] ?? null;

    }

}