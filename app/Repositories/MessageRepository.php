<?php

namespace App\Repositories;

use App\Models\Message;
use Illuminate\Support\Collection;

class MessageRepository
{
    public function getBySlugs(array $slugs): Collection
    {
        return Message::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');
    }

    public function updateOrCreateBySlug(string $slug, array $attributes): Message
    {
        return Message::query()->updateOrCreate(
            ['slug' => $slug],
            $attributes,
        );
    }
}
