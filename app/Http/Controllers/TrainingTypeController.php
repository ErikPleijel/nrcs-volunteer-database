<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use App\Models\TrainingType;
use App\Models\TrainingGroup; // Import the TrainingGroup model
use App\Models\User;
use Illuminate\Http\Request;

class TrainingTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $trainingTypes = TrainingType::with('group')
            ->orderByGroupThenName()
            ->get();

        return view('training-types.index', compact('trainingTypes'));
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Pass training groups to the create view as well, if you have one
        $trainingGroups = TrainingGroup::orderBy('group_name')->get();
        return view('training-types.create', compact('trainingGroups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:training_types',
            'validity_years_limit' => 'nullable|integer|min:1|max:99',
            'is_active' => 'boolean',
            'certificate_hq_only' => 'boolean',
            'is_first_aid' => 'boolean',
            'group_id' => 'nullable|exists:training_groups,id',
        ]);

        $validatedData['is_active'] = $request->has('is_active');
        $validatedData['certificate_hq_only'] = $request->has('certificate_hq_only');
        $validatedData['is_first_aid'] = $request->has('is_first_aid');

        $trainingType = TrainingType::create($validatedData);

        AuditLog::write(
            'training_type_created',
            $trainingType,
            null,
            null,
            $trainingType->toArray(),
            "Training type \"{$trainingType->name}\" created."
        );

        return redirect()->route('training-types.index')
            ->with('success', 'Training type created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(TrainingType $trainingType)
    {
        $trainingType->load('trainings');
        return view('training-types.show', compact('trainingType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TrainingType $trainingType)
    {
        // Eager load the group relationship for the current training type
        $trainingType->load('group');
        // Fetch all training groups to populate the dropdown
        $trainingGroups = TrainingGroup::orderBy('group_name')->get();

        return view('training-types.edit', compact('trainingType', 'trainingGroups'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TrainingType $trainingType)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:training_types,name,' . $trainingType->id,
            'validity_years_limit' => 'nullable|integer|min:1|max:99',
            'is_active' => 'boolean',
            'certificate_hq_only' => 'boolean',
            'is_first_aid' => 'boolean',
            'group_id' => 'nullable|exists:training_groups,id',
        ]);

        $validatedData['is_active'] = $request->has('is_active');
        $validatedData['certificate_hq_only'] = $request->has('certificate_hq_only');
        $validatedData['is_first_aid'] = $request->has('is_first_aid');

        // Capture the FA flag before the update so we can detect a change.
        $oldIsFirstAid = (bool) $trainingType->is_first_aid;
        $originalAttributes = $trainingType->getOriginal();

        $trainingType->update($validatedData);

        // If the first-aid flag flipped, the denormalised last_first_aid_at of every user who has
        // a non-deleted training of THIS type may now be wrong. Recompute just those users
        // (scoped via whereHas on the type + is_deleted = false), not the whole user table.
        if ($oldIsFirstAid !== (bool) $trainingType->is_first_aid) {
            User::whereHas('trainings', function ($q) use ($trainingType) {
                $q->where('training_type_id', $trainingType->id)
                    ->where('is_deleted', false);
            })->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $user->recalculateLastFirstAidAt();
                }
            });
        }

        $changes = $trainingType->getChanges();
        unset($changes['updated_at']);

        if (!empty($changes)) {
            $oldValues = array_intersect_key($originalAttributes, $changes);

            $description = sprintf(
                'Training type "%s" updated (fields changed: %s).',
                $trainingType->name,
                implode(', ', array_keys($changes))
            );

            if (array_key_exists('is_first_aid', $changes)) {
                $description .= ' First-aid flag changed — triggered a recalculation of last_first_aid_at for all users with a non-deleted training of this type.';
            }

            AuditLog::write(
                'training_type_updated',
                $trainingType,
                null,
                $oldValues,
                $changes,
                $description
            );
        }

        return redirect()->route('training-types.index')
            ->with('success', 'Training type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TrainingType $trainingType)
    {
        // Check if training type has any associated trainings
        if ($trainingType->trainings()->count() > 0) {
            return redirect()->route('training-types.index')
                ->with('error', 'Cannot delete training type that has associated trainings.');
        }

        $attributes = $trainingType->toArray();

        $trainingType->delete();

        AuditLog::write(
            'training_type_deleted',
            $trainingType,
            null,
            $attributes,
            null,
            "Training type \"{$attributes['name']}\" deleted."
        );

        return redirect()->route('training-types.index')
            ->with('success', 'Training type deleted successfully.');
    }
}
