<?php

namespace App\Http\Controllers;

use App\Models\CurrentPayment;
use Illuminate\Http\Request;

class CurrentPaymentController extends Controller
{
    public function index()
    {
        $currentPayments = CurrentPayment::all();

        return $this->inertia('CurrentPayments/Index', [
            'current_payments' => $currentPayments
                ->map(fn (CurrentPayment $currentPayment) => $this->currentPaymentData($currentPayment))
                ->values(),
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

    public function store(Request $request)
    {
        CurrentPayment::create($request->post());
        return redirect()->route('current-payments.index');
    }

    public function edit(CurrentPayment $currentPayment)
    {
        return $this->inertia('CurrentPayments/Form', [
            'mode' => 'edit',
            'submit_url' => route('current-payments.update', $currentPayment),
            'current_payment' => $this->currentPaymentData($currentPayment),
        ]);
    }

    public function update(Request $request, CurrentPayment $currentPayment)
    {
        $currentPayment->update($request->post());
        return redirect()->route('current-payments.index');
    }

    public function destroy(CurrentPayment $currentPayment)
    {
        $currentPayment->delete();
        return redirect()->route('current-payments.index');
    }
}
