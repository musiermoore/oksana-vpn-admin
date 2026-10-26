<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\ServerCommandTemplate\ServerCommandTemplateData;
use App\Models\ServerCommandTemplate;

class ServerCommandTemplateCrudService
{
    public function create(ServerCommandTemplateData $data): ServerCommandTemplate
    {
        return ServerCommandTemplate::query()->create($data->toAttributes());
    }

    public function update(ServerCommandTemplate $template, ServerCommandTemplateData $data): ServerCommandTemplate
    {
        $template->update($data->toAttributes());

        return $template->refresh();
    }

    public function delete(ServerCommandTemplate $template): void
    {
        $template->delete();
    }
}
