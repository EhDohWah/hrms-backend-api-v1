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

    public function show($id)
    {
        $letterTemplate = LetterTemplate::findOrFail($id);
        return response()->json($letterTemplate);
    }

    public function update(Request $request, $id)
    {
        $letterTemplate = LetterTemplate::findOrFail($id);
        $letterTemplate->update($request->all());
        return response()->json($letterTemplate);
    }

    public function destroy($id)
    {
        $letterTemplate = LetterTemplate::findOrFail($id);
        $letterTemplate->delete();
        return response()->json(null, 204);
    }

    

}
