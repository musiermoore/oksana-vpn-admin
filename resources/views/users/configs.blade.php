<x-layout title="Конфиг">
    <h1>
        Конфиг
    </h1>
    <div>
        @if ($isPasswordCorrect)
            <div class="alert alert-danger">
                Ссылка станет недоступна через
                {{ (int) now()->diffInMinutes(\Carbon\Carbon::parse($userToken->expires_at), true) }} мин.
            </div>
        @endif

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
</x-layout>
