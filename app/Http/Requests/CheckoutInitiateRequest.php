<?php

namespace App\Http\Requests;

use App\Enums\BillingInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutInitiateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'module_slugs' => ['required', 'array', 'min:1'],
            'module_slugs.*' => ['string', Rule::exists('modules', 'slug')->where('is_active', true)],
            'billing_interval' => ['required', Rule::enum(BillingInterval::class)],
        ];
    }
}
