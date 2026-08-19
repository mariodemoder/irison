<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'generate_abono' => ['nullable', 'boolean'],
        ];
    }
}
