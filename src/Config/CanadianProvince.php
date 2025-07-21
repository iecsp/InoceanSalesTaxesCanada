<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesCanada\Config;

enum CanadianProvince: string
{
    case BRITISH_COLUMBIA = 'BC';
    case ALBERTA = 'AB';
    case SASKATCHEWAN = 'SK';
    case MANITOBA = 'MB';
    case ONTARIO = 'ON';
    case QUEBEC = 'QC';
    case NEW_BRUNSWICK = 'NB';
    case PRINCE_EDWARD_ISLAND = 'PE';
    case NOVA_SCOTIA = 'NS';
    case NEWFOUNDLAND_LABRADOR = 'NL';
    case YUKON = 'YT';
    case NORTHWEST_TERRITORIES = 'NT';
    case NUNAVUT = 'NU';
    
    public function getName(): string
    {
        return match($this) {
            self::BRITISH_COLUMBIA => 'British Columbia',
            self::ALBERTA => 'Alberta',
            self::SASKATCHEWAN => 'Saskatchewan',
            self::MANITOBA => 'Manitoba',
            self::ONTARIO => 'Ontario',
            self::QUEBEC => 'Quebec',
            self::NEW_BRUNSWICK => 'New Brunswick',
            self::PRINCE_EDWARD_ISLAND => 'Prince Edward Island',
            self::NOVA_SCOTIA => 'Nova Scotia',
            self::NEWFOUNDLAND_LABRADOR => 'Newfoundland and Labrador',
            self::YUKON => 'Yukon',
            self::NORTHWEST_TERRITORIES => 'Northwest Territories',
            self::NUNAVUT => 'Nunavut',
        };
    }
    
    public function getTaxFieldNames(): array
    {
        return match($this) {
            self::BRITISH_COLUMBIA => ['TaxGstBC', 'TaxPstBC'],
            self::ALBERTA => ['TaxGstAB', 'TaxPstAB'],
            self::SASKATCHEWAN => ['TaxGstSK', 'TaxPstSK'],
            self::MANITOBA => ['TaxGstMB', 'TaxPstMB'],
            self::ONTARIO => ['TaxHstON'],
            self::QUEBEC => ['TaxGstQC', 'TaxQstQC'],
            self::NEW_BRUNSWICK => ['TaxHstNB'],
            self::PRINCE_EDWARD_ISLAND => ['TaxHstPE'],
            self::NOVA_SCOTIA => ['TaxHstNS'],
            self::NEWFOUNDLAND_LABRADOR => ['TaxHstNL'],
            self::YUKON => ['TaxGstYT', 'TaxPstYT'],
            self::NORTHWEST_TERRITORIES => ['TaxGstNT', 'TaxPstNT'],
            self::NUNAVUT => ['TaxGstNU', 'TaxPstNU'],
        };
    }
    
    public function getTaxTypes(): array
    {
        return match($this) {
            self::BRITISH_COLUMBIA => [TaxType::GST, TaxType::PST],
            self::ALBERTA => [TaxType::GST, TaxType::PST],
            self::SASKATCHEWAN => [TaxType::GST, TaxType::PST],
            self::MANITOBA => [TaxType::GST, TaxType::PST],
            self::ONTARIO => [TaxType::HST],
            self::QUEBEC => [TaxType::GST, TaxType::QST],
            self::NEW_BRUNSWICK => [TaxType::HST],
            self::PRINCE_EDWARD_ISLAND => [TaxType::HST],
            self::NOVA_SCOTIA => [TaxType::HST],
            self::NEWFOUNDLAND_LABRADOR => [TaxType::HST],
            self::YUKON => [TaxType::GST, TaxType::PST],
            self::NORTHWEST_TERRITORIES => [TaxType::GST, TaxType::PST],
            self::NUNAVUT => [TaxType::GST, TaxType::PST],
        };
    }
}