<!-- resources/views/users/index.blade.php -->
@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1>Users</h1>
        <a href="{{ route('users.create') }}" class="btn btn-primary">Create User</a>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Name</th>
            <th>Telegram</th>
            <th>Description</th>
            <th>Cкока должен</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->telegram }}</td>
                <td>{{ $user->description }}</td>
                <td>{{ max(0, $fullSum - $user->transactions_sum_amount) }}</td>
                <td>
                    <a href="{{ route('users.edit', $user->id) }}" class="btn btn-warning btn-sm">Edit</a>
                    <form action="{{ route('users.destroy', $user->id) }}" method="POST" style="display:inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
