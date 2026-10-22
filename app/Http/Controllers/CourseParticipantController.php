<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Log;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Add and remove the people on a course (participants and facilitators).
 * manage_course_participants is checked by route middleware; the course must
 * be visible to the viewer and still planned, and the person within reach
 * (Course::canHandlePerson()). Searching happens on the course page
 * (CourseController::show()).
 */
class CourseParticipantController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $viewer = Auth::user();
        $this->authorizeCourse($viewer, $course);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(CourseParticipant::ROLES)],
        ]);

        // Only people the search would offer: in reach, not archived, not on the course yet.
        $person = $course->addablePeopleFor($viewer)->find($validated['user_id']);

        if (! $person) {
            $alreadyOn = $course->courseParticipants()->where('user_id', $validated['user_id'])->exists();

            return back()->with('error', $alreadyOn
                ? 'This person is already on the course.'
                : 'This person cannot be added to the course.');
        }

        try {
            $course->courseParticipants()->create([
                'user_id' => $person->id,
                'role' => $validated['role'],
                'added_by_user_id' => $viewer->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'This person is already on the course.');
        }

        Log::write(
            'course_participant_added',
            $course,
            $person->only(['branch_id', 'division_id']),
            null,
            ['user_id' => $person->id, 'role' => $validated['role']],
            "{$person->full_name} added to course \"{$course->name}\" as {$validated['role']}"
        );
        $viewer->touchLastAdminActivity();

        return back()->with('success', "{$person->full_name} added as {$validated['role']}.");
    }

    public function destroy(Course $course, CourseParticipant $participant): RedirectResponse
    {
        $viewer = Auth::user();
        $this->authorizeCourse($viewer, $course);

        abort_unless($participant->course_id === $course->id, 404);

        $person = $participant->user;
        abort_unless($person && $course->canHandlePerson($viewer, $person), 403, 'You can only remove people from your own area.');

        $participant->delete();

        Log::write(
            'course_participant_removed',
            $course,
            $person->only(['branch_id', 'division_id']),
            ['user_id' => $person->id, 'role' => $participant->role],
            null,
            "{$person->full_name} removed from course \"{$course->name}\""
        );
        $viewer->touchLastAdminActivity();

        return back()->with('success', "{$person->full_name} removed from the course.");
    }

    /**
     * Save the results of the course's participants in one go: results[entry id]
     * = passed / failed / absent, or empty to clear. Only the organising side
     * (Course::canRecordResults), from the start date while planned. Entries
     * that did not change are left alone; one audit entry lists the changes.
     */
    public function updateResults(Request $request, Course $course): RedirectResponse
    {
        $viewer = Auth::user();
        $this->authorizeCourse($viewer, $course);
        abort_unless($course->canRecordResults($viewer), 403, 'Only the organisers of this course can record results.');

        if (! $course->resultsOpen()) {
            return back()->with('error', 'Results can be recorded from the start date of the course.');
        }

        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*' => ['nullable', Rule::in(CourseParticipant::RESULTS)],
        ]);

        $entries = $course->courseParticipants()
            ->where('role', CourseParticipant::ROLE_PARTICIPANT)
            ->whereIn('id', array_keys($validated['results']))
            ->get();

        $old = [];
        $new = [];
        foreach ($entries as $entry) {
            $result = $validated['results'][$entry->id] ?: null;
            if ($result === $entry->result) {
                continue;
            }

            $old[$entry->user_id] = $entry->result;
            $new[$entry->user_id] = $result;

            $entry->update([
                'result' => $result,
                'result_recorded_by_user_id' => $result === null ? null : $viewer->id,
                'result_recorded_at' => $result === null ? null : now(),
            ]);
        }

        if ($new === []) {
            return back()->with('success', 'No results changed.');
        }

        Log::write(
            'course_results_recorded',
            $course,
            $course->only(['branch_id', 'division_id']),
            $old,
            $new,
            count($new).' result(s) recorded for course "'.$course->name.'"'
        );
        $viewer->touchLastAdminActivity();

        return back()->with('success', count($new) === 1 ? '1 result saved.' : count($new).' results saved.');
    }

    private function authorizeCourse(User $viewer, Course $course): void
    {
        abort_unless($course->isVisibleTo($viewer), 403, 'This course is outside your area.');
        abort_unless($course->isPlanned(), 403, 'People can only be changed on a planned course.');
    }
}
