<?php

namespace App\Http\Requests\Portfolio;

use App\Rules\RupeeAmount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSipRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', new RupeeAmount],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
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

    public function startDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('start_date')->value());
    }

    public function endDate(): ?CarbonImmutable
    {
        return $this->filled('end_date') ? CarbonImmutable::parse($this->string('end_date')->value()) : null;
    }
}
