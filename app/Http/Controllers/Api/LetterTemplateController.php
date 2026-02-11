<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LetterTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LetterTemplateController extends Controller
{
    /**
     * List all letter templates with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|nullable|max:255',
                'sort_by' => 'string|nullable|in:recently_updated,recently_created,title_asc,title_desc',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $sortBy = $validated['sort_by'] ?? 'recently_updated';

            $query = LetterTemplate::select('id', 'title', 'created_by', 'created_at', 'updated_at');

            // Apply search filter
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->where('title', 'LIKE', "%{$searchTerm}%");
            }

            // Apply sorting
            switch ($sortBy) {
                case 'title_asc':
                    $query->orderBy('title', 'asc');
                    break;
                case 'title_desc':
                    $query->orderBy('title', 'desc');
                    break;
                case 'recently_created':
                    $query->orderBy('created_at', 'desc');
                    break;
                default: // recently_updated
                    $query->orderBy('updated_at', 'desc');
                    break;
            }

            $templates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Letter templates retrieved successfully',
                'data' => $templates->items(),
                'pagination' => [
                    'current_page' => $templates->currentPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                    'last_page' => $templates->lastPage(),
                    'from' => $templates->firstItem(),
                    'to' => $templates->lastItem(),
                    'has_more_pages' => $templates->hasMorePages(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving letter templates: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve letter templates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new letter template.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:200',
                'content' => 'required|string',
            ]);

            $validated['created_by'] = Auth::user()?->name ?? 'System';
            $validated['updated_by'] = $validated['created_by'];

            $letterTemplate = LetterTemplate::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Letter template created successfully',
                'data' => $letterTemplate,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating letter template: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create letter template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single letter template with full content.
     */
    public function show($id): JsonResponse
    {
        $letterTemplate = LetterTemplate::find($id);

        if (! $letterTemplate) {
            return response()->json([
                'success' => false,
                'message' => 'Letter template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Letter template retrieved successfully',
            'data' => $letterTemplate,
        ]);
    }

    /**
     * Update an existing letter template.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $letterTemplate = LetterTemplate::find($id);

        if (! $letterTemplate) {
            return response()->json([
                'success' => false,
                'message' => 'Letter template not found',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:200',
                'content' => 'sometimes|required|string',
            ]);

            $validated['updated_by'] = Auth::user()?->name ?? 'System';

            $letterTemplate->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Letter template updated successfully',
                'data' => $letterTemplate->fresh(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error updating letter template: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update letter template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a letter template.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $letterTemplate = LetterTemplate::findOrFail($id);
            $letterTemplate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Letter template deleted successfully',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Letter template not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting letter template: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete letter template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a PDF from a saved template by replacing placeholders with provided data.
     */
    public function generatePdf(Request $request, $id)
    {
        $letterTemplate = LetterTemplate::findOrFail($id);

        $placeholderData = $request->validate([
            'placeholders' => 'required|array',
            'placeholders.*' => 'nullable|string',
        ]);

        $placeholders = $placeholderData['placeholders'];

        // Get the template body content (Quill editor HTML)
        $templateBody = $letterTemplate->content;

        // Replace placeholders: {key} → value
        foreach ($placeholders as $key => $value) {
            $templateBody = str_replace('{'.$key.'}', $value ?? '', $templateBody);
        }

        // Wrap editor content in a full PDF-ready HTML document
        $logoPath = public_path('images/logo.png');
        $orgsPath = public_path('images/orgs.png');

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>'.e($letterTemplate->title).'</title>
    <style>
        @page {
            margin-top: 0.49in;
            margin-bottom: 0.19in;
            margin-left: 0.79in;
            margin-right: 0.79in;
        }
        body {
            font-family: Calibri, sans-serif;
            line-height: 1.5;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        p {
            text-align: left;
        }

        /* Quill editor alignment classes */
        .ql-align-center { text-align: center !important; }
        .ql-align-right { text-align: right !important; }
        .ql-align-justify { text-align: justify !important; }

        /* Quill editor font size classes */
        .ql-size-small { font-size: 0.75em; }
        .ql-size-large { font-size: 1.5em; }
        .ql-size-huge { font-size: 2.5em; }

        /* Quill editor font family classes */
        .ql-font-serif { font-family: Georgia, "Times New Roman", serif; }
        .ql-font-monospace { font-family: "Courier New", Courier, monospace; }

        /* Quill editor indentation classes */
        .ql-indent-1 { padding-left: 3em; }
        .ql-indent-2 { padding-left: 6em; }
        .ql-indent-3 { padding-left: 9em; }
        .ql-indent-4 { padding-left: 12em; }
        .ql-indent-5 { padding-left: 15em; }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header img {
            width: 200px;
        }
        .footer {
            font-size: 10px;
            color: #555;
            text-align: center;
            margin-top: 10px;
            padding-top: 10px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
        }
        .footer img {
            width: 150px;
        }
        .footer p {
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="header">
    <img src="'.$logoPath.'" alt="Logo">
</div>

<div class="template-content">
'.$templateBody.'
</div>

<div class="footer">
    <img src="'.$orgsPath.'" alt="BHF Logo">
    <p>
        BHF/SMRU Office | 78/1 Moo 5, Mae Ramat Sub-District, Mae Ramat District, Tak Province, 63140 | www.shoklo-unit.com<br>
        Phone: +66 55 532 026
    </p>
</div>

</body>
</html>';

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');

        $filename = str_replace(' ', '-', strtolower($letterTemplate->title)).'.pdf';

        return $pdf->stream($filename);
    }
}
