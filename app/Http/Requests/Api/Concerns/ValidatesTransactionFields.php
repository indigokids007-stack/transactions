<?php

namespace App\Http\Requests\Api\Concerns;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Support\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * The domain rules a transaction has to satisfy whichever way it is written. Creating
 * and editing share them so the two paths cannot drift apart; each request decides
 * which fields it feeds in, and an edit feeds the stored value for anything it did
 * not receive.
 */
trait ValidatesTransactionFields
{
    /**
     * The declarative half of those rules. Creating demands every field, editing takes
     * whichever ones it is given, and that presence marker is the only difference
     * between the two, so a change to the note length or the future date window cannot
     * reach one path without the other.
     *
     * @param  'required'|'sometimes'  $presence
     * @return array<string, array<int, mixed>>
     */
    protected function transactionFieldRules(string $presence): array
    {
        return [
            'type' => [$presence, Rule::enum(TransactionType::class)],
            'amount' => [$presence, 'regex:'.Money::AMOUNT_PATTERN, 'not_in:0,0.0,0.00'],
            'currency' => [$presence, 'string', 'size:3', Rule::in(array_keys(config('money.currencies')))],
            'occurred_on' => [$presence, 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'category_id' => [$presence, 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'dimension_values' => ['sometimes', 'array'],
            'dimension_values.*' => ['integer'],
        ];
    }

    /** @return array<int, int> */
    public function dimensionValues(): array
    {
        $submitted = $this->input('dimension_values');

        if (! is_array($submitted)) {
            return [];
        }

        $values = [];

        foreach ($submitted as $dimensionId => $valueId) {
            if (! is_numeric($dimensionId) || ! is_numeric($valueId)) {
                continue;
            }

            $values[(int) $dimensionId] = (int) $valueId;
        }

        return $values;
    }

    /**
     * The same invariant `CreateTransaction` asserts, checked here so an HTTP caller
     * gets a 422 on the amount field instead of an unhandled exception. Amounts with
     * more decimals than the currency carries are still accepted and rounded; what is
     * rejected is an amount that rounds away to nothing, or one large enough to
     * threaten the column.
     */
    protected function validateAmountInMinorUnits(Validator $validator, string $amount, string $currency): void
    {
        if (! Money::isSupported($currency)) {
            return;
        }

        $amountMinor = Money::toMinor($amount, $currency);

        if ($amountMinor <= 0) {
            $validator->errors()->add('amount', __('errors.amount_not_positive'));

            return;
        }

        if ($amountMinor > Money::MAX_MINOR) {
            $validator->errors()->add('amount', __('errors.amount_too_large'));
        }
    }

    protected function validateCategoryAcceptsType(Validator $validator, ?int $categoryId, ?TransactionType $type): void
    {
        $category = $categoryId === null ? null : Category::find($categoryId);

        if (! $category instanceof Category || ! $type instanceof TransactionType) {
            return;
        }

        if ($category->acceptsType($type)) {
            return;
        }

        $validator->errors()->add('category_id', __('errors.category_rejects_type'));
    }

    protected function validateDimensionValues(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['dimension_values'])) {
            return;
        }

        $submitted = $this->dimensionValues();
        $dimensions = Dimension::query()->where('is_active', true)->get()->keyBy('id');

        $this->validateRequiredDimensions($validator, $dimensions, $submitted);
        $this->validateSubmittedDimensionValues($validator, $dimensions, $submitted);
    }

    /**
     * @param  Collection<int, Dimension>  $dimensions
     * @param  array<int, int>  $submitted
     */
    private function validateRequiredDimensions(Validator $validator, Collection $dimensions, array $submitted): void
    {
        $missing = $dimensions
            ->filter(fn (Dimension $dimension) => $dimension->is_required)
            ->reject(fn (Dimension $dimension) => array_key_exists($dimension->id, $submitted));

        foreach ($missing as $dimension) {
            $validator->errors()->add('dimension_values', __('errors.dimension_required', [
                'dimension' => $dimension->name,
            ]));
        }
    }

    /**
     * @param  Collection<int, Dimension>  $dimensions
     * @param  array<int, int>  $submitted
     */
    private function validateSubmittedDimensionValues(Validator $validator, Collection $dimensions, array $submitted): void
    {
        if ($submitted === []) {
            return;
        }

        $values = DimensionValue::query()->whereIn('id', $submitted)->get()->keyBy('id');

        foreach ($submitted as $dimensionId => $valueId) {
            $value = $values->get($valueId);

            if ($dimensions->get($dimensionId) instanceof Dimension
                && $value instanceof DimensionValue
                && $value->is_active
                && $value->dimension_id === $dimensionId) {
                continue;
            }

            $validator->errors()->add('dimension_values', __('errors.dimension_value_invalid'));
        }
    }
}
