<?php

namespace App\Http\Requests\Api;

use App\Enums\TransactionType;
use App\Http\Requests\Api\Concerns\ResolvesActor;
use App\Models\Dimension;
use App\Support\TransactionFilters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class TransactionFilterRequest extends FormRequest
{
    use ResolvesActor;

    /** @var Collection<string, int>|null */
    private ?Collection $dimensionIdsByKey = null;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(array_keys(config('money.currencies')))],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'dimension' => ['array'],
            'dimension.*' => ['integer', Rule::exists('dimension_values', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['dimension'])) {
                return;
            }

            foreach ($this->submittedDimensions() as $key => $valueId) {
                if ($this->dimensionIdsByKey()->has($key)) {
                    continue;
                }

                $validator->errors()->add('dimension', __('errors.dimension_unknown', ['dimension' => $key]));
            }
        });
    }

    /**
     * `user_id` and `department_id` are read here as plain filters. They narrow the
     * caller's scope and are never consulted when that scope is resolved.
     */
    public function filters(): TransactionFilters
    {
        return new TransactionFilters(
            from: $this->dateOrNull('from'),
            to: $this->dateOrNull('to'),
            type: $this->filled('type') ? TransactionType::from($this->string('type')->toString()) : null,
            categoryId: $this->filled('category_id') ? $this->integer('category_id') : null,
            currency: $this->filled('currency') ? $this->string('currency')->toString() : null,
            userId: $this->filled('user_id') ? $this->integer('user_id') : null,
            departmentId: $this->filled('department_id') ? $this->integer('department_id') : null,
            dimensions: $this->dimensions(),
        );
    }

    private function dateOrNull(string $key): ?CarbonImmutable
    {
        if (! $this->filled($key)) {
            return null;
        }

        return CarbonImmutable::parse($this->string($key)->toString());
    }

    /** @return array<int, int> */
    private function dimensions(): array
    {
        $dimensions = [];

        foreach ($this->submittedDimensions() as $key => $valueId) {
            $dimensionId = $this->dimensionIdsByKey()->get($key);

            if ($dimensionId !== null) {
                $dimensions[$dimensionId] = $valueId;
            }
        }

        return $dimensions;
    }

    /** @return array<string, int> */
    private function submittedDimensions(): array
    {
        $submitted = $this->input('dimension');

        if (! is_array($submitted)) {
            return [];
        }

        $dimensions = [];

        foreach ($submitted as $key => $valueId) {
            if (is_string($key) && is_numeric($valueId)) {
                $dimensions[$key] = (int) $valueId;
            }
        }

        return $dimensions;
    }

    /** @return Collection<string, int> */
    private function dimensionIdsByKey(): Collection
    {
        /** @var Collection<string, int> $keys */
        $keys = $this->dimensionIdsByKey ??= Dimension::query()->pluck('id', 'key');

        return $keys;
    }
}
