<x-layout title="Edit Token">
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

        <div class="mb-2">
            @php
                $link = route('users.configs', ['userToken' => $userToken->token]);
            @endphp
            Link: <a href="{{ $link }}">{{ $link }}</a>
        </div>

        <div>
            <x-configs.user-token-configs
                :$userToken
                password="{{ $userToken->password }}"
            />
        </div>
    </div>
</x-layout>
