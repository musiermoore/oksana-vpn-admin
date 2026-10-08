<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayGlobalConfig;

use App\DTOs\XrayGlobalConfig\XrayGlobalConfigData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Validator;

class UpdateXrayGlobalConfigRequest extends DataFormRequest
{
    protected function laravelData(): string
    {
        return XrayGlobalConfigData::class;
    }

    public function rules(): array
    {
        return [
            'dns_json' => ['required', 'string', 'json'],
            'routing_json' => ['required', 'string', 'json'],
            'rules_json' => ['required', 'string', 'json'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['dns_json' => 'DNS', 'routing_json' => 'Routing', 'rules_json' => 'Rules'] as $field => $label) {
                $value = json_decode((string) $this->input($field), true);
                $isRules = $field === 'rules_json';

                if (! is_array($value) || ($isRules && ! array_is_list($value))) {
                    $validator->errors()->add($field, $label.' JSON must be '.($isRules ? 'an array.' : 'an object.'));
                }
            }
        });
    }

    protected function dtoPayload(): array
    {
        return [
            'dns' => json_decode((string) $this->validated('dns_json'), true, 512, JSON_THROW_ON_ERROR),
            'routing' => json_decode((string) $this->validated('routing_json'), true, 512, JSON_THROW_ON_ERROR),
            'rules' => json_decode((string) $this->validated('rules_json'), true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}
