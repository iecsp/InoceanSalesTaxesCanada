import template from './sw-order-detail-general.html.twig';

const { Component } = Shopware;

/**
 * Aggregates a Canadian tax breakdown out of the line item payloads.
 *
 * Keyed by rate rather than by name so that a rate appearing on several lines
 * collapses into a single summary row.
 */
function aggregateByRate(lineItems, payloadKey) {
    const aggregation = {};

    (lineItems || []).forEach((lineItem) => {
        const entries = lineItem?.payload?.[payloadKey];

        if (!Array.isArray(entries)) {
            return;
        }

        entries.forEach((taxInfo) => {
            const rateKey = taxInfo.rate;

            if (!aggregation[rateKey]) {
                aggregation[rateKey] = {
                    taxRate: taxInfo.rate,
                    taxName: taxInfo.name,
                    taxPriceTotal: 0,
                };
            }

            aggregation[rateKey].taxPriceTotal += Number(taxInfo.tax) || 0;
        });
    });

    return Object.values(aggregation)
        .sort((a, b) => a.taxRate - b.taxRate)
        .map((tax) => ({
            taxDetails: {
                rate: tax.taxRate,
                tax: tax.taxPriceTotal,
                name: tax.taxName,
            },
        }));
}

Component.override('sw-order-detail-general', {
    template,

    computed: {
        taxStatus() {
            return this.order.price.taxStatus;
        },

        sortedCalculatedTaxes() {
            if (!this.order || !this.order.lineItems) {
                return [];
            }

            const canadianTaxes = aggregateByRate(this.order.lineItems, 'inoceanCanadaTaxInfo');

            if (canadianTaxes.length > 0) {
                return canadianTaxes;
            }

            // No Canadian breakdown on this order: it was placed outside Canada,
            // or before this plugin was installed. Falling back to Shopware's own
            // tax rows keeps the summary honest, instead of showing no tax at all
            // on an order whose total plainly includes some. A null name tells the
            // template to use the native label.
            return (this.order.price?.calculatedTaxes || [])
                .map((tax) => ({
                    taxDetails: {
                        rate: tax.taxRate,
                        tax: tax.tax,
                        name: null,
                    },
                }))
                .sort((a, b) => a.taxDetails.rate - b.taxDetails.rate);
        },

        sortedShippingTaxes() {
            if (!this.order || !this.order.lineItems) {
                return [];
            }

            return aggregateByRate(this.order.lineItems, 'inoceanShippingTaxInfo');
        },
    },
});
