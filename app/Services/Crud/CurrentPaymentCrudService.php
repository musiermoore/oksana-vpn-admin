<?php

namespace App\Services\Crud;

use App\DTOs\CurrentPayment\CurrentPaymentData;
use App\Models\CurrentPayment;
use App\Repositories\CurrentPaymentRepository;

class CurrentPaymentCrudService
{
    public function __construct(
        private readonly CurrentPaymentRepository $currentPayments,
    ) {}

    public function create(CurrentPaymentData $data): CurrentPayment
    {
        return $this->currentPayments->create($data->toArray());
    }

    public function update(CurrentPayment $currentPayment, CurrentPaymentData $data): CurrentPayment
    {
        return $this->currentPayments->update($currentPayment, $data->toArray());
    }

    public function delete(CurrentPayment $currentPayment): void
    {
        $this->currentPayments->delete($currentPayment);
    }
}
