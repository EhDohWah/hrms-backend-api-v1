<?php

namespace App\Services;

use App\Enums\FundingAllocationStatus;
use App\Models\GrantItem;
use App\Notifications\GrantItemActionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class GrantItemService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Retrieve all grant items with grant relationship.
     */
    public function list(): Collection
    {
        return GrantItem::with(['grant:id,code,name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Show a single grant item with grant relationship and allocation count.
     */
    public function show(GrantItem $grantItem): GrantItem
    {
        return $grantItem->load('grant')
            ->loadCount(['employeeFundingAllocations as active_allocations_count' => function ($query) {
                $query->where('status', FundingAllocationStatus::Active);
            }]);
    }

    /**
     * Create a new grant item and send notification.
     */
    public function create(array $data): GrantItem
    {
        $data['created_by'] = Auth::user()?->name ?? 'system';
        $data['updated_by'] = Auth::user()?->name ?? 'system';

        $grantItem = GrantItem::create($data);

        $grantItem->load('grant')
            ->loadCount(['employeeFundingAllocations as active_allocations_count' => function ($query) {
                $query->where('status', FundingAllocationStatus::Active);
            }]);

        $this->sendNotification($grantItem, 'created');

        return $grantItem;
    }

    /**
     * Update an existing grant item and send notification.
     */
    public function update(GrantItem $grantItem, array $data): GrantItem
    {
        $data['updated_by'] = Auth::user()?->name ?? 'system';

        $grantItem->update($data);

        $grantItem->load('grant')
            ->loadCount(['employeeFundingAllocations as active_allocations_count' => function ($query) {
                $query->where('status', FundingAllocationStatus::Active);
            }]);

        $this->sendNotification($grantItem, 'updated');

        return $grantItem;
    }

    /**
     * Delete a grant item and send notification.
     */
    public function delete(GrantItem $grantItem): void
    {
        $grant = $grantItem->grant;

        // Store data for notification before deletion
        $grantItemForNotification = (object) [
            'id' => $grantItem->id,
            'grant_position' => $grantItem->grant_position ?? 'Unknown Position',
        ];

        $grantForNotification = $grant ? (object) [
            'id' => $grant->id,
            'name' => $grant->name ?? 'Unknown Grant',
            'code' => $grant->code ?? 'N/A',
        ] : null;

        $grantItem->delete();

        $performedBy = Auth::user();
        if ($performedBy && $grantForNotification) {
            $this->notificationService->notifyByModule(
                'grants_list',
                new GrantItemActionNotification('deleted', $grantItemForNotification, $grantForNotification, $performedBy, 'grants_list'),
                'deleted'
            );
        }
    }

    /**
     * Send grant item action notification.
     */
    private function sendNotification(GrantItem $grantItem, string $action): void
    {
        $performedBy = Auth::user();

        if ($performedBy && $grantItem->grant) {
            $this->notificationService->notifyByModule(
                'grants_list',
                new GrantItemActionNotification($action, $grantItem, $grantItem->grant, $performedBy, 'grants_list'),
                $action
            );
        }
    }
}
