<!-- resources/views/configs/show.blade.php -->
@extends('layouts.app')

@section('title', 'Edit Token')

@section('content')
    <h1>Show Token</h1>
    <div>
        @csrf
        @method('PUT')
        <div class="form-group">
            <label>User</label>
            <input
                type="text"
                class="form-control"
                value="{{ $userToken->user->telegram }} ({{ $userToken->user->name }})"
                readonly
            >
        </div>
        <div class="form-group">
            <label>Token</label>
            <input
                type="text"
                class="form-control"
                value="{{ $userToken->token }}"
                readonly
            >
        </div>
        <div class="form-group">
            <label>Password</label>
            <input
                type="text"
                class="form-control"
                value="{{ $userToken->password }}"
                readonly
            >
        </div>
    </div>
@endsection
