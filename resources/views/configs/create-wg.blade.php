<!-- resources/views/configs/create.blade.php -->
@extends('layouts.app')

@section('title', 'Create Config')

@section('content')
    <h1>Create WG Config</h1>
    <form action="{{ route('configs-wg.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="name">User</label>
            <input name="name" id="name" class="form-control" value="{{ old('name') }}" required>
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
