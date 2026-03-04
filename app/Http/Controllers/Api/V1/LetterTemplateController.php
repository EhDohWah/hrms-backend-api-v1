<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\GeneratePdfLetterTemplateRequest;
use App\Http\Requests\IndexLetterTemplateRequest;
use App\Http\Requests\StoreLetterTemplateRequest;
use App\Http\Requests\UpdateLetterTemplateRequest;
use App\Http\Resources\LetterTemplateResource;
use App\Models\LetterTemplate;
use App\Services\LetterTemplateService;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD operations and PDF generation for letter templates.
 */
class LetterTemplateController extends BaseApiController
{
    public function __construct(
        private readonly LetterTemplateService $letterTemplateService
    ) {}

    /**
     * List all letter templates with pagination, search, and sorting.
     *
     * The list response excludes template content for performance.
     * Use the show endpoint to retrieve full content.
     */
    public function index(IndexLetterTemplateRequest $request): JsonResponse
    {
        $templates = $this->letterTemplateService->list($request->validated());

        return LetterTemplateResource::collection($templates)
            ->additional([
                'success' => true,
                'message' => 'Letter templates retrieved successfully',
            ])
            ->response();
    }

    /**
     * Show a single letter template with full content.
     */
    public function show(LetterTemplate $letterTemplate): JsonResponse
    {
        return $this->successResponse(
            new LetterTemplateResource($letterTemplate),
            'Letter template retrieved successfully'
        );
    }

    /**
     * Store a new letter template.
     */
    public function store(StoreLetterTemplateRequest $request): JsonResponse
    {
        $letterTemplate = $this->letterTemplateService->create(
            $request->validated(),
            $request->user()
        );

        return $this->createdResponse(
            new LetterTemplateResource($letterTemplate),
            'Letter template created successfully'
        );
    }

    /**
     * Update an existing letter template.
     */
    public function update(UpdateLetterTemplateRequest $request, LetterTemplate $letterTemplate): JsonResponse
    {
        $letterTemplate = $this->letterTemplateService->update(
            $letterTemplate,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(
            new LetterTemplateResource($letterTemplate),
            'Letter template updated successfully'
        );
    }

    /**
     * Delete a letter template.
     */
    public function destroy(LetterTemplate $letterTemplate): JsonResponse
    {
        $this->letterTemplateService->delete($letterTemplate);

        return $this->successResponse(null, 'Letter template deleted successfully');
    }

    /**
     * Generate a PDF from a saved template by replacing placeholders with provided data.
     *
     * @return \Illuminate\Http\Response
     */
    public function generatePdf(GeneratePdfLetterTemplateRequest $request, LetterTemplate $letterTemplate)
    {
        return $this->letterTemplateService->generatePdf(
            $letterTemplate,
            $request->validated()['placeholders']
        );
    }
}
