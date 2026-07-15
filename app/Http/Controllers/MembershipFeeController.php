<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use App\Models\MembershipFee;
use Illuminate\Http\Request;

class MembershipFeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $membershipFees = MembershipFee::orderByDesc('is_active')
                                        ->orderBy('for_organizations')
                                        ->orderBy('validity_years')
                                        ->orderBy('amount')
                                        ->paginate(200);
        return view('membership-fees.index', compact('membershipFees'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Pass a new empty MembershipFee instance to the view for form consistency
        $membershipFee = new MembershipFee();
        return view('membership-fees.create', compact('membershipFee'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'id_card_fee' => 'nullable|numeric|min:0',
            'validity_years' => 'required|integer|min:1',
            'for_organizations' => 'boolean',
        ]);

        // Set is_active to true by default for new records, as the checkbox is removed from the form
        $validated['is_active'] = true;

        $membershipFee = MembershipFee::create($validated);

        AuditLog::write(
            'membership_fee_created',
            $membershipFee,
            null,
            null,
            $membershipFee->toArray(),
            "Membership Fee \"{$membershipFee->name}\" created."
        );

        return redirect()->route('membership-fees.index')->with('success', 'Membership Fee created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MembershipFee $membershipFee)
    {
        return view('membership-fees.show', compact('membershipFee'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MembershipFee $membershipFee)
    {
        return view('membership-fees.edit', compact('membershipFee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MembershipFee $membershipFee)
    {
        // Validate all incoming data
        $validated = $request->validate([
            // These fields are displayed but disabled, their values are sent via hidden inputs.
            // We re-validate them here to ensure data integrity, but changes to them should not trigger a new record.
            'name' => 'required|string|max:255',
            'validity_years' => 'required|integer|min:1',
            'for_organizations' => 'boolean',

            // These fields can trigger a new record if changed
            'amount' => 'required|numeric|min:0',
            'id_card_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',

            // These fields are updated in place
            'is_volunteer_fee' => 'boolean',
        ]);

        // Determine if critical fields (amount, id_card_fee, is_active) have changed
        // Use a small epsilon for float comparison to account for precision issues
        $epsilon = 0.00001;
        $amountChanged = abs($membershipFee->amount - $validated['amount']) > $epsilon;
        $idCardFeeChanged = abs(($membershipFee->id_card_fee ?? 0) - ($validated['id_card_fee'] ?? 0)) > $epsilon;
        $isActiveChanged = $membershipFee->is_active != $validated['is_active'];

        // Determine if in-place fields have changed
        $isVolunteerFeeChanged = (bool)($validated['is_volunteer_fee'] ?? false) !== (bool)$membershipFee->is_volunteer_fee;

        // If amount, ID card fee, or active status has changed, create a new record and deactivate the old one
        if ($amountChanged || $idCardFeeChanged || $isActiveChanged) {
            // Snapshot BEFORE any mutation — this is the true pre-change state of the old record.
            $oldAttributes = $membershipFee->toArray();
            $oldAttributes['status_after_change'] = 'deactivated (not deleted)';

            // Deactivate the current membership fee record
            $membershipFee->update(['is_active' => false]);

            // Create a new membership fee record with updated values
            $newMembershipFee = MembershipFee::create([
                'name' => $membershipFee->name,
                'amount' => $validated['amount'],
                'id_card_fee' => $validated['id_card_fee'] ?? 0,
                'validity_years' => $membershipFee->validity_years,
                'for_organizations' => $membershipFee->for_organizations,
                'is_active' => $validated['is_active'],
                'is_volunteer_fee' => (bool)($validated['is_volunteer_fee'] ?? false),
            ]);

            $fieldChanges = [];

            if ($amountChanged) {
                $fieldChanges[] = sprintf('amount: %s → %s', $membershipFee->amount, $newMembershipFee->amount);
            }
            if ($idCardFeeChanged) {
                $fieldChanges[] = sprintf('ID card fee: %s → %s', $membershipFee->id_card_fee, $newMembershipFee->id_card_fee);
            }
            if ($isActiveChanged) {
                $fieldChanges[] = sprintf(
                    'status: %s → %s',
                    $membershipFee->is_active ? 'Active' : 'Inactive',
                    $newMembershipFee->is_active ? 'Active' : 'Inactive'
                );
            }

            $changesSummary = empty($fieldChanges)
                ? 'no amount/fee/status change detected'
                : implode(', ', $fieldChanges);

            AuditLog::write(
                'membership_fee_superseded',
                $membershipFee,
                null,
                $oldAttributes,
                $newMembershipFee->toArray(),
                "Membership Fee \"{$membershipFee->name}\": fee #{$membershipFee->id} was deactivated (not deleted) and superseded by new fee #{$newMembershipFee->id} — this was not an in-place update. Changes: {$changesSummary}."
            );

            return redirect()->route('membership-fees.index')->with('success', "Membership Fee '{$membershipFee->name}' updated. The old record has been deactivated, and a new record created with the adjusted details.");

        } elseif ($isVolunteerFeeChanged) {
            $oldValues = ['is_volunteer_fee' => (bool) $membershipFee->is_volunteer_fee];
            $newValues = ['is_volunteer_fee' => (bool)($validated['is_volunteer_fee'] ?? false)];

            // If only in-place fields changed, update the existing record
            $membershipFee->update([
                'is_volunteer_fee' => (bool)($validated['is_volunteer_fee'] ?? false),
            ]);

            AuditLog::write(
                'membership_fee_updated',
                $membershipFee,
                null,
                $oldValues,
                $newValues,
                sprintf(
                    'Membership Fee "%s" updated (fields changed: %s).',
                    $membershipFee->name,
                    implode(', ', array_keys($newValues))
                )
            );

            return redirect()->route('membership-fees.index')->with('success', "Membership Fee '{$membershipFee->name}' updated successfully.");
        }

        // If no relevant changes were made
        return redirect()->route('membership-fees.index')->with('info', "No changes were made to Membership Fee '{$membershipFee->name}'.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MembershipFee $membershipFee)
    {
        $attributes = $membershipFee->toArray();

        $membershipFee->delete();

        AuditLog::write(
            'membership_fee_deleted',
            $membershipFee,
            null,
            $attributes,
            null,
            "Membership Fee \"{$attributes['name']}\" deleted."
        );

        return redirect()->route('membership-fees.index')->with('success', 'Membership Fee deleted successfully.');
    }
}
