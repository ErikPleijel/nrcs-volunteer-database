{{--
    Empty-state text for a paginated member list (see PaginatesMembers).
    Renders only the text; the caller supplies the wrapping div/td.

    - Page past the end (stale bookmark, edited URL) while the list does have
      members: say so and link back to page 1 (other query params kept).
    - Page holds rows but they were all filtered out (a later page with only
      the leaders on it): no other members on this page.
    - Otherwise the list is genuinely empty: $emptyText.
--}}
@props([
    'members',
    'emptyText' => 'No members in this unit (excluding leaders).',
])

@if($members->isEmpty() && $members->total() > 0)
    No members on this page.
    <a href="{{ $members->url(1) }}" class="text-blue-600 underline hover:text-blue-800">Go back to the first page</a>
@elseif($members->isNotEmpty() && $members->lastPage() > 1)
    No other members on this page (excluding leaders).
@else
    {{ $emptyText }}
@endif
