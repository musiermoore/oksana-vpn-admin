<?php

namespace App\Http\Requests\TelegramApp;

use Illuminate\Foundation\Http\FormRequest;

class ClaimReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referral' => ['required', 'string', 'max:500'],
        ];
    }

    public function referralInput(): string
    {
        return trim((string) $this->validated('referral'));
    }
}
