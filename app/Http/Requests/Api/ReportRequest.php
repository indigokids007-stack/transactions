<?php

namespace App\Http\Requests\Api;

use App\Reports\SummaryGrouping;
use App\Reports\TrendInterval;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * The report parameters are the list filters plus the two the reports add, so this request
 * inherits the filter rules rather than restating them: a filter accepted by the ledger is
 * accepted by a report, and resolved the same way.
 */
class ReportRequest extends TransactionFilterRequest
{
    private ?SummaryGrouping $grouping = null;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'group_by' => ['nullable', 'string'],
            'interval' => ['nullable', Rule::enum(TrendInterval::class)],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['group_by'])) {
                return;
            }

            if ($this->tryGrouping() instanceof SummaryGrouping) {
                return;
            }

            $validator->errors()->add('group_by', __('errors.group_by_unknown', ['group_by' => $this->groupBy()]));
        });
    }

    public function grouping(): SummaryGrouping
    {
        $grouping = $this->tryGrouping();

        if (! $grouping instanceof SummaryGrouping) {
            throw new InvalidArgumentException("Unknown grouping `{$this->groupBy()}`.");
        }

        return $grouping;
    }

    public function trendInterval(): TrendInterval
    {
        if (! $this->filled('interval')) {
            return TrendInterval::Day;
        }

        return TrendInterval::from($this->string('interval')->toString());
    }

    private function tryGrouping(): ?SummaryGrouping
    {
        return $this->grouping ??= SummaryGrouping::resolve($this->groupBy());
    }

    private function groupBy(): string
    {
        if (! $this->filled('group_by')) {
            return SummaryGrouping::DEFAULT;
        }

        return $this->string('group_by')->toString();
    }
}
