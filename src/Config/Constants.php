<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Config;

class Constants
{
    const RULE_NAME = 'Customers from Canada';
    const CANADA_RULE_ID = '0197db2b54907752b70bfbc8711e54a3';
    const TAXES = [        
        ['id' => '0197c94d91ed734a97ba618257880791', 'tax_rate' => 12, 'name' => '(CA) GST + PST/QST', 'position' => 0, 'tax_type' => 'GST-PST'],
        ['id' => '0197c94d91ed734a97ba618257f6b4c7', 'tax_rate' => 13, 'name' => '(CA) HST', 'position' => 0, 'tax_type' => 'HST'],
        ['id' => '0197c94d91ed734a97ba618256bd262f', 'tax_rate' => 5, 'name' => '(CA) GST only', 'position' => 0, 'tax_type' => 'GST-ONLY'],
        ['id' => '0197c94d91ed734a97ba618257c2185e', 'tax_rate' => 0, 'name' => '(CA) TAX-FREE', 'position' => 0, 'tax_type' => 'TAX-FREE'],
    ];
    const DEFAULT_COUNTRY = 'CA';
    const DEFAULT_PROVINCE = 'BC';
}