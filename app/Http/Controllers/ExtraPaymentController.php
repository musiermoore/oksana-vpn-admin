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

        return view('extra-payments.index', compact('payments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::all();
        $currentPayments = CurrentPayment::latest()->get();
        $activePeriodId = CurrentPayment::orderByDesc('start_date')->value('id');

        return view('extra-payments.create', compact(
            'users',
            'currentPayments',
            'activePeriodId'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (empty($request->user_id) || empty($request->current_payment_id) || empty($request->amount)) {
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
