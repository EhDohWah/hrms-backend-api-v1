<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Events\MyTestEvent;

Broadcast::routes(['middleware' => ['auth:api']]); // this is for web routes



Route::get('/', function () {
    return view('welcome');
});

Route::get('/job-offer', function () {
    return view('jobOffer');
});

Route::get('/broadcast-test', function () {
    event(new MyTestEvent('Hello, this is a Pusher test event!'));
    return response()->json(['success' => true, 'message' => 'Test event broadcasted']);
});