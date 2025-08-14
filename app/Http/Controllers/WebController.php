<?php

namespace App\Http\Controllers;

use App\Events\MyTestEvent;

class WebController extends Controller
{
    public function welcome()
    {
        return view('welcome');
    }

    public function jobOffer()
    {
        return view('jobOffer');
    }

    public function broadcastTest()
    {
        event(new MyTestEvent('Hello, this is a Pusher test event!'));
        return response()->json(['success' => true, 'message' => 'Test event broadcasted']);
    }
}