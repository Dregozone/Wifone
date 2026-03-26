<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('calls.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('online', function (User $user) {
    return ['id' => $user->id, 'name' => $user->name];
});
