<?php

namespace App\Http\Requests\Portfolio;

use App\Rules\RupeeAmount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
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
            'amount' => ['required', new RupeeAmount],
            'units' => ['nullable', 'regex:/^\d+(\.\d{1,3})?$/', 'numeric', 'gt:0'],
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
        return ['txn_date' => 'date'];
    }

    public function txnDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('txn_date')->value());
    }

    public function amountPaise(): int
    {
        return Money::rupeesToPaise($this->string('amount')->value());
    }

    /**
     * @return numeric-string|null
     */
    public function unitsOverride(): ?string
    {
        return UnitsInput::from($this->input('units'));
    }
}
