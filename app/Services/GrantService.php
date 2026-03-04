<?php

namespace App\Services;

use App\Enums\FundingAllocationStatus;
use App\Exceptions\DeletionBlockedException;
use App\Exports\GrantTemplateExport;
use App\Models\Grant;
use App\Notifications\GrantActionNotification;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GrantService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * List grants with filtering, sorting, and pagination.
     */
    public function list(array $params): array
    {
        $query = Grant::forPagination()
            ->withItemsCount()
            ->withOptimizedItems();

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('code', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('organization', 'LIKE', "%{$searchTerm}%");
            });
        }

        if (! empty($params['filter_organization'])) {
            $query->bySubsidiary($params['filter_organization']);
        }

        $sortBy = $params['sort_by'];
        $sortOrder = $params['sort_order'];

        if (in_array($sortBy, ['name', 'code'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        $grants = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        $appliedFilters = [];
        if (! empty($params['filter_organization'])) {
            $appliedFilters['organization'] = explode(',', $params['filter_organization']);
        }

        return [
            'grants' => $grants,
            'applied_filters' => $appliedFilters,
        ];
    }

    /**
     * Show a grant by ID with items and allocation counts.
     */
    public function show(Grant $grant): Grant
    {
        return $grant->load([
            'grantItems' => function ($q) {
                $q->select(
                    'id', 'grant_id', 'grant_position', 'grant_salary', 'grant_benefit',
                    'grant_level_of_effort', 'grant_position_number', 'budgetline_code',
                    'created_at', 'updated_at'
                )->withCount(['employeeFundingAllocations as active_allocations_count' => function ($query) {
                    $query->where('status', FundingAllocationStatus::Active);
                }]);
            },
        ]);
    }

    /**
     * Show a grant by code with items.
     */
    public function showByCode(string $code): Grant
    {
        return Grant::with([
            'grantItems' => function ($query) {
                $query->select('id', 'grant_id', 'grant_position', 'grant_salary', 'grant_benefit', 'grant_level_of_effort', 'grant_position_number');
            },
        ])
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Create a new grant.
     *
     * Note: created_by/updated_by are set automatically by model boot events.
     */
    public function store(array $data): Grant
    {
        $grant = Grant::create($data);

        $this->sendNotification($grant, 'created');

        return $grant;
    }

    /**
     * Update an existing grant.
     *
     * Note: updated_by is set automatically by model boot events.
     */
    public function update(Grant $grant, array $data): Grant
    {
        $grant->update($data);

        $this->sendNotification($grant, 'updated');

        return $grant;
    }

    /**
     * Delete a single grant (soft delete).
     *
     * @throws DeletionBlockedException
     */
    public function destroy(Grant $grant): void
    {
        $blockers = $grant->getDeletionBlockers();
        if (! empty($blockers)) {
            throw new DeletionBlockedException($blockers, 'Cannot delete grant');
        }

        $grantForNotification = (object) [
            'id' => $grant->id,
            'name' => $grant->name ?? 'Unknown Grant',
            'code' => $grant->code ?? 'N/A',
        ];

        $grant->delete();

        $this->sendNotification($grantForNotification, 'deleted');
    }

    /**
     * Batch delete grants (soft delete).
     */
    public function destroyBatch(array $ids): array
    {
        $succeeded = [];
        $failed = [];

        foreach ($ids as $id) {
            $grant = Grant::find($id);
            if (! $grant) {
                $failed[] = ['id' => $id, 'blockers' => ['Grant not found']];

                continue;
            }

            $blockers = $grant->getDeletionBlockers();
            if (! empty($blockers)) {
                $failed[] = ['id' => $id, 'blockers' => $blockers];

                continue;
            }

            $displayName = $grant->name ?? $grant->code ?? "Grant #{$id}";
            $grant->delete();
            $succeeded[] = [
                'id' => $id,
                'display_name' => $displayName,
            ];
        }

        $successCount = count($succeeded);

        $performedBy = auth()->user();
        if ($performedBy && $successCount > 0) {
            $names = collect($succeeded)->pluck('display_name')->take(3)->implode(', ');
            $message = $successCount > 3
                ? "{$names} and ".($successCount - 3).' more'
                : $names;

            $grantForNotification = (object) [
                'id' => null,
                'name' => "Bulk delete: {$message}",
                'code' => "{$successCount} grants",
            ];

            $this->notificationService->notifyByModule(
                'grants_list',
                new GrantActionNotification('deleted', $grantForNotification, $performedBy, 'grants_list'),
                'deleted'
            );
        }

        return [
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    /**
     * Get grant statistics with position recruitment status.
     */
    public function positions(array $params): array
    {
        $search = $params['search'] ?? null;

        $query = Grant::with([
            'grantItems' => function ($q) {
                $q->withCount(['employeeFundingAllocations as active_allocations_count' => function ($query) {
                    $query->where('status', FundingAllocationStatus::Active);
                }]);
            },
        ]);

        if ($search) {
            $escapedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $searchPattern = "%{$escapedSearch}%";

            $query->where(function ($q) use ($searchPattern) {
                $q->where('code', 'LIKE', $searchPattern)
                    ->orWhereHas('grantItems', function ($itemQuery) use ($searchPattern) {
                        $itemQuery->where('grant_position', 'LIKE', $searchPattern);
                    });
            });
        }

        $query->orderBy('created_at', 'desc');

        $grantsPaginated = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        $grantStats = [];

        foreach ($grantsPaginated as $grant) {
            $totalPositions = 0;
            $recruitedPositions = 0;
            $openPositions = 0;
            $grantPositions = [];

            foreach ($grant->grantItems as $item) {
                $manpower = (int) ($item->grant_position_number ?? 0);
                $activeAllocations = $item->active_allocations_count ?? 0;

                $totalPositions += $manpower;
                $recruitedPositions += $activeAllocations;
                $openPositions += max(0, $manpower - $activeAllocations);

                $grantPositions[] = [
                    'id' => $item->id,
                    'position' => $item->grant_position,
                    'budgetline_code' => $item->budgetline_code,
                    'manpower' => $manpower,
                    'recruited' => $activeAllocations,
                    'finding' => max(0, $manpower - $activeAllocations),
                ];
            }

            $status = 'Active';
            if ($grant->end_date && $grant->end_date < now()) {
                $status = 'Completed';
            } elseif ($recruitedPositions == $totalPositions && $totalPositions > 0) {
                $status = 'Completed';
            } elseif ($recruitedPositions == 0 && $totalPositions > 0) {
                $status = 'Pending';
            }

            $grantStats[] = [
                'grant_id' => $grant->id,
                'grant_code' => $grant->code,
                'grant_name' => $grant->name,
                'positions' => $grantPositions,
                'total_manpower' => $totalPositions,
                'total_recruited' => $recruitedPositions,
                'total_finding' => $openPositions,
                'status' => $status,
            ];
        }

        return [
            'data' => $grantStats,
            'paginator' => $grantsPaginated,
        ];
    }

    /**
     * Upload and process grant data from Excel file.
     */
    public function upload($file): array
    {
        $importId = uniqid('grant_import_');
        $userId = auth()->id();

        $grantsImport = new \App\Imports\GrantsImport($importId, $userId);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheets = $spreadsheet->getAllSheets();

        $sheetImport = new \App\Imports\GrantSheetImport($grantsImport);

        foreach ($sheets as $sheet) {
            $sheetImport->processSheet($sheet);
        }

        $processedGrants = $grantsImport->getProcessedGrants();
        $processedItems = $grantsImport->getProcessedItems();
        $errors = $grantsImport->getErrors();
        $skippedGrants = $grantsImport->getSkippedGrants();

        $responseData = [
            'processed_grants' => $processedGrants,
            'processed_items' => $processedItems,
        ];

        if (! empty($errors)) {
            $responseData['errors'] = $errors;
        }

        if (! empty($skippedGrants)) {
            $responseData['skipped_grants'] = $skippedGrants;
        }

        $message = 'Grant data import completed';
        if (! empty($errors)) {
            $message = 'Grant data import completed with errors';
        } elseif (! empty($skippedGrants)) {
            $message = 'Grant data import completed with skipped grants';
        }

        $grantsImport->sendCompletionNotification();

        return [
            'message' => $message,
            'data' => $responseData,
        ];
    }

    /**
     * Download grant import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $export = new GrantTemplateExport;
        $tempFile = $export->generate();
        $filename = $export->getFilename();

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Send grant action notification.
     */
    private function sendNotification(object $grant, string $action): void
    {
        $performedBy = auth()->user();

        if ($performedBy) {
            $this->notificationService->notifyByModule(
                'grants_list',
                new GrantActionNotification($action, $grant, $performedBy, 'grants_list'),
                $action
            );
        }
    }
}
