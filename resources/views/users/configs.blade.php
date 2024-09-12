<!-- resources/views/configs/show.blade.php -->
@extends('layouts.app')

@section('title', 'Конфиг')

@section('content')
    <h1>
        Конфиг
    </h1>
    <div>
        @csrf
        @method('PUT')
        <div class="form-group">
            <label>Пользователь</label>
            <input
                type="text"
                class="form-control"
                value="{{ $userToken->user->telegram }}"
                readonly
            >
        </div>
        @if ($isPasswordCorrect)
            <x-configs.user-token-configs
                :$userToken
                password="{{ request()->password }}"
            />
        @else
            <form action="{{ route('users.configs', $userToken->token) }}">
                <div class="form-group">
                    <label>Пароль</label>
                    <input
                        type="password"
                        class="form-control"
                        name="password"
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    Отправить
                </button>
            </form>
        @endif
    </div>
@endsection
