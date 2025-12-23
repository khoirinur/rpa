<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LiveChickenPurchaseOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class LiveChickenPurchaseOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LiveChickenPurchaseOrder');
    }

    public function view(AuthUser $authUser, LiveChickenPurchaseOrder $liveChickenPurchaseOrder): bool
    {
        return $authUser->can('View:LiveChickenPurchaseOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LiveChickenPurchaseOrder');
    }

    public function update(AuthUser $authUser, LiveChickenPurchaseOrder $liveChickenPurchaseOrder): bool
    {
        return $authUser->can('Update:LiveChickenPurchaseOrder');
    }

    public function delete(AuthUser $authUser, LiveChickenPurchaseOrder $liveChickenPurchaseOrder): bool
    {
        return $authUser->can('Delete:LiveChickenPurchaseOrder');
    }

    public function restore(AuthUser $authUser, LiveChickenPurchaseOrder $liveChickenPurchaseOrder): bool
    {
        return $authUser->can('Restore:LiveChickenPurchaseOrder');
    }

    public function forceDelete(AuthUser $authUser, LiveChickenPurchaseOrder $liveChickenPurchaseOrder): bool
    {
        return $authUser->can('ForceDelete:LiveChickenPurchaseOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LiveChickenPurchaseOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LiveChickenPurchaseOrder');
    }

    public function replicate(AuthUser $authUser, LiveChickenPurchaseOrder $liveChickenPurchaseOrder): bool
    {
        return $authUser->can('Replicate:LiveChickenPurchaseOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LiveChickenPurchaseOrder');
    }

}