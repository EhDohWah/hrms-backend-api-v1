<?php

use App\Http\Controllers\Api\LetterTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Letter Template Routes
|--------------------------------------------------------------------------
|
| CRUD routes for managing letter templates used in the Document Templating
| System. Templates contain CKEditor HTML with {placeholder} tokens that
| are replaced with real data during PDF generation.
|
| Permission Model:
| - Read: GET requests (view list, details)
| - Edit: POST/PUT/DELETE requests (create, update, delete, generate PDF)
|
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('letter-templates')->middleware('module.permission:letter_templates')->group(function () {
        // List all templates (title, id, timestamps — no content for performance)
        Route::get('/', [LetterTemplateController::class, 'index']);

        // Create a new template
        Route::post('/', [LetterTemplateController::class, 'store']);

        // Show a single template (includes full content)
        Route::get('/{id}', [LetterTemplateController::class, 'show']);

        // Update an existing template
        Route::put('/{id}', [LetterTemplateController::class, 'update']);

        // Delete a template
        Route::delete('/{id}', [LetterTemplateController::class, 'destroy']);

        // Generate PDF with placeholder replacement
        Route::post('/{id}/generate-pdf', [LetterTemplateController::class, 'generatePdf']);
    });
});
