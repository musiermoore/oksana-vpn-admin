<!-- resources/views/configs/create.blade.php -->
@extends('layouts.app')

@section('title', 'Create Config')

@section('content')
    <h1>Create Config</h1>
    <form action="{{ route('configs.store') }}" method="POST">
        @csrf
        <div x-data="{ existing: false }" class="form-group">
            <label for="user_id">User</label>
            <template x-if="existing">
                <select name="user_id" id="user_id" class="form-control" required>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </template>
            <template x-else>
                <input name="name" id="name" class="form-control" value="{{ old('name') }}" required>
            </template>
        </div>
        <x-configs.config-items
            :files="$fileNames"
            :configs="old('configs', [[]])"
        />

        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
