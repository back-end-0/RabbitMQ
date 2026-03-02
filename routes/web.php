<?php

use App\Events\UserRegistered;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-event', function () {
    $user = User::first() ?? User::factory()->create();

    UserRegistered::dispatch($user);

    return response()->json([
        'message' => 'UserRegistered event dispatched to RabbitMQ!',
        'user' => $user->email,
    ]);
});
