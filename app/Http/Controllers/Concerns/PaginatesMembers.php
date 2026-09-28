<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Shared pagination for member lists on unit / task-force pages. Some units
 * hold thousands of members (NYSC Unit: 5,000+), so a member list is never
 * loaded whole.
 *
 * The page parameter is 'members_page' so it doesn't collide with the
 * recentActivities paginator (plain 'page') that shares these pages, and the
 * links carry the '#unit-members' fragment so paging lands back on the list.
 */
trait PaginatesMembers
{
    protected const MEMBERS_PER_PAGE = 24; // 8 full rows of the 3-column card grid

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     */
    protected function paginateMembers($query): LengthAwarePaginator
    {
        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('users.id') // tie-break: stable pages when names repeat
            ->paginate(self::MEMBERS_PER_PAGE, ['users.*'], 'members_page')
            ->withQueryString()
            ->fragment('unit-members');
    }
}
