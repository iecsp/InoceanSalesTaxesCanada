# Canada Tax Provider - GST, PST, HST & QST Plugin for Shopware 6.6+

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

- **Shopware Version**: 6.6.0 or higher
- **PHP Version**: 8.2 or higher
- **Geographic Scope**: Canada only
- **Business License**: Valid Canadian business registration required

## 📦 Installation

1. Download the plugin from Shopware Store
2. Install and activate the plugin on the Shopware backend
3. 

## ⚙️ Configuration

1. Navigate to **Settings > Extensions > Canada Tax Provider - GST, PST, HST & QST**
2. The tax rate has been set by default. If the tax rate changes in the future, you can modify it yourself.
3. Set up tax-exempt and GST only products, other products set their own default tax rates.

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

## 🔒 License & Terms

This plugin is **free for Canadian merchants** but operates under a **proprietary license**:

- ✅ **Free to use** for Canadian businesses
- ❌ **No redistribution** allowed
- ❌ **No source code modification** permitted
- ❌ **Geographic restriction**: Canada only

See [LICENSE.txt](LICENSE.txt) for complete terms and conditions.

## 🆘 Support

### Technical Support
- **Email**: [iecsp.com@gmail.com]
- **Response Time**: 24-72 hours for Canadian merchants

### Tax Compliance Notice
⚠️ **Important**: While this plugin assists with Canada Tax Provider - GST, PST, HST & QST, merchants remain solely responsible for ensuring their tax calculations comply with all applicable Canadian federal and provincial tax laws. Consult with qualified tax professionals for complex scenarios.

## 🔄 Changelog

### Version 1.0.2 (2025-07-25)
- Initial release
- Support for all Canadian provinces and territories
- Multi-language administration interface
- Shopware 6.6+ compatibility

## 🤝 Contributing

This is proprietary software. Contributions, bug reports, and feature requests should be submitted through official support channels only.

**Made with ❤️ for Canadian e-commerce merchants**

*For questions about this plugin or Canada Tax Provider - GST, PST, HST & QST, please contact our support team.*

