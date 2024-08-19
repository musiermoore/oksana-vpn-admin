<!-- resources/views/users/edit.blade.php -->
@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <h1>Edit User</h1>
    <form action="{{ route('users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
        </div>
        <div class="form-group">
            <label for="telegram">Telegram</label>
            <input type="text" name="telegram" id="telegram" class="form-control" value="{{ old('telegram', $user->telegram) }}" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control">{{ old('description', $user->description) }}</textarea>
        </div>
        <div class="form-group">
            <label for="join_at">Join At</label>
            <input type="date" name="join_at" id="join_at" class="form-control" value="{{ old('join_at', $user->join_at) }}" required>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
