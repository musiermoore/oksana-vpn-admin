<!-- resources/views/configs/index.blade.php -->
@extends('layouts.app')

@section('title', 'Configs')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1>Tokens</h1>
        <a href="{{ route('user-tokens.create') }}" class="btn btn-primary">Create Token</a>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>User</th>
            <th>Expires At</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($userTokens as $token)
            <tr>
                <td>{{ $token->user->name }}</td>
                <td>{{ $token->expires_at }}</td>
                <td>
                    <a href="{{ route('user-tokens.show', $token->id) }}" class="btn btn-warning btn-sm">Show</a>
                    <form action="{{ route('user-tokens.destroy', $token->id) }}" method="POST" style="display:inline-block;">
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
