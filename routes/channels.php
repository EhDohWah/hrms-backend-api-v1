<?php

use Illuminate\Support\Facades\Broadcast;

// Channel definitions are temporarily commented out to fix route caching issue
// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// }, ['guards' => ['api']]);