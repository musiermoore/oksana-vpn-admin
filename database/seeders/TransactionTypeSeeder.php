<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->items() as $item) {
            TransactionType::query()->updateOrCreate(
                ['slug' => $item['slug']],
                ['name' => $item['name']]
            );
        }
    }

    public function items(): array
    {
        return [
            [
                'name' => 'Пополнение',
                'slug' => TransactionType::SLUG_DEPOSIT,
            ],
            [
                'name' => 'Подписка',
                'slug' => TransactionType::SLUG_SUBSCRIPTION,
            ],
        ];
    }
}
