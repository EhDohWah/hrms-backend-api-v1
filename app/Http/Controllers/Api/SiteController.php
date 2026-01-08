<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSiteRequest;
use App\Http\Requests\ListSiteOptionsRequest;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Sites', description: 'API Endpoints for Site management')]
class SiteController extends Controller
{
    /**
     * Lightweight list for dropdowns
     */
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
    public function options(ListSiteOptionsRequest $request)
    {
        $validated = $request->validated();

        $query = Site::query();

        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', "%{$validated['search']}%")
                    ->orWhere('code', 'like', "%{$validated['search']}%");
            });
        }

        if (isset($validated['is_active'])) {
            $validated['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        $sites = $query
            ->orderBy('name', 'asc')
            ->limit($validated['limit'] ?? 200)
            ->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'message' => 'Site options retrieved successfully',
            'data' => $sites,
        ]);
    }

    /**
     * Get all sites with optional filtering and pagination
     */
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
    public function index(IndexSiteRequest $request)
    {
        $validated = $request->validated();

        $query = Site::withCounts();

        // Apply search filter
        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', "%{$validated['search']}%")
                    ->orWhere('code', 'like', "%{$validated['search']}%")
                    ->orWhere('description', 'like', "%{$validated['search']}%");
            });
        }

        // Apply active status filter
        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate results
        $perPage = $validated['per_page'] ?? 20;
        $sites = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Sites retrieved successfully',
            'data' => SiteResource::collection($sites)->response()->getData(),
        ]);
    }

    /**
     * Store a new site
     */
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
    public function store(StoreSiteRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id() ?? 'system';

        $site = Site::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Site created successfully',
            'data' => new SiteResource($site),
        ], 201);
    }

    /**
     * Get a specific site
     */
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
    public function show($id)
    {
        $site = Site::withCounts()->find($id);

        if (! $site) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Site retrieved successfully',
            'data' => new SiteResource($site),
        ]);
    }

    /**
     * Update a site
     */
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
    public function update(UpdateSiteRequest $request, $id)
    {
        $site = Site::find($id);

        if (! $site) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found',
            ], 404);
        }

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id() ?? 'system';

        $site->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Site updated successfully',
            'data' => new SiteResource($site->fresh()),
        ]);
    }

    /**
     * Delete a site
     */
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
    public function destroy($id)
    {
        $site = Site::withCounts()->find($id);

        if (! $site) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found',
            ], 404);
        }

        // Check if site has active employments
        if ($site->active_employments_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete site with {$site->active_employments_count} active employments. Please reassign employments first.",
            ], 422);
        }

        $site->delete();

        return response()->json([
            'success' => true,
            'message' => 'Site deleted successfully',
        ]);
    }
}
