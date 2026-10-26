<?php

declare(strict_types=1);

namespace App\DTOs\ServerCommandTemplate;

use App\DTOs\Data;

class ServerCommandTemplateData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $format,
        public string $content,
        public int $timeoutSeconds,
    ) {}

    /** @return array{name:string,description:?string,format:string,content:string,timeout_seconds:int} */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'format' => $this->format,
            'content' => $this->content,
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }
}
