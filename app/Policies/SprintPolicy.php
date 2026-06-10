<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Sprint;
use Illuminate\Auth\Access\HandlesAuthorization;

class SprintPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_sprint');
    }

    public function view(AuthUser $authUser, Sprint $sprint): bool
    {
        return $authUser->can('view_sprint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_sprint');
    }

    public function update(AuthUser $authUser, Sprint $sprint): bool
    {
        return $authUser->can('update_sprint');
    }

    public function delete(AuthUser $authUser, Sprint $sprint): bool
    {
        return $authUser->can('delete_sprint');
    }

    public function restore(AuthUser $authUser, Sprint $sprint): bool
    {
        return $authUser->can('restore_sprint');
    }

    public function forceDelete(AuthUser $authUser, Sprint $sprint): bool
    {
        return $authUser->can('force_delete_sprint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_sprint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_sprint');
    }

    public function replicate(AuthUser $authUser, Sprint $sprint): bool
    {
        return $authUser->can('replicate_sprint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_sprint');
    }

}