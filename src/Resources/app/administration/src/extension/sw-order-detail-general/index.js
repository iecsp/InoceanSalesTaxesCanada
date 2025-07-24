// <plugin-root>/src/Resources/app/administration/src/extension/sw-order-detail-general/index.js
import template from './sw-order-detail-general.html.twig';

const { Component } = Shopware;

Component.override('sw-order-detail-general', {
    template,

    computed: {
        // 获取加拿大税率信息
        canadianTaxInfo() {
            if (!this.order || !this.order.lineItems) {
                return null;
            }

            const taxInfo = {
                lineItemTaxes: [],
                shippingTaxes: []
            };

            // 从订单行项目 payload 中提取税率信息
            this.order.lineItems.forEach(lineItem => {
                if (lineItem.payload && lineItem.payload.inoceanCanadaTaxInfo) {
                    taxInfo.lineItemTaxes.push({
                        id: lineItem.id,
                        label: lineItem.label,
                        taxDetails: lineItem.payload.inoceanCanadaTaxInfo
                    });
                }
            });

            return taxInfo;
        },

        // 格式化税率显示
        formattedCanadianTaxes() {
            if (!this.canadianTaxInfo) {
                return [];
            }

            const formattedTaxes = [];

            // // 处理订单级别的税率
            // if (this.canadianTaxInfo.orderTaxes.length > 0) {
            //     this.canadianTaxInfo.orderTaxes.forEach(tax => {
            //         formattedTaxes.push({
            //             type: 'order',
            //             taxName: tax.tax_name || 'N/A',
            //             taxRate: tax.tax_rate || 0,
            //             taxAmount: tax.tax_amount || 0,
            //             province: tax.province || 'N/A'
            //         });
            //     });
            // }

            // 处理行项目级别的税率
            this.canadianTaxInfo.lineItemTaxes.forEach(item => {
                if (item.taxDetails && Array.isArray(item.taxDetails)) {
                    item.taxDetails.forEach(tax => {
                        formattedTaxes.push({
                            type: 'lineItem',
                            lineItemLabel: item.label,
                            taxName: tax.name || 'TAX',
                            taxRate: tax.rate || 0,
                            taxAmount: tax.tax || 0
                        });
                    });
                }
            });

            return formattedTaxes;
        }
    },

    methods: {
        // 格式化税率百分比显示
        formatTaxRate(rate) {
            return `${(rate * 100).toFixed(2)}%`;
        },

        // 格式化金额显示
        formatCurrency(amount) {
            return this.$filters.currency(amount, this.order.currency.shortName);
        }
    }
});
