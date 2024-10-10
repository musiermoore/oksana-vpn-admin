<!-- resources/views/users/index.blade.php -->
@extends('layouts.app')

@section('title', 'Участники')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1>Участники ({{ $users->count() }})</h1>
        <div>
            <a href="{{ route('users.create') }}" class="btn btn-primary">Добавить</a>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Telegram</th>
            <th>Имя</th>
            <th>Описание</th>
            <th>Баланс</th>
            <th>Долг</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($users as $user)
            <tr>
                <td>{{ $user->telegram }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->description }}</td>
                <td>{{ max(0, $user->transactions_sum_amount) }} ({{ max(0, $user->transactions_sum_amount - $user->payment_amount) }})</td>
                <td>{{ max(0, $user->payment_amount - $user->transactions_sum_amount) }}</td>
                <td>
                    <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                    <form action="{{ route('users.destroy', $user->id) }}" method="POST" style="display:inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm js-remove_confirmation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
