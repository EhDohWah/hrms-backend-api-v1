<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LetterTemplateController extends Controller
{
    //
    public function index()
    {
        $letterTemplates = LetterTemplate::all();
        return response()->json($letterTemplates);
    }

    public function store(Request $request)
    {
        $letterTemplate = LetterTemplate::create($request->all());
        return response()->json($letterTemplate, 201);
    }
    
    
}
