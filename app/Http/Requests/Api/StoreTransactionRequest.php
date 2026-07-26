<?php

namespace App\Http\Requests\Api;

use App\DataObjects\TransactionInput;
use App\Enums\TransactionType;
use App\Http\Requests\Api\Concerns\ResolvesActor;
use App\Http\Requests\Api\Concerns\ValidatesTransactionFields;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    use ResolvesActor, ValidatesTransactionFields;

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
            if (! $validator->errors()->hasAny(['amount', 'currency'])) {
                $this->validateAmountInMinorUnits(
                    $validator,
                    $this->string('amount')->toString(),
                    $this->string('currency')->toString(),
                );
            }

            if (! $validator->errors()->hasAny(['type', 'category_id'])) {
                $this->validateCategoryAcceptsType(
                    $validator,
                    $this->integer('category_id'),
                    TransactionType::tryFrom($this->string('type')->toString()),
                );
            }

            $this->validateDimensionValues($validator);
        });
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
}
