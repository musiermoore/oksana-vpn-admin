<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentPaymentResource;
use App\Http\Requests\CurrentPayment\StoreCurrentPaymentRequest;
use App\Http\Requests\CurrentPayment\UpdateCurrentPaymentRequest;
use App\Models\CurrentPayment;
use App\Services\Crud\CurrentPaymentCrudService;

class CurrentPaymentController extends Controller
{
    public function __construct(
        private readonly CurrentPaymentCrudService $currentPaymentService,
    ) {}

    public function index()
    {
        $currentPayments = CurrentPayment::all();

        return $this->inertia('CurrentPayments/Index', [
            'current_payments' => CurrentPaymentResource::collection($currentPayments),
        ]);
    }

    public function create()
    {
        $subMonth = now()->day < 21 ? 1 : 0;

        return $this->inertia('CurrentPayments/Form', [
            'mode' => 'create',
            'submit_url' => route('current-payments.store'),
            'current_payment' => [
                'start_date' => now()->subMonths($subMonth)->format('Y-m-21'),
                'end_date' => now()->addMonth()->subMonths($subMonth)->format('Y-m-21'),
                'amount' => CurrentPayment::getHostingPrice(),
            ],
        ]);
    }

    public function store(StoreCurrentPaymentRequest $request)
    {
        $this->currentPaymentService->create($request->toDto());

        return redirect()->route('current-payments.index');
    }

    public function edit(CurrentPayment $currentPayment)
    {
        return $this->inertia('CurrentPayments/Form', [
            'mode' => 'edit',
            'submit_url' => route('current-payments.update', $currentPayment),
            'current_payment' => new CurrentPaymentResource($currentPayment),
        ]);
    }

    public function update(UpdateCurrentPaymentRequest $request, CurrentPayment $currentPayment)
    {
        $this->currentPaymentService->update($currentPayment, $request->toDto());

        return redirect()->route('current-payments.index');
    }

    public function destroy(CurrentPayment $currentPayment)
    {
        $this->currentPaymentService->delete($currentPayment);

        return redirect()->route('current-payments.index');
    }
}
