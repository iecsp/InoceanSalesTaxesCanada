import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.scss';

const { Component } = Shopware;

Component.override('sw-order-line-items-grid', {
    template,

    computed: {

        getLineItemColumns() {
            const columnDefinitions = [
                {
                    property: 'quantity',
                    dataIndex: 'quantity',
                    label: 'sw-order.detailBase.columnQuantity',
                    allowResize: false,
                    align: 'right',
                    inlineEdit: true,
                    width: '90px',
                },
                {
                    property: 'label',
                    dataIndex: 'label',
                    label: 'sw-order.detailBase.columnProductName',
                    allowResize: false,
                    primary: true,
                    inlineEdit: true,
                    multiLine: true,
                },
                {
                    property: 'payload.productNumber',
                    dataIndex: 'payload.productNumber',
                    label: 'sw-order.detailBase.columnProductNumber',
                    allowResize: false,
                    align: 'left',
                    visible: false,
                },
                {
                    property: 'unitPrice',
                    dataIndex: 'unitPrice',
                    label: this.unitPriceLabel,
                    allowResize: false,
                    align: 'right',
                    inlineEdit: true,
                    width: '120px',
                },
            ];

            if (this.taxStatus !== 'tax-free') {
                columnDefinitions.push({
                    property: 'price.taxRules[0]',
                    label: 'salseTaxCanada.order.lineItem.tax',
                    allowResize: false,
                    align: 'right',
                    inlineEdit: false,
                    width: '110px',
                });
            }

            return [
                ...columnDefinitions,
                {
                    property: 'totalPrice',
                    dataIndex: 'totalPrice',
                    label:
                        this.taxStatus === 'gross'
                            ? 'sw-order.detailBase.columnTotalPriceGross'
                            : 'sw-order.detailBase.columnTotalPriceNet',
                    allowResize: false,
                    align: 'right',
                    width: '120px',
                },
            ];
        },

        lineItemTaxesMap() {
            if (!this.order || !this.order.lineItems) {
                return {};
            }
    
            const map = {};
    
            this.order.lineItems.forEach(lineItem => {
                map[lineItem.id] = Array.isArray(lineItem.payload?.inoceanCanadaTaxInfo) 
                    ? lineItem.payload.inoceanCanadaTaxInfo.map(taxInfo => ({
                        taxName: taxInfo.name,
                        taxRate: taxInfo.rate,
                        taxPrice: Number(taxInfo.tax) || 0,
                    })) 
                    : [];
            });

            return map;
        },
    
        lineItemsTaxes() {
            return Object.entries(this.lineItemTaxesMap).map(([id, taxes]) => ({
                id,
                taxes,
            }));
        }

    }
});