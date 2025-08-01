<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Shopware\Core\Defaults;
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
            $ruleData = [
                'id' => Constants::CANADA_RULE_ID,
                'name' => Constants::RULE_NAME,
                'priority' => 1,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            ];
            $ruleRepository->create([$ruleData], $context);
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
                    'type' => 'customerShippingCountry',
                    'ruleId' => Constants::CANADA_RULE_ID,
                    'value' => ['operator' => '=', 'countryIds' => [$countryId]],
                    'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]], $context);
            }
        }

        $taxProviderRepository = $container->get('tax_provider.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('identifier', CanadaTaxProvider::class));
        $taxProviderId = $taxProviderRepository->searchIds($criteria, $context)->firstId();
        if (!$taxProviderId) {
            $languageRepository = $this->container->get('language.repository');

            $deDeLangCriteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', 'de-DE'));
            $deDeLanguageId = $languageRepository->searchIds($deDeLangCriteria, $context)->firstId();

            $enGbLangCriteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', 'en-GB'));
            $enGbLanguageId = $languageRepository->searchIds($enGbLangCriteria, $context)->firstId();

            $enUsLangCriteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', 'en-US'));
            $enUsLanguageId = $languageRepository->searchIds($enUsLangCriteria, $context)->firstId();

            $frFrLangCriteria = (new Criteria())->addFilter(new EqualsFilter('locale.code', 'fr-FR'));
            $frFrLanguageId = $languageRepository->searchIds($frFrLangCriteria, $context)->firstId();

            $taxProviderData = [
                'id' => Uuid::randomHex(),
                'identifier' => CanadaTaxProvider::class,
                'name' => 'Canada Tax Provider',
                'active' => true,
                'priority' => 1,
                'availabilityRuleId' => Constants::CANADA_RULE_ID,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'translations' => [],
            ];

            if ($deDeLanguageId) {
                $taxProviderData['translations'][$deDeLanguageId] = ['name' => 'Kanada Umsatzsteuer-Anbieter'];
            }

            if ($enGbLanguageId) {
                $taxProviderData['translations'][$enGbLanguageId] = ['name' => 'Canada Tax Provider'];
            }

            if ($enUsLanguageId) {
                $taxProviderData['translations'][$enUsLanguageId] = ['name' => 'Canada Tax Provider'];
            }

            if ($frFrLanguageId) {
                $taxProviderData['translations'][$frFrLanguageId] = ['name' => 'Fournisseur de taxes du Canada'];
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

        $context = $uninstallContext->getContext();

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->setActiveFlagForTaxProvider(false, $context);
        $this->clearTaxProviderRuleAssociation($context);
        $this->removeTaxProvider($context);
        $this->removeRuleConditions($context);
        $this->removeRule($context);
        $this->removeTaxes($context);
    
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
        $criteria = (new Criteria())->addFilter(new EqualsFilter('identifier', CanadaTaxProvider::class));
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

    private function clearTaxProviderRuleAssociation(Context $context): void
    {
        $taxProviderRepository = $this->container->get('tax_provider.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('identifier', CanadaTaxProvider::class));
        
        $taxProviderId = $taxProviderRepository->searchIds($criteria, $context)->firstId();
        if ($taxProviderId) {
            $taxProviderRepository->update([
                [
                    'id' => $taxProviderId,
                    'availabilityRuleId' => null,
                ],
            ], $context);
        }
    }

    private function removeTaxProvider(Context $context): void
    {
        $taxProviderRepository = $this->container->get('tax_provider.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('identifier', CanadaTaxProvider::class));
        
        $taxProviderId = $taxProviderRepository->searchIds($criteria, $context)->firstId();
        if ($taxProviderId) {
            $taxProviderRepository->delete([
                ['id' => $taxProviderId]
            ], $context);
        }
    }

    private function removeRuleConditions(Context $context): void
    {
        $ruleConditionRepository = $this->container->get('rule_condition.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('ruleId', Constants::CANADA_RULE_ID));
        
        $ruleConditionIds = $ruleConditionRepository->searchIds($criteria, $context)->getIds();
        if (!empty($ruleConditionIds)) {
            $deleteData = array_map(fn($id) => ['id' => $id], $ruleConditionIds);
            $ruleConditionRepository->delete($deleteData, $context);
        }
    }

    private function removeRule(Context $context): void
    {
        $ruleRepository = $this->container->get('rule.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Constants::CANADA_RULE_ID));
        
        $ruleId = $ruleRepository->searchIds($criteria, $context)->firstId();
        if ($ruleId) {
            $ruleRepository->delete([
                ['id' => $ruleId]
            ], $context);
        }
    }

    private function removeTaxes(Context $context): void
    {
        $taxRepository = $this->container->get('tax.repository');
        $criteria = new Criteria();
        
        $taxIds = array_map(fn($tax) => $tax['id'], Constants::TAXES);
        $criteria->addFilter(new EqualsAnyFilter('id', $taxIds));
        
        $taxesToRemove = $taxRepository->searchIds($criteria, $context)->getIds();
        
        if (!empty($taxesToRemove)) {
            $productRepository = $this->container->get('product.repository');
            $shippingMethodRepository = $this->container->get('shipping_method.repository');
            
            $productCriteria = new Criteria();
            $productCriteria->addFilter(new EqualsAnyFilter('taxId', $taxesToRemove));
            $productsUsingTax = $productRepository->searchIds($productCriteria, $context)->getIds();
            
            $shippingMethodCriteria = new Criteria();
            $shippingMethodCriteria->addFilter(new EqualsAnyFilter('taxId', $taxesToRemove));
            $shippingMethodsUsingTax = $shippingMethodRepository->searchIds($shippingMethodCriteria, $context)->getIds();
            
            $taxesInUse = array_unique(array_merge($productsUsingTax, $shippingMethodsUsingTax));
            $taxesToActuallyRemove = array_diff($taxesToRemove, $taxesInUse);
            
            if (!empty($taxesToActuallyRemove)) {
                $deleteData = array_map(fn($id) => ['id' => $id], $taxesToActuallyRemove);
                $taxRepository->delete($deleteData, $context);
            }
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