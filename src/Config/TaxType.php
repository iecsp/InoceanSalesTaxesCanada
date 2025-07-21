<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada\Config;

enum TaxType: string
{
    case GST = 'GST';
    case PST = 'PST';
    case HST = 'HST';
    case QST = 'QST';
    
    public function getLabel(): string
    {
        return match($this) {
            self::GST => 'Goods and Services Tax',
            self::PST => 'Provincial Sales Tax',
            self::HST => 'Harmonized Sales Tax',
            self::QST => 'Quebec Sales Tax',
        };
    }
    
    public function getConfigFieldName(CanadianProvince $province): ?string
    {
        return match([$this, $province]) {
            [self::GST, CanadianProvince::BRITISH_COLUMBIA] => 'TaxGstBC',
            [self::PST, CanadianProvince::BRITISH_COLUMBIA] => 'TaxPstBC',
            [self::GST, CanadianProvince::ALBERTA] => 'TaxGstAB',
            [self::PST, CanadianProvince::ALBERTA] => 'TaxPstAB',
            [self::GST, CanadianProvince::SASKATCHEWAN] => 'TaxGstSK',
            [self::PST, CanadianProvince::SASKATCHEWAN] => 'TaxPstSK',
            [self::GST, CanadianProvince::MANITOBA] => 'TaxGstMB',
            [self::PST, CanadianProvince::MANITOBA] => 'TaxPstMB',
            [self::HST, CanadianProvince::ONTARIO] => 'TaxHstON',
            [self::GST, CanadianProvince::QUEBEC] => 'TaxGstQC',
            [self::QST, CanadianProvince::QUEBEC] => 'TaxQstQC',
            [self::HST, CanadianProvince::NEW_BRUNSWICK] => 'TaxHstNB',
            [self::HST, CanadianProvince::PRINCE_EDWARD_ISLAND] => 'TaxHstPE',
            [self::HST, CanadianProvince::NOVA_SCOTIA] => 'TaxHstNS',
            [self::HST, CanadianProvince::NEWFOUNDLAND_LABRADOR] => 'TaxHstNL',
            [self::GST, CanadianProvince::YUKON] => 'TaxGstYT',
            [self::PST, CanadianProvince::YUKON] => 'TaxPstYT',
            [self::GST, CanadianProvince::NORTHWEST_TERRITORIES] => 'TaxGstNT',
            [self::PST, CanadianProvince::NORTHWEST_TERRITORIES] => 'TaxPstNT',
            [self::GST, CanadianProvince::NUNAVUT] => 'TaxGstNU',
            [self::PST, CanadianProvince::NUNAVUT] => 'TaxPstNU',
            default => null,
        };
    }
}
