<?php

namespace App\Http\Controllers;

use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserExtraPayment;
use Illuminate\Http\Request;

class ExtraPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = UserExtraPayment::query()
            ->with(['user', 'currentPayment'])
            ->latest()
            ->get();

        return $this->inertia('ExtraPayments/Index', [
            'payments' => $payments->map(fn (UserExtraPayment $payment) => $this->extraPaymentData($payment))->values(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::all();
        $currentPayments = CurrentPayment::latest()->get();
        $activePeriodId = CurrentPayment::getActivePaymentPeriodId();

        return $this->inertia('ExtraPayments/Create', [
            'submit_url' => route('extra-payments.store'),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'current_payments' => $currentPayments
                ->map(fn (CurrentPayment $currentPayment) => $this->currentPaymentData($currentPayment))
                ->values(),
            'active_period_id' => $activePeriodId,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (empty($request->user_id) || empty($request->current_payment_id) || $request->amount < 0) {
            return redirect()->back()
                ->with('error', 'Невалидные данные.');
        }

        UserExtraPayment::create([
            'user_id' => $request->user_id,
            'current_payment_id' => $request->current_payment_id,
            'amount' => $request->amount,
        ]);

        return redirect()->route('extra-payments.index')
            ->with('success', 'Доп. оплата успешно добавлена.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        UserExtraPayment::whereId($id)->delete();

        return redirect()->route('extra-payments.index')
            ->with('success', 'Доп. оплата успешно удалена.');
    }
}
