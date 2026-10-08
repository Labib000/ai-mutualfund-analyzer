<?php

namespace App\Http\Requests\Portfolio;

use App\Rules\RupeeAmount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRedemptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'txn_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'all_units' => ['boolean'],
            'units' => ['exclude_if:all_units,true', 'required', 'regex:/^\d+(\.\d{1,3})?$/', 'numeric', 'gt:0'],
            'amount' => ['nullable', new RupeeAmount],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'txn_date.before_or_equal' => 'The date cannot be in the future.',
            'units.regex' => 'Units can have at most 3 decimals.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['txn_date' => 'date', 'amount' => 'amount received'];
    }

    public function txnDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('txn_date')->value());
    }

    /**
     * Units to redeem, or null to redeem everything held on the date.
     *
     * @return numeric-string|null
     */
    public function units(): ?string
    {
        return $this->boolean('all_units') ? null : UnitsInput::from($this->input('units'));
    }

    public function amountPaise(): ?int
    {
        return $this->filled('amount') ? Money::rupeesToPaise($this->string('amount')->value()) : null;
    }
}
