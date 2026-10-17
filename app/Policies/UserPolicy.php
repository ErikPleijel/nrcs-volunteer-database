<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        $actorAccessLevel = $actor->getAccessLevel();
        $actorScopedId = $actor->getScopedId();

        return match ($actorAccessLevel) {
            'national' => true,
            'branch'   => (int) $target->branch_id === (int) $actorScopedId,
            'division' => (int) $target->division_id === (int) $actorScopedId,
            default    => (int) $target->id === (int) $actor->id,
        };
    }

    public function authorizeUser(User $actor, User $target): bool
    {
        return $this->view($actor, $target);
    }

    /**
     * Narrow, task-force-scoped exception to view(): allows viewing a target
     * user (specifically their photo, via PhotoController) when the actor
     * shares at least one task force with them, regardless of branch/division.
     *
     * Intentionally does NOT replace view() — task forces are a deliberately
     * cross-branch, peer-visible grouping (see task-forces Guide copy), but
     * this must stay scoped to callers that opt in explicitly (task-forces
     * show/my-task-force pages), not become the default profile/photo check.
     */
    public function viewAsTaskForceMate(User $actor, User $target): bool
    {
        return $actor->taskForces()
            ->whereIn('task_forces.id', $target->taskForces()->pluck('task_forces.id'))
            ->exists();
    }

    /**
     * Opt-in exception (like viewAsTaskForceMate): lets a volunteer view the
     * profile photo of a fellow member of their own Red Cross Unit, even
     * without an admin-tier role. Not a replacement for view().
     */
    public function viewAsUnitMate(User $actor, User $target): bool
    {
        return $actor->red_cross_unit_id !== null
            && $actor->red_cross_unit_id === $target->red_cross_unit_id;
    }

    /**
     * Opt-in exception (like viewAsTaskForceMate/viewAsUnitMate): lets any
     * authenticated user view the profile photo of a person currently
     * designated as one of their own branch's public contacts. One-directional
     * only — being a viewer here grants the viewer no reciprocal visibility,
     * and being a contact grants no visibility of the viewer. Not a
     * replacement for view().
     */
    public function viewAsBranchContact(User $actor, User $target): bool
    {
        if (! $actor->branch_id) {
            return false;
        }

        $branch = $actor->branch;

        if (! $branch) {
            return false;
        }

        foreach ($branch->publicContacts() as $contact) {
            if ($contact['user']->id === $target->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Upload someone's photo or signature (users/{user}/images). Allowed for
     * anyone who may edit the person (edit_user + update()), and for division DB
     * assistants holding the "Can upload photos and signatures" tick box
     * (upload_user_images): own division only, and never a person with an admin
     * role — the same limits update() puts on non-administrators.
     */
    public function uploadImages(User $actor, User $target): bool
    {
        if ($actor->can('edit_user')) {
            try {
                return $this->update($actor, $target);
            } catch (AuthorizationException) {
                return false;
            }
        }

        return $actor->can('upload_user_images')
            && $actor->getAccessLevel() === 'division'
            && $actor->getScopedId() !== null
            && (int) $target->division_id === (int) $actor->getScopedId()
            && ! $target->roles()->exists();
    }

    public function update(User $actor, User $target): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return true;
        }

        if ($actor->hasRole('national_db_administrator')) {
            return true;
        }

        if ($actor->hasAnyRole(['branch_db_administrator', 'branch_secretary'])) {
            if ($target->getAccessLevel() === 'national') {
                throw new AuthorizationException(
                    'Branch administrators cannot edit national-level administrator accounts.'
                );
            }

            $actorScopedId = $actor->getScopedId();

            return $actorScopedId !== null
                && (int) $target->branch_id === (int) $actorScopedId;
        }

        if (!$this->authorizeUser($actor, $target)) {
            throw new AuthorizationException('You can only edit users within your assigned scope.');
        }

        if ($target->roles()->exists()) {
            throw new AuthorizationException('You are not allowed to edit users who have administrative roles.');
        }

        return true;
    }
}
