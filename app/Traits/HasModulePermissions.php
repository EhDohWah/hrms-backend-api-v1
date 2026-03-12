<?php

namespace App\Traits;

use App\Models\Module;
use Illuminate\Http\JsonResponse;

/**
 * HasModulePermissions Trait
 *
 * Provides reusable methods for controllers to check module permissions.
 *
 * Usage in controllers:
 * ```php
 * use App\Traits\HasModulePermissions;
 *
 * class EmployeeController extends Controller
 * {
 *     use HasModulePermissions;
 *
 *     protected string $moduleName = 'employee';
 *
 *     public function store(Request $request)
 *     {
 *         if (!$this->userCanCreateModule()) {
 *             return $this->unauthorizedResponse('create');
 *         }
 *         // ... rest of store logic
 *     }
 * }
 * ```
 */
trait HasModulePermissions
{
    /**
     * The module name for this controller.
     * Must be set in the controller that uses this trait.
     */
    protected string $moduleName;

    /**
     * Check if current user can read the module.
     */
    protected function userCanReadModule(): bool
    {
        return auth()->user()?->canReadModule($this->getModuleName()) ?? false;
    }

    /**
     * Check if current user can create in the module.
     */
    protected function userCanCreateModule(): bool
    {
        return auth()->user()?->canCreateModule($this->getModuleName()) ?? false;
    }

    /**
     * Check if current user can update in the module.
     */
    protected function userCanUpdateModule(): bool
    {
        return auth()->user()?->canUpdateModule($this->getModuleName()) ?? false;
    }

    /**
     * Check if current user can delete in the module.
     */
    protected function userCanDeleteModule(): bool
    {
        return auth()->user()?->canDeleteModule($this->getModuleName()) ?? false;
    }

    /**
     * Check if current user has any access to the module.
     */
    protected function userHasModuleAccess(): bool
    {
        return auth()->user()?->hasModuleAccess($this->getModuleName()) ?? false;
    }

    /**
     * Check if current user has read-only access.
     */
    protected function userHasReadOnlyAccess(): bool
    {
        return auth()->user()?->hasReadOnlyAccess($this->getModuleName()) ?? false;
    }

    /**
     * Check if current user has full access.
     */
    protected function userHasFullAccess(): bool
    {
        return auth()->user()?->hasFullAccess($this->getModuleName()) ?? false;
    }

    /**
     * Get module access information for current user.
     *
     * @return array{read: bool, create: bool, update: bool, delete: bool}
     */
    protected function getUserModuleAccess(): array
    {
        return auth()->user()?->getModuleAccess($this->getModuleName()) ?? [
            'read' => false, 'create' => false, 'update' => false, 'delete' => false,
        ];
    }

    /**
     * Get the module instance.
     */
    protected function getModule(): ?Module
    {
        return Module::where('name', $this->getModuleName())->where('is_active', true)->first();
    }

    /**
     * Get the module name.
     *
     * @throws \RuntimeException if moduleName is not set
     */
    protected function getModuleName(): string
    {
        if (! isset($this->moduleName)) {
            throw new \RuntimeException(
                'Property $moduleName must be set in '.static::class.' to use HasModulePermissions trait'
            );
        }

        return $this->moduleName;
    }

    /**
     * Return unauthorized response for specific action.
     *
     * @param  string  $action  The action being attempted (view, create, update, delete)
     */
    protected function unauthorizedResponse(string $action = 'perform this action'): JsonResponse
    {
        $module = $this->getModule();
        $displayName = $module?->display_name ?? $this->getModuleName();

        $messages = [
            'view' => "You do not have permission to view {$displayName} records",
            'create' => "You do not have permission to create {$displayName} records",
            'update' => "You do not have permission to update {$displayName} records",
            'delete' => "You do not have permission to delete {$displayName} records",
            'import' => "You do not have permission to import {$displayName} records",
            'export' => "You do not have permission to export {$displayName} records",
        ];

        $message = $messages[$action] ?? "You do not have permission to {$action} on {$displayName}";

        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * Abort with 403 if user cannot read module.
     */
    protected function authorizeRead(): void
    {
        if (! $this->userCanReadModule()) {
            abort(403, $this->unauthorizedResponse('view')->getData()->message);
        }
    }

    /**
     * Abort with 403 if user cannot create in module.
     */
    protected function authorizeCreate(): void
    {
        if (! $this->userCanCreateModule()) {
            abort(403, $this->unauthorizedResponse('create')->getData()->message);
        }
    }

    /**
     * Abort with 403 if user cannot update in module.
     */
    protected function authorizeUpdate(): void
    {
        if (! $this->userCanUpdateModule()) {
            abort(403, $this->unauthorizedResponse('update')->getData()->message);
        }
    }

    /**
     * Abort with 403 if user cannot delete in module.
     */
    protected function authorizeDelete(): void
    {
        if (! $this->userCanDeleteModule()) {
            abort(403, $this->unauthorizedResponse('delete')->getData()->message);
        }
    }
}
