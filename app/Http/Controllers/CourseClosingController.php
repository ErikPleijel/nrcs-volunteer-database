<?php

namespace App\Http\Controllers;

use App\Exceptions\ArchivedReactivationRequiresConfirmation;
use App\Exceptions\RecordAlreadyDecided;
use App\Models\Course;
use App\Models\CourseParticipant;
use App\Models\Log;
use App\Models\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Closing a course and approving the training records it issued.
 *
 * Closing (edit_courses, organisers only) creates one PENDING training record
 * per participant who passed, submitted by the person who closed it, so the
 * records go through the normal four-eyes approval. It runs once: the course
 * row is locked and re-checked, and UNIQUE(course_id, user_id) on trainings
 * backs that up. Failed and absent results stay in course_participants only.
 */
class CourseClosingController extends Controller
{
    public function close(Course $course): RedirectResponse
    {
        $closer = Auth::user();
        abort_unless($course->isManageableBy($closer), 403, 'You can only close courses of your own branch.');

        $result = DB::transaction(function () use ($course, $closer) {
            $course = Course::whereKey($course->id)->lockForUpdate()->first();

            if ($blocker = $course->closeBlocker()) {
                return ['error' => $blocker];
            }

            $course->load('trainingType');
            $passed = $course->courseParticipants()
                ->where('role', CourseParticipant::ROLE_PARTICIPANT)
                ->where('result', CourseParticipant::RESULT_PASSED)
                ->with('user:id,first_name,last_name,branch_id,division_id')
                ->get();

            // trainings.division_id is required; a person without a division
            // takes the course's division, if it has one.
            $noDivision = $passed->filter(fn (CourseParticipant $p) => ! $p->user?->division_id && ! $course->division_id);
            if ($noDivision->isNotEmpty()) {
                return ['error' => 'These participants have no division, so no training record can be made: '
                    .$noDivision->map(fn ($p) => $p->user?->full_name ?? "#{$p->user_id}")->join(', ')
                    .'. Set their division first.'];
            }

            foreach ($passed as $participant) {
                $training = Training::create([
                    'user_id' => $participant->user_id,
                    'training_type_id' => $course->training_type_id,
                    'course_id' => $course->id,
                    'training_date' => $course->start_date->toDateString(),
                    'duration' => $course->durationInDays(),
                    // Validity is HQ-governed per training type, as in TrainingController::store().
                    'valid_years' => $course->trainingType?->validity_years_limit,
                    'submitted_at' => now(),
                    'submission_name' => $closer->full_name,
                    'submitted_by_user_id' => $closer->id,
                    'reference' => mb_substr($course->name, 0, 45),
                    'branch_id' => $participant->user->branch_id ?? $course->branch_id,
                    'division_id' => $participant->user->division_id ?? $course->division_id,
                    'is_deleted' => false,
                ]);

                $participant->update(['training_id' => $training->id]);
            }

            $course->update([
                'status' => Course::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $closer->id,
            ]);

            $counts = $course->courseParticipants()
                ->where('role', CourseParticipant::ROLE_PARTICIPANT)
                ->pluck('result')
                ->countBy()
                ->all();

            Log::write(
                'course_closed',
                $course,
                $course->only(['branch_id', 'division_id']),
                ['status' => Course::STATUS_PLANNED],
                [
                    'status' => Course::STATUS_CLOSED,
                    'passed' => $counts[CourseParticipant::RESULT_PASSED] ?? 0,
                    'failed' => $counts[CourseParticipant::RESULT_FAILED] ?? 0,
                    'absent' => $counts[CourseParticipant::RESULT_ABSENT] ?? 0,
                    'training_records_created' => $passed->count(),
                ],
                "Course \"{$course->name}\" closed"
            );

            return ['created' => $passed->count()];
        });

        if (isset($result['error'])) {
            return redirect()->route('courses.show', $course)->with('error', $result['error']);
        }

        $closer->touchLastAdminActivity();

        $created = $result['created'];

        return redirect()->route('courses.show', $course)->with('success', $created === 0
            ? 'Course closed. No one passed, so no training records were created.'
            : "Course closed. {$created} training record(s) were created and now wait for approval.");
    }

    /**
     * "Approve all from this course": approve every pending record the course
     * issued, using the normal per-record approve() (four-eyes, lifecycle,
     * first-aid date). Archived members are skipped, as in bulk approve.
     */
    public function approveRecords(Course $course): RedirectResponse
    {
        $approver = Auth::user();
        abort_unless($course->canApproveRecords($approver), 403, 'Only approvers of the organising branch, or national approvers, can approve these records.');

        if ((int) $course->closed_by_user_id === (int) $approver->id) {
            return redirect()->route('courses.show', $course)
                ->with('error', 'You closed this course, so another approver must approve its training records.');
        }

        $approved = 0;
        $skipped = [];

        foreach (Training::pendingApproval()->where('course_id', $course->id)->with('user')->get() as $record) {
            if ((int) $record->submitted_by_user_id === (int) $approver->id) {
                $skipped[] = ($record->user?->full_name ?? "#{$record->user_id}").' (your own submission)';

                continue;
            }

            try {
                $record->approve($approver, false);
                $approved++;
            } catch (ArchivedReactivationRequiresConfirmation) {
                $skipped[] = ($record->user?->full_name ?? "#{$record->user_id}").' (archived: approve on its own review page)';
            } catch (RecordAlreadyDecided) {
                // Decided by someone else meanwhile; nothing to do.
            }
        }

        if ($approved > 0) {
            $approver->touchLastAdminActivity();
        }

        $message = $approved === 1 ? '1 training record approved.' : "{$approved} training records approved.";

        return redirect()->route('courses.show', $course)
            ->with('success', $message)
            ->with('skipped', $skipped);
    }
}
