<x-lifecycle.layout
    title="Archived"
>
    {{-- Summary row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-6 lg:col-span-2">
            <h2 class="howto-h2">What is "Archived"?</h2>

            <p class="mt-2 text-base text-gray-700">
                People in <span class="font-semibold">Archived</span> are profiles that are no longer considered active in operations.
                They are kept for record-keeping, audit, and historical reporting.
                An archived profile can be reactivated.
            </p>

            <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 p-4">
                <div class="text-xl font-semibold text-amber-900">Objective</div>
                <div class="mt-1 text-base text-amber-900">
                    Maintain a clean database. Archived profiles should not be targeted by normal campaigns.
                </div>
            </div>

            {{-- How-to section --}}
            <div class="rounded-lg border border-gray-200 bg-white p-6">

                <h4 class="howto-h4 mt-4">Deactivation</h4>
                <p class="text-base text-gray-600">
                    To deactivate a person: click <span class="font-semibold">Edit</span>, scroll to the bottom of the page, tick <span class="font-semibold">"Archive this user"</span>, and click <span class="font-semibold">Update person</span>. The person can be reactivated later if needed.
                </p>

                <h4 class="howto-h4 mt-7">Reactivation</h4>
                <p class="text-base text-gray-600">
                    To reactivate a person: click <span class="font-semibold">Edit</span>, scroll to the bottom of the page, untick <span class="font-semibold">"Archive this user"</span>, and click <span class="font-semibold">Update person</span>.
                </p>

                <h4 class="howto-h4 mt-7">Bulk Archiving</h4>
                <p class="text-base text-gray-600">
                    HQ administrators and branch secretaries have access to the
                    @can('use_archive_tool')
                        <a href="{{ route('dormant-users.index') }}" class="font-semibold text-indigo-600 hover:text-indigo-800 underline">Dormant Users</a>
                    @else
                        <span class="font-semibold">Dormant Users</span>
                    @endcan
                    tool, which lists users who have been inactive for an extended period.
                    This tool allows bulk archiving of multiple users at once.
                    Only users without active memberships, without admin roles, and not linked to any organisation are shown.
                    Archived users can always be reactivated individually.
                </p>

                <div class="mt-4 rounded-md bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                    If you believe a user was archived by mistake, locate them using the
                    <span class="font-semibold">Archived</span> filter on the Users index page and reactivate them from their Edit page.
                </div>

            </div>
        </div>
    </div>

</x-lifecycle.layout>
