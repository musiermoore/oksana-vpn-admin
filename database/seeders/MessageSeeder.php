<?php

namespace Database\Seeders;

use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        Message::query()->updateOrCreate(
            ['slug' => Message::SLUG_WELCOME_BASIC],
            [
                'name' => 'Welcome Basic',
                'text' => '',
            ],
        );

        Message::query()->updateOrCreate(
            ['slug' => Message::SLUG_WELCOME_EXTENDED],
            [
                'name' => 'Welcome Extended',
                'text' => '',
            ],
        );
    }
}
