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
            @foreach ($userToken->user->configs as $key => $config)
                <h4>
                    Конфиг <b>{{ $config->name }}</b>
                </h4>
                <div>
                    @php
                    $params = ['userToken' => $userToken->token, 'config' => $config->id, 'password' => request()->password]
                    @endphp

                    <a href="{{ route('users.configs.qr-code', $params) }}" target="_blank">QR-Code</a>
                    <a href="{{ route('users.configs.download', $params) }}">Скачать</a>
                </div>
            @endforeach
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
