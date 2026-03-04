<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\IndexSiteRequest;
use App\Http\Requests\OptionsSiteRequest;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Services\SiteService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Handles CRUD operations for organizational sites/locations.
 */
#[OA\Tag(name: 'Sites', description: 'API Endpoints for Site management')]
class SiteController extends BaseApiController
{
    public function __construct(
        private readonly SiteService $siteService,
    ) {}

    #[OA\Get(
        path: '/sites/options',
        summary: 'Get site options (lightweight)',
        description: 'Returns minimal site list for dropdowns',
        operationId: 'getSiteOptions',
        security: [['bearerAuth' => []]],
        tags: ['Sites']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function options(OptionsSiteRequest $request): JsonResponse
    {
        $sites = $this->siteService->options($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Site options retrieved successfully',
            'data' => $sites,
        ]);
    }

    #[OA\Get(
        path: '/sites',
        summary: 'Get all sites',
        description: 'Returns a paginated list of sites with optional filtering and search',
        operationId: 'getSites',
        security: [['bearerAuth' => []]],
        tags: ['Sites']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index(IndexSiteRequest $request): JsonResponse
    {
        $paginator = $this->siteService->list($request->validated());

        return SiteResource::collection($paginator)
            ->additional(['success' => true, 'message' => 'Sites retrieved successfully'])
            ->response();
    }

    #[OA\Post(
        path: '/sites',
        summary: 'Create a new site',
        description: 'Creates a new site and returns it',
        operationId: 'storeSite',
        security: [['bearerAuth' => []]],
        tags: ['Sites']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'code'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'code', type: 'string', maxLength: 20),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'address', type: 'string', nullable: true),
                new OA\Property(property: 'contact_person', type: 'string', nullable: true),
                new OA\Property(property: 'contact_phone', type: 'string', nullable: true),
                new OA\Property(property: 'contact_email', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', default: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Site created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreSiteRequest $request): JsonResponse
    {
        $site = $this->siteService->create($request->validated());

        return SiteResource::make($site)
            ->additional(['success' => true, 'message' => 'Site created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/sites/{id}',
        summary: 'Get a specific site',
        description: 'Returns a specific site by ID',
        operationId: 'getSite',
        security: [['bearerAuth' => []]],
        tags: ['Sites']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Site not found')]
    public function show(Site $site): JsonResponse
    {
        $site = $this->siteService->show($site);

        return SiteResource::make($site)
            ->additional(['success' => true, 'message' => 'Site retrieved successfully'])
            ->response();
    }

    #[OA\Put(
        path: '/sites/{id}',
        summary: 'Update a site',
        description: 'Updates a site and returns it',
        operationId: 'updateSite',
        security: [['bearerAuth' => []]],
        tags: ['Sites']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'code', type: 'string', maxLength: 20),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'address', type: 'string', nullable: true),
                new OA\Property(property: 'contact_person', type: 'string', nullable: true),
                new OA\Property(property: 'contact_phone', type: 'string', nullable: true),
                new OA\Property(property: 'contact_email', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Site updated successfully')]
    #[OA\Response(response: 404, description: 'Site not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateSiteRequest $request, Site $site): JsonResponse
    {
        $site = $this->siteService->update($site, $request->validated());

        return SiteResource::make($site)
            ->additional(['success' => true, 'message' => 'Site updated successfully'])
            ->response();
    }

    #[OA\Delete(
        path: '/sites/{id}',
        summary: 'Delete a site',
        description: 'Soft deletes a site',
        operationId: 'deleteSite',
        security: [['bearerAuth' => []]],
        tags: ['Sites']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Site deleted successfully')]
    #[OA\Response(response: 404, description: 'Site not found')]
    #[OA\Response(response: 422, description: 'Cannot delete site with active employments')]
    public function destroy(Site $site): JsonResponse
    {
        $this->siteService->delete($site);

        return $this->successResponse(null, 'Site deleted successfully');
    }

    /**
     * Batch delete multiple sites.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Site::findOrFail($id);
                $this->siteService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} site(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
