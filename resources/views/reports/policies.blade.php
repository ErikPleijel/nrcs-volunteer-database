<x-layouts.admin title="Policies & Rules">
    <x-slot name="pageHeader">
        <i class="fas fa-scale-balanced mr-3"></i>Policies &amp; Rules
    </x-slot>
    <x-slot name="subHeader">
        How approvals, lifecycle status, and archiving work in this system.
    </x-slot>

    <div class="container mx-auto px-4 py-6 max-w-3xl">
        <div x-data="{ open: null }" class="space-y-3 text-sm">

            {{-- How Approval Works (Four-Eyes Rule) --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <button type="button"
                        @click="open = open === 'four-eyes' ? null : 'four-eyes'"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-800">
                    <span><i class="fas fa-user-check mr-2 text-blue-500"></i>How Approval Works (Four-Eyes Rule)</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                       :class="open === 'four-eyes' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === 'four-eyes'" x-collapse class="px-4 py-3 bg-white">
                    <ul class="space-y-2 text-sm text-gray-700 list-disc pl-4">
                        <li>Every donation, membership payment, training record, and volunteering activity starts as "pending" when submitted.</li>
                        <li>The person who submits a record can never approve it themselves, even if they hold an approval permission. This is the four-eyes rule: a second, different person must review and approve every record.</li>
                        <li>Only users with the relevant approval permission (approve_donations, approve_payments, approve_training, approve_volunteering) can approve records, and only for their own branch or division — national-level approvers can approve records from anywhere.</li>
                        <li>Editing an approved record automatically sends it back to "pending" and clears the previous approval — whoever edited it (even the original approver) must have the change reviewed and approved again by someone else. This applies to every edit, regardless of how small.</li>
                        <li>Bulk-approving a batch of records automatically skips any record submitted by the person doing the bulk approval, and skips records belonging to archived members — these always require individual review.</li>
                    </ul>
                </div>
            </div>

            {{-- Approving a Record for an Archived or Dormant Member --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <button type="button"
                        @click="open = open === 'reactivation-on-approve' ? null : 'reactivation-on-approve'"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-800">
                    <span><i class="fas fa-user-shield mr-2 text-orange-500"></i>Approving a Record for an Archived or Dormant Member</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                       :class="open === 'reactivation-on-approve' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === 'reactivation-on-approve'" x-collapse class="px-4 py-3 bg-white">
                    <ul class="space-y-2 text-sm text-gray-700 list-disc pl-4">
                        <li>If you approve a record belonging to a dormant member, they are automatically reactivated to "active" as part of the approval.</li>
                        <li>If you approve a record belonging to an archived member, you'll be asked to confirm the reactivation first — this never happens silently or as part of a bulk approval, only on the individual review screen.</li>
                    </ul>
                </div>
            </div>

            {{-- The Four Lifecycle Stages --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <button type="button"
                        @click="open = open === 'stages' ? null : 'stages'"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-800">
                    <span><i class="fas fa-heartbeat mr-2 text-red-500"></i>The Four Lifecycle Stages</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                       :class="open === 'stages' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === 'stages'" x-collapse class="px-4 py-3 bg-white">
                    <ul class="space-y-2 text-sm text-gray-700 list-disc pl-4">
                        <li><span class="font-semibold">Pending Engagement</span> — a newly registered person who hasn't yet had any record approved and hasn't been assigned to a Red Cross unit.</li>
                        <li><span class="font-semibold">Active</span> — an engaged member or volunteer currently meeting the engagement policy for their category (see next section).</li>
                        <li><span class="font-semibold">Dormant</span> — someone who no longer meets the engagement policy for their category — this is not a manual label, it's calculated automatically.</li>
                        <li><span class="font-semibold">Archived</span> — someone manually removed from active tracking by an admin. This is never automatic — no system process archives anyone on its own.</li>
                    </ul>
                </div>
            </div>

            {{-- Moving from Pending Engagement to Active --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <button type="button"
                        @click="open = open === 'pending-to-active' ? null : 'pending-to-active'"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-800">
                    <span><i class="fas fa-arrow-up-right-dots mr-2 text-green-500"></i>Moving from Pending Engagement to Active</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                       :class="open === 'pending-to-active' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === 'pending-to-active'" x-collapse class="px-4 py-3 bg-white">
                    <ul class="space-y-2 text-sm text-gray-700 list-disc pl-4">
                        <li>A pending engagement person is automatically promoted to "active" the first time an approved training, volunteering activity, or membership payment is recorded for them.</li>
                        <li>A donation alone does not promote someone out of pending engagement — a one-time donor isn't treated the same as an engaged volunteer or member. If they also have a training, activity, or membership payment approved, that will promote them normally.</li>
                        <li>Being assigned to a Red Cross unit also promotes someone to active directly, independent of any approved record.</li>
                        <li>In rare cases, a person can be promoted to active and then immediately re-evaluated as dormant in the same action — for example, if the only approved record is old enough to already fall outside the dormancy window. This is expected: the system always settles on the policy-correct status, even if that means passing through "active" only briefly.</li>
                    </ul>
                </div>
            </div>

            {{-- How Dormancy Is Decided --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <button type="button"
                        @click="open = open === 'dormancy-rules' ? null : 'dormancy-rules'"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-800">
                    <span><i class="fas fa-clock-rotate-left mr-2 text-amber-500"></i>How Dormancy Is Decided</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                       :class="open === 'dormancy-rules' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === 'dormancy-rules'" x-collapse class="px-4 py-3 bg-white">
                    <ul class="space-y-2 text-sm text-gray-700 list-disc pl-4">
                        <li>The rule depends on what kind of relationship the person has with the organisation:</li>
                        <li><span class="font-semibold">Members</span> (no Red Cross unit, has a current personal membership payment) — dormancy is based purely on whether their membership payment has expired. Activity history doesn't matter for this group — only membership validity.</li>
                        <li><span class="font-semibold">Volunteers</span> (assigned to a Red Cross unit), and everyone else without a current membership (no unit, no payment) — dormancy is based on inactivity — no approved donation, training, activity, or payment within the configured window (12 months by default).</li>
                        <li>"Unassigned" people (previously had a unit, no longer do, and don't have a genuine personal membership) are treated the same as volunteers/other for dormancy purposes — inactivity-based.</li>
                        <li>A daily automated check re-evaluates every active and dormant person against these rules and updates their status accordingly. People belonging to an organisation are excluded from this automatic check.</li>
                    </ul>
                </div>
            </div>

            {{-- Reactivating a Dormant or Archived Person --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <button type="button"
                        @click="open = open === 'reactivating' ? null : 'reactivating'"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-800">
                    <span><i class="fas fa-rotate-right mr-2 text-teal-500"></i>Reactivating a Dormant or Archived Person</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                       :class="open === 'reactivating' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === 'reactivating'" x-collapse class="px-4 py-3 bg-white">
                    <ul class="space-y-2 text-sm text-gray-700 list-disc pl-4">
                        <li><span class="font-semibold">Dormant → Active</span> happens automatically the moment any of their records is approved, or via the daily automated check once their situation genuinely changes (e.g. a membership payment that was pending is approved and is still valid).</li>
                        <li><span class="font-semibold">Archived → Active</span> only happens manually — either by unchecking "Archived" on their profile, or by an approver explicitly confirming reactivation while approving one of their pending records.</li>
                    </ul>
                </div>
            </div>

            {{-- Archiving --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <button type="button"
                        @click="open = open === 'archiving' ? null : 'archiving'"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-800">
                    <span><i class="fas fa-box-archive mr-2 text-gray-500"></i>Archiving</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                       :class="open === 'archiving' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === 'archiving'" x-collapse class="px-4 py-3 bg-white">
                    <ul class="space-y-2 text-sm text-gray-700 list-disc pl-4">
                        <li>Archiving is always a deliberate admin action — there is no automatic process that archives anyone.</li>
                        <li>It can be done for one person at a time from their profile (admins cannot archive themselves), or in bulk via the dormant-users cleanup tool, which only targets people who are already dormant or have been stuck in pending engagement for a long time — and automatically excludes super-admins, other admin-role holders, anyone linked to an organisation, and anyone with a currently valid membership.</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</x-layouts.admin>
