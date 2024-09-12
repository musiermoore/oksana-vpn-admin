<?php

namespace App\Http\Controllers;

use App\Models\UserToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserTokenController extends Controller
{
    public function index()
    {
        $userTokens = UserToken::query()
            ->where('expires_at', '>', now())
            ->orWhereNull('expires_at')
            ->get();

        return view('user-tokens.index', compact('userTokens'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();

        return view('user-tokens.create', compact('users'));
    }

    public function store(Request $request)
    {
        $user = User::find($request->user_id);

        $token = $user->tokens()->create([
            'token' => Str::random(40),
            'password' => Str::random(10)
        ]);

        return redirect()->route('user-tokens.show', $token->id);
    }

    public function destroy(UserToken $userToken)
    {
        $userToken->delete();
        return redirect()->route('user-tokens.index');
    }

    public function show(UserToken $userToken)
    {
        if ($userToken->expires_at && now()->gte(Carbon::parse($userToken->expires_at))) {
            abort(404);
        }

        return view('user-tokens.show', compact('userToken'));
    }
}
