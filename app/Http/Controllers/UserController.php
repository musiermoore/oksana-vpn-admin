<?php

namespace App\Http\Controllers;

use App\Models\CurrentPayment;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $fullSum = CurrentPayment::getHostingPriceForAllMonths();

        $users = User::query()
            ->withSum('transactions', 'amount')
            ->get();

        return view('users.index', compact('users', 'fullSum'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        User::create($request->all());
        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->all());
        return redirect()->route('users.index');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index');
    }
}
