# Canada Tax Provider for Shopware 6.6 / 6.7

A comprehensive tax calculation and compliance plugin designed specifically for Canadian merchants using Shopware e-commerce platform.

## 🇨🇦 Overview

This plugin provides automated Canadian tax calculation and compliance features, including:
- **GST (Goods and Services Tax)** calculation
- **PST (Provincial Sales Tax)** for applicable provinces
- **HST (Harmonized Sales Tax)** for participating provinces
- **QST (Quebec Sales Tax)** for Quebec merchants
- Real-time tax rate updates based on customer shipping address
- Detailed tax breakdown in order summaries and line items
- Backend administration interface for tax review

## ✨ Features

### Tax Calculation
- ✅ Automatic tax calculation based on customer shipping address
- ✅ Support for all Canadian provinces and territories
- ✅ Multi-tier tax structure (Federal + Provincial/Territorial)
- ✅ Line item level tax tracking and reporting
- ✅ Tax-exempt product support

### Administration Interface
- ✅ Enhanced order detail view with Canadian tax information
- ✅ Tax breakdown by type (GST, PST, HST, QST)
- ✅ Individual line item tax display
- ✅ Multi-language support (English, French, German)

### Compliance Features
- ✅ Persistent tax data storage in order payload
- ✅ Integration with Shopware's existing tax system

## 🛠️ Requirements

- **Shopware Version**: 6.6 or 6.7 (see `composer.json` for the exact constraint)
- **PHP Version**: 8.2 or higher
- **Geographic Scope**: Canadian shipping addresses only — see *Scope* below
- **Price display**: the sales channel must calculate **net** prices
- **Business License**: Valid Canadian business registration required

### ⚠️ Net prices only

Every rate is applied *on top of* the line total, which is only correct when
prices are net (tax-exclusive). If the customer group calculates gross prices,
the plugin steps aside, writes an error to the log and lets Shopware calculate
tax natively — totals stay correct, but the GST/PST breakdown will be missing.
Set the customer group to net prices (Shopware: *Settings → Customer groups →
Net prices*) before going live.

### Scope: non-Canadian orders

Orders shipping outside Canada are **out of scope by design**. The plugin
declares no taxes for them and Shopware's own tax configuration applies. That
means the tax rate assigned to each product's tax category (e.g. "(CA) GST +
PST/QST" = 12%) is what a foreign customer is charged. If you sell across the
border, add a Shopware tax rule so non-Canadian addresses are taxed at 0%.

## 📦 Installation

1. Download the plugin from the Shopware Store
2. Install and activate the plugin in the Shopware administration
3. Assign the tax categories the plugin created — "(CA) GST + PST/QST",
   "(CA) HST", "(CA) GST only", "(CA) TAX-FREE" — to your products and shipping
   methods (see *Configuration*)

## ⚙️ Configuration

1. Navigate to **Settings > Extensions > Canada Tax Provider for Shopware**
2. Rates are pre-filled with the current statutory values. Adjust them if a
   rate changes; each sales channel can override them independently.
3. **Federal GST** (basic settings) is the rate charged on "(CA) GST only"
   products in HST provinces, which have no separate provincial GST field.
4. Assign a tax category to every product:
   - **(CA) GST + PST/QST** — normal goods, taxed at the province's full rate set
   - **(CA) GST only** — federal rate only (e.g. qualifying prepared food)
   - **(CA) TAX-FREE** — zero-rated / exempt items, and tips
5. **Tax Decimals** controls rounding of tax amounts. Values below 2 are raised
   to 2 and values above 4 are capped at 4.

## 🎯 Usage

### For Store Administrators
- View detailed tax breakdowns in **Orders > Order Details > General Tab**
- Monitor tax calculations in **Orders > Line Items Grid**

### For Customers
- Automatic tax calculation during checkout
- Clear tax breakdown in order confirmation
- Province-specific tax rates applied based on shipping address

## 🌐 Supported Languages

- **English** (en-GB)
- **French** (fr-FR) - Quebec compliance
- **German** (de-DE) - For German-Canadian businesses

## 📊 Tax Rates Coverage

| Province/Territory | GST/HST | PST/QST | Combined Rate |
|-------------------|---------|---------|---------------|
| Alberta           | 5%      | 0%      | 5%            |
| British Columbia  | 5%      | 7%      | 12%           |
| Manitoba          | 5%      | 7%      | 12%           |
| New Brunswick     | 15% HST | -       | 15%           |
| Newfoundland      | 15% HST | -       | 15%           |
| Northwest Territories | 5%  | 0%      | 5%            |
| Nova Scotia       | 15% HST | -       | 15%           |
| Nunavut           | 5%      | 0%      | 5%            |
| Ontario           | 13% HST | -       | 13%           |
| Prince Edward Island | 15% HST | -    | 15%           |
| Quebec            | 5%      | 9.975%  | 14.975%       |
| Saskatchewan      | 5%      | 6%      | 11%           |
| Yukon             | 5%      | 0%      | 5%            |


## 🆘 Support

### Technical Support
- **Email**: [iecsp.com@gmail.com]
- **Response Time**: 24-72 hours for Canadian merchants

### Tax Compliance Notice
⚠️ **Important**: While this plugin assists with Canada Tax Provider for Shopware, merchants remain solely responsible for ensuring their tax calculations comply with all applicable Canadian federal and provincial tax laws. Consult with qualified tax professionals for complex scenarios.

## 🔄 Changelog

### Version 1.0.7 (2026-08-01)
- **Fixed**: a Canadian address without a province, or with an unrecognised
  province code, aborted the whole checkout. It now falls back to the default
  province and logs an error.
- **Fixed**: an unparsable tax rate in the settings aborted the checkout for
  that province. Rates are now parsed leniently (comma separators, extra
  decimals) and anything genuinely unusable falls back to 0% with an error log.
- **Fixed**: "(CA) GST only" products ignored the configured GST rate and were
  always charged 5%.
- **Fixed**: the tax breakdown written onto line items was never cleared, so a
  cart that moved out of Canada kept a breakdown that had never been charged —
  which POS refunds then used.
- **Fixed**: gross-priced sales channels were charged tax computed on the gross
  amount. The plugin now steps aside for them.
- **Fixed**: order detail and invoices showed no tax rows at all for orders
  without a Canadian breakdown (non-Canadian, or placed before installation).
- **Fixed**: invoices matched tax bands to the order total by rate, which could
  drop or duplicate a band.
- **Added**: configurable **Federal GST** rate; **Tax Decimals** is now clamped
  to 2–4.

### Version 1.0.2 (2025-07-25)
- Initial release
- Support for all Canadian provinces and territories
- Multi-language administration interface
- Shopware 6.6+ compatibility

## 🤝 Contributing

Released under the MIT License (see `LICENSE.md`). Bug reports and feature
requests are best submitted through the support channels above.

**Made with ❤️ for Canadian e-commerce merchants**

*For questions about this plugin or Canada Tax Provider for Shopware, please contact our support team.*

