<?php declare(strict_types=1);

namespace InoceanSalesTaxesCanada\Core\Checkout\Cart\Tax;

/**
 * Works out which tax rates a promotion (discount) line should carry.
 *
 * A discount is not a thing you sell — it is a reduction of things you already
 * sold. So it must be taxed at the rates of the line items it actually reduced,
 * never at the province's default rate set.
 *
 * Getting this wrong is not cosmetic. Before this class existed, a discount on a
 * GST-only dish in a GST+PST province reversed PST that had never been charged,
 * so every discounted order under-collected tax and booked a negative PST
 * liability out of nothing.
 *
 * Shopware records, on the promotion line's payload, a `composition` array
 * saying exactly which line items the discount came off and by how much. That is
 * the authoritative apportioning key, so we use it whenever it is usable and
 * fall back to a price-weighted split across the taxed lines only if it is not.
 */
class PromotionTaxApportioner
{
    /**
     * Split a discount across the tax groups of the lines it discounted.
     *
     * @param float                             $promotionPrice Discount total (negative).
     * @param array<string, mixed>              $composition    Promotion payload `composition` entries.
     * @param array<string, array<string,float>> $ratesByLineId Tax rates (name => percent) per line item id.
     * @param array<string, float>              $priceByLineId  Total price per line item id (fallback weighting).
     *
     * @return array<string, array{rate: float, base: float}> Taxable base per tax name.
     */
    public function apportion(
        float $promotionPrice,
        array $composition,
        array $ratesByLineId,
        array $priceByLineId
    ): array {
        $weights = $this->weightsFromComposition($composition, $ratesByLineId);

        if ($weights === []) {
            $weights = $this->weightsFromPrices($priceByLineId, $ratesByLineId);
        }

        if ($weights === []) {
            // Nothing taxable to attribute the discount to (e.g. the whole cart
            // is tax-free). Reversing tax here would invent a liability, so we
            // deliberately return none.
            return [];
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0.0) {
            return [];
        }

        $result = [];
        foreach ($weights as $lineId => $weight) {
            $share = $promotionPrice * ($weight / $totalWeight);

            foreach ($ratesByLineId[$lineId] as $taxName => $taxRate) {
                if (!isset($result[$taxName])) {
                    $result[$taxName] = ['rate' => $taxRate, 'base' => 0.0];
                }
                $result[$taxName]['base'] += $share;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>               $composition
     * @param array<string, array<string,float>> $ratesByLineId
     *
     * @return array<string, float>
     */
    private function weightsFromComposition(array $composition, array $ratesByLineId): array
    {
        $weights = [];

        foreach ($composition as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $id = $entry['id'] ?? null;
            if (!\is_string($id) || !isset($ratesByLineId[$id])) {
                continue;
            }
            // A discount attributed to a line we have no rates for (or to a
            // tax-free line) must not drag the rest of the split around.
            if ($ratesByLineId[$id] === []) {
                continue;
            }
            $discount = abs((float) ($entry['discount'] ?? 0.0));
            if ($discount <= 0.0) {
                continue;
            }
            $weights[$id] = ($weights[$id] ?? 0.0) + $discount;
        }

        return $weights;
    }

    /**
     * @param array<string, float>               $priceByLineId
     * @param array<string, array<string,float>> $ratesByLineId
     *
     * @return array<string, float>
     */
    private function weightsFromPrices(array $priceByLineId, array $ratesByLineId): array
    {
        $weights = [];

        foreach ($priceByLineId as $lineId => $price) {
            if (!isset($ratesByLineId[$lineId]) || $ratesByLineId[$lineId] === []) {
                continue;
            }
            if ($price <= 0.0) {
                continue;
            }
            $weights[$lineId] = $price;
        }

        return $weights;
    }
}
