<?php declare(strict_types=1);

namespace Inocean\SalesTaxesCanada\Config;

class Constants
{
    const RULE_NAME = 'Customer from Canada';
    const CANADA_RULE_ID = '0197db2b54907752b70bfbc8711e54a3';
    const TAX_PROVIDER_ID = '0197db31c0a37b849dc1195985ce063b';
    const TAXES = [        
        ['id' => '0197c94d91ed734a97ba618257880791', 'tax_rate' => 12, 'name' => 'GST + PST/QST', 'position' => 0],
        ['id' => '0197c94d91ed734a97ba618257f6b4c7', 'tax_rate' => 13, 'name' => 'HST', 'position' => 0],
        ['id' => '0197c94d91ed734a97ba618256bd262f', 'tax_rate' => 5, 'name' => 'GST only', 'position' => 0],
        ['id' => '0197c94d91ed734a97ba618257c2185e', 'tax_rate' => 0, 'name' => 'TAX-FREE', 'position' => 0],
    ];
    const DEFAULT_COUNTRY = 'CA';
    const DEFAULT_PROVINCE = 'BC';
}