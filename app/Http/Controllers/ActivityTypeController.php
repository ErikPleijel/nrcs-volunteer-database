<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use Illuminate\Http\Request;

class ActivityTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activityTypes = ActivityType::orderByName()->paginate(500);
        return view('activity-types.index', compact('activityTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('activity-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:activity_types,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        ActivityType::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', false), // Changed default from true to false
        ]);

        return redirect()->route('activity-types.index')
            ->with('success', 'Activity Type created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ActivityType $activityType)
    {
        $activityType->load(['activities' => function($query) {
            $query->where('is_deleted', false);
        }]);

        return view('activity-types.show', compact('activityType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ActivityType $activityType)
    {
        return view('activity-types.edit', compact('activityType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ActivityType $activityType)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:activity_types,name,' . $activityType->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $activityType->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', false), // Changed default from true to false
        ]);

        return redirect()->route('activity-types.index')
            ->with('success', 'Activity Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityType $activityType)
    {
        // Check if there are any associated activities
        if ($activityType->activities()->count() > 0) {
            return redirect()->route('activity-types.index')
                ->with('error', 'Cannot delete Activity Type with associated activities.');
        }

        $activityType->delete();

        return redirect()->route('activity-types.index')
            ->with('success', 'Activity Type deleted successfully.');
    }
}
