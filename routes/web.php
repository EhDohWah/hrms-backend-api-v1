<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]); // this is for web routes


Route::get('/', function () {
    return view('welcome');
});

Route::get('/job-offer', function () {
    return view('jobOffer');
});
