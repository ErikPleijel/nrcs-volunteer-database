<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use App\Models\TaskForceType;
use Illuminate\Http\Request;

class TaskForceTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $taskForceTypes = TaskForceType::orderByLevel()->orderByName()->get();
        return view('task-force-types.index', compact('taskForceTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('task-force-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:task_force_types',
            'level' => 'required|integer|min:1|max:10',
            'include_in_list' => 'boolean',
        ]);

        // Set default value for checkbox
        $validatedData['include_in_list'] = $request->has('include_in_list');

        $taskForceType = TaskForceType::create($validatedData);

        AuditLog::write(
            'task_force_type_created',
            $taskForceType,
            null,
            null,
            $taskForceType->toArray(),
            "Task Force Type \"{$taskForceType->name}\" created."
        );

        return redirect()->route('task-force-types.index')
            ->with('success', 'Task Force Type created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(TaskForceType $taskForceType)
    {
        $taskForceType->load(['taskForces' => function ($query) {
            $query->orderBy('name');
        }]);
        return view('task-force-types.show', compact('taskForceType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaskForceType $taskForceType)
    {
        return view('task-force-types.edit', compact('taskForceType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaskForceType $taskForceType)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:task_force_types,name,' . $taskForceType->id,
            'level' => 'required|integer|min:1|max:10',
            'include_in_list' => 'boolean',
        ]);

        // Set default value for checkbox
        $validatedData['include_in_list'] = $request->has('include_in_list');

        $originalAttributes = $taskForceType->getOriginal();

        $taskForceType->update($validatedData);

        $changes = $taskForceType->getChanges();
        unset($changes['updated_at']);

        if (!empty($changes)) {
            $oldValues = array_intersect_key($originalAttributes, $changes);

            AuditLog::write(
                'task_force_type_updated',
                $taskForceType,
                null,
                $oldValues,
                $changes,
                sprintf(
                    'Task Force Type "%s" updated (fields changed: %s).',
                    $taskForceType->name,
                    implode(', ', array_keys($changes))
                )
            );
        }

        return redirect()->route('task-force-types.index')
            ->with('success', 'Task Force Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaskForceType $taskForceType)
    {
        // Check if task force type has any associated task forces
        if ($taskForceType->taskForces()->count() > 0) {
            return redirect()->route('task-force-types.index')
                ->with('error', 'Cannot delete Task Force Type that has associated task forces.');
        }

        $attributes = $taskForceType->toArray();

        $taskForceType->delete();

        AuditLog::write(
            'task_force_type_deleted',
            $taskForceType,
            null,
            $attributes,
            null,
            "Task Force Type \"{$attributes['name']}\" deleted."
        );

        return redirect()->route('task-force-types.index')
            ->with('success', 'Task Force Type deleted successfully.');
    }
}
