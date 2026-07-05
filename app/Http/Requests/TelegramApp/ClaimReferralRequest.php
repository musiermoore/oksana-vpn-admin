<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\TelegramApp\ClaimReferralData;
use App\Http\Requests\DataFormRequest;

class ClaimReferralRequest extends DataFormRequest
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

    protected function dtoClass(): string
    {
        return ClaimReferralData::class;
    }
}
