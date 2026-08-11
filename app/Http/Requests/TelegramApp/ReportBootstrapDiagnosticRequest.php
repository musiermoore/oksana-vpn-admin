<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\TelegramApp\ReportBootstrapDiagnosticData;
use App\Http\Requests\DataFormRequest;

class ReportBootstrapDiagnosticRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['required', 'string', 'max:120'],
            'error_message' => ['required', 'string', 'max:1000'],
            'error_name' => ['nullable', 'string', 'max:255'],
            'attempts' => ['nullable', 'integer', 'min:0', 'max:20'],
            'delay_ms' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'href' => ['nullable', 'string', 'max:2000'],
            'path' => ['nullable', 'string', 'max:500'],
            'search' => ['nullable', 'string', 'max:2000'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'user_agent' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', 'max:40'],
            'telegram_user_id' => ['nullable', 'string', 'max:255'],
            'telegram_start_param' => ['nullable', 'string', 'max:255'],
            'telegram_web_app_available' => ['nullable', 'boolean'],
            'telegram_platform' => ['nullable', 'string', 'max:255'],
            'telegram_version' => ['nullable', 'string', 'max:255'],
            'telegram_color_scheme' => ['nullable', 'string', 'max:255'],
            'telegram_init_data_source' => ['nullable', 'string', 'max:50'],
            'telegram_init_data_length' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'telegram_init_data_keys' => ['nullable', 'array', 'max:20'],
            'telegram_init_data_keys.*' => ['string', 'max:120'],
            'telegram_init_data_user_id' => ['nullable', 'string', 'max:255'],
            'telegram_init_data_auth_date' => ['nullable', 'string', 'max:255'],
            'telegram_init_data_query_id_prefix' => ['nullable', 'string', 'max:255'],
            'telegram_init_data_hash_prefix' => ['nullable', 'string', 'max:255'],
            'has_stored_token' => ['nullable', 'boolean'],
            'stored_telegram_user_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function dtoClass(): string
    {
        return ReportBootstrapDiagnosticData::class;
    }
}
