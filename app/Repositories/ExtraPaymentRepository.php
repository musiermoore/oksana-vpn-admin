<?php

namespace App\Repositories;

use App\Models\UserExtraPayment;

class ExtraPaymentRepository
{
    public function create(array $attributes): UserExtraPayment
    {
        return UserExtraPayment::create($attributes);
    }

    public function deleteById(string|int $id): void
    {
        UserExtraPayment::query()->whereKey($id)->delete();
    }
}
