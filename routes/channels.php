<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('server-console.{sessionUuid}', function ($user, string $sessionUuid): bool {
    return (bool) $user->is_admin && \App\Models\ServerConsoleSession::query()
        ->where('uuid', $sessionUuid)
        ->where('user_id', $user->id)
        ->exists();
});
