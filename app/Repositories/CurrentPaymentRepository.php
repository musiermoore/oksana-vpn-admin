<?php

namespace App\Repositories;

use App\Models\CurrentPayment;

class CurrentPaymentRepository
{
    public function create(array $attributes): CurrentPayment
    {
        return CurrentPayment::create($attributes);
    }

    public function update(CurrentPayment $currentPayment, array $attributes): CurrentPayment
    {
        $currentPayment->update($attributes);

        return $currentPayment->refresh();
    }

    public function delete(CurrentPayment $currentPayment): void
    {
        $currentPayment->delete();
    }
}
