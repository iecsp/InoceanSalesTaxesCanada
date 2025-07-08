<?php declare(strict_types=1);

namespace Inocean\SalesTaxesCanada;

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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;
use Inocean\SalesTaxesCanada\Config\Constants;
use Inocean\SalesTaxesCanada\Core\Checkout\Cart\Tax\CanadaTaxProvider;

class SalesTaxesCanada extends Plugin
{

	public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $container = $this->container;

        $ruleRepository = $container->get('rule.repository');
        $ruleRepository->create([[
            'id' => Constants::CANADA_RULE_ID,
            'name' => Constants::RULE_NAME,
            'priority' => 1,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $installContext->getContext());

        $countryId = $this->getCountryIdByIso(Constants::DEFAULT_COUNTRY, $installContext->getContext());
        if ($countryId !== null) {
            $ruleConditionRepository = $container->get('rule_condition.repository');
            $ruleConditionRepository->create([[
                'id' => Uuid::randomHex(),
                'type' => 'customerBillingCountry',
                'ruleId' => Constants::CANADA_RULE_ID,
                'value' => ['operator' => '=', 'countryIds' => [$countryId]],
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]], $installContext->getContext());
        } else {
            throw new \RuntimeException('Invalid country ISO code: ' . Constants::DEFAULT_COUNTRY);
        }

        $taxProviderRepository = $container->get('tax_provider.repository');
        $taxProviderRepository->create([[
            'id' => Constants::TAX_PROVIDER_ID,
            'identifier' => CanadaTaxProvider::class,
            'active' => true,
            'priority' => 1,
            'availabilityRuleId' => Constants::CANADA_RULE_ID,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $installContext->getContext());

        foreach (Constants::TAXES as $tax) {
            $taxRepository = $container->get('tax.repository');
            $taxRepository->create([[
                'id' => $tax['id'],
                'taxRate' => $tax['tax_rate'],
                'name' => $tax['name'],
                'position' => $tax['position'],
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]], $installContext->getContext());
        }

    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $context = Context::createDefaultContext();

        $connection = $this->container->get(Connection::class);

        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=0;');

        $taxRepo = $this->container->get('tax.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', array_column(Constants::TAXES, 'id')));
        $taxIds = $taxRepo->searchIds($criteria, $context);
        $taxIdsToDelete = array_map(function($id) {
            return ['id' => $id];
        }, $taxIds->getIds());
        if (!empty($taxIdsToDelete)) {
            $taxRepo->delete($taxIdsToDelete, $context);
        }

        $taxProviderRepo = $this->container->get('tax_provider.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Constants::TAX_PROVIDER_ID));
        $taxProviderIds = $taxProviderRepo->searchIds($criteria, $context);
        $taxProviderIdsToDelete = array_map(function($id) {
            return ['id' => $id];
        }, $taxProviderIds->getIds());
        if (!empty($taxProviderIdsToDelete)) {
            $taxProviderRepo->delete($taxProviderIdsToDelete, $context);
        }

        $ruleConditionRepo = $this->container->get('rule_condition.repository');        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('ruleId', Constants::CANADA_RULE_ID));
        $ruleConditionIds = $ruleConditionRepo->searchIds($criteria, $context);
        $ruleConditionIdsToDelete = array_map(function($id) {
            return ['id' => $id];
        }, $ruleConditionIds->getIds());
        if (!empty($ruleConditionIdsToDelete)) {
            $ruleConditionRepo->delete($ruleConditionIdsToDelete, $context);
        }
        
        $ruleRepo = $this->container->get('rule.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Constants::CANADA_RULE_ID));
        $ruleIds = $ruleRepo->searchIds($criteria, $context);
        $ruleIdsToDelete = array_map(function($id) {
            return ['id' => $id];
        }, $ruleIds->getIds());
        if (!empty($ruleIdsToDelete)) {
            $ruleRepo->delete($ruleIdsToDelete, $context);
        }

        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=1;');

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

