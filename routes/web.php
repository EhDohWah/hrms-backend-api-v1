<?php

use Illuminate\Support\Facades\Route;
use App\Events\MyTestEvent;



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