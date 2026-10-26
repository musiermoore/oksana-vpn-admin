<?php

declare(strict_types=1);

namespace App\Http\Requests\ServerCommandTemplate;

use App\DTOs\ServerCommandTemplate\ServerCommandTemplateData;
use App\Http\Requests\DataFormRequest;
use App\Models\ServerCommandTemplate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreServerCommandTemplateRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contentRules = ['required', 'string', 'max:1000000'];

        if ($this->input('format') === ServerCommandTemplate::FORMAT_BLOCKS) {
            $contentRules[] = 'json';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'format' => ['required', 'string', Rule::in(ServerCommandTemplate::formats())],
            'content' => $contentRules,
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->input('format') !== ServerCommandTemplate::FORMAT_BLOCKS) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $this->validateBlocks($validator);
        });
    }

    private function validateBlocks(Validator $validator): void
    {
        $blocks = $this->decodedBlocks();

        if ($blocks === null) {
            $validator->errors()->add('content', 'Укажите непустой массив blocks в JSON.');

            return;
        }

        foreach ($blocks as $block) {
            $this->validateBlock($validator, $block);
        }
    }

    /** @return array<int, mixed>|null */
    private function decodedBlocks(): ?array
    {
        $content = json_decode((string) $this->input('content'), true);
        $blocks = is_array($content) ? ($content['blocks'] ?? null) : null;

        if (! is_array($blocks) || ! array_is_list($blocks) || $blocks === []) {
            return null;
        }

        return $blocks;
    }

    private function validateBlock(Validator $validator, mixed $block): void
    {
        if (! is_array($block) || ! is_string($block['command'] ?? null) || trim($block['command']) === '') {
            $validator->errors()->add('content', 'В каждом блоке должна быть указана команда.');

            return;
        }

        $timeout = $block['timeout_seconds'] ?? null;

        if ($timeout !== null && (! is_int($timeout) || $timeout < 1 || $timeout > 3600)) {
            $validator->errors()->add('content', 'Тайм-аут блока должен быть целым числом от 1 до 3600 секунд.');
        }

        $this->validateAnswers($validator, $block['answers'] ?? []);
    }

    private function validateAnswers(Validator $validator, mixed $answers): void
    {
        if (! is_array($answers)) {
            $validator->errors()->add('content', 'Поле answers должно быть массивом.');

            return;
        }

        foreach ($answers as $answer) {
            if ($this->isInvalidAnswer($answer)) {
                $validator->errors()->add('content', 'Каждое правило answers должно содержать scan, answer и корректный send.');

                return;
            }
        }
    }

    private function isInvalidAnswer(mixed $answer): bool
    {
        return ! is_array($answer)
            || ! is_string($answer['scan'] ?? null)
            || trim($answer['scan']) === ''
            || ! is_string($answer['answer'] ?? null)
            || ! in_array($answer['send'] ?? 'enter', ['enter', 'raw'], true);
    }

    protected function laravelData(): string
    {
        return ServerCommandTemplateData::class;
    }
}
