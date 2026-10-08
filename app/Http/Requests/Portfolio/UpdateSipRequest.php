<?php

namespace App\Http\Requests\Portfolio;

use App\Models\Sip;
use App\Rules\RupeeAmount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSipRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Sip $sip */
        $sip = $this->route('sip');

        // The end date can't fall before installments that already exist.
        $earliestEnd = ($sip->generated_until ?? $sip->start_date)->toDateString();

        return [
            'amount' => ['required', new RupeeAmount],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'end_date' => ['nullable', 'date_format:Y-m-d', "after_or_equal:{$earliestEnd}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date cannot be before an installment that has already been recorded.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['day_of_month' => 'day of the month'];
    }

    public function amountPaise(): int
    {
        return Money::rupeesToPaise($this->string('amount')->value());
    }

    public function endDate(): ?CarbonImmutable
    {
        return $this->filled('end_date') ? CarbonImmutable::parse($this->string('end_date')->value()) : null;
    }
}
