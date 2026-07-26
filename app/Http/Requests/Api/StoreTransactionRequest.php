<?php

namespace App\Http\Requests\Api;

use App\DataObjects\TransactionInput;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'regex:'.Money::AMOUNT_PATTERN, 'not_in:0,0.0,0.00'],
            'currency' => ['required', 'string', 'size:3', Rule::in(array_keys(config('money.currencies')))],
            'occurred_on' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:1000'],
            'dimension_values' => ['array'],
            'dimension_values.*' => ['integer'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key');

        $this->merge(['idempotency_key' => is_string($key) && $key !== '' ? $key : null]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAmountInMinorUnits($validator);
            $this->validateCategoryAcceptsType($validator);
            $this->validateDimensionValues($validator);
        });
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    public function toInput(User $actor): TransactionInput
    {
        return new TransactionInput(
            userId: $actor->id,
            departmentId: $actor->department_id,
            type: TransactionType::from($this->string('type')->toString()),
            amount: $this->string('amount')->toString(),
            currency: $this->string('currency')->toString(),
            occurredOn: CarbonImmutable::parse($this->string('occurred_on')->toString()),
            categoryId: $this->integer('category_id'),
            note: $this->filled('note') ? $this->string('note')->toString() : null,
            dimensionValues: $this->dimensionValues(),
            idempotencyKey: $this->idempotencyKey(),
        );
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->input('idempotency_key');

        return is_string($key) && $key !== '' ? $key : null;
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
    private function validateAmountInMinorUnits(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['amount', 'currency'])) {
            return;
        }

        $currency = $this->string('currency')->toString();

        if (! Money::isSupported($currency)) {
            return;
        }

        $amountMinor = Money::toMinor($this->string('amount')->toString(), $currency);

        if ($amountMinor <= 0) {
            $validator->errors()->add('amount', __('errors.amount_not_positive'));

            return;
        }

        if ($amountMinor > Money::MAX_MINOR) {
            $validator->errors()->add('amount', __('errors.amount_too_large'));
        }
    }

    private function validateCategoryAcceptsType(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['type', 'category_id'])) {
            return;
        }

        $category = Category::find($this->integer('category_id'));
        $type = TransactionType::tryFrom($this->string('type')->toString());

        if (! $category instanceof Category || ! $type instanceof TransactionType) {
            return;
        }

        if ($category->acceptsType($type)) {
            return;
        }

        $validator->errors()->add('category_id', __('errors.category_rejects_type'));
    }

    private function validateDimensionValues(Validator $validator): void
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
