<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Division;
use App\Models\Log;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Courses: create, edit, cancel and list. Permissions are checked by route
 * middleware (view_courses, add_courses, edit_courses); the area by
 * Course::visibleTo() and Course::isManageableBy(). Participants, results and
 * closing follow in later steps.
 */
class CourseController extends Controller
{
    /** Columns shown in the audit log for create / update. */
    private const LOGGED_FIELDS = [
        'name', 'training_type_id', 'start_date', 'end_date', 'place',
        'branch_id', 'division_id', 'external_facilitators', 'status',
    ];

    public function index(Request $request): View
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();

        $query = Course::query()
            ->visibleTo($user)
            ->with(['trainingType', 'branch', 'division'])
            ->withCount([
                'courseParticipants as participants_count' => fn ($q) => $q->where('role', 'participant'),
            ]);

        $status = $request->input('status', 'upcoming');
        match ($status) {
            'past' => $query->where(fn ($q) => $q
                ->where('status', Course::STATUS_CLOSED)
                ->orWhere(fn ($q) => $q->where('status', Course::STATUS_PLANNED)->whereDate('end_date', '<', today())))
                ->orderByDesc('start_date'),
            'cancelled' => $query->where('status', Course::STATUS_CANCELLED)->orderByDesc('start_date'),
            default => $query->upcoming()->orderBy('start_date'),
        };

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $request->input('branch_id') === 'national'
                ? $query->whereNull('branch_id')
                : $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('training_type_id')) {
            $query->where('training_type_id', $request->input('training_type_id'));
        }

        return view('courses.index', [
            'courses' => $query->paginate(50)->withQueryString(),
            'status' => in_array($status, ['upcoming', 'past', 'cancelled'], true) ? $status : 'upcoming',
            'accessLevel' => $accessLevel,
            'branches' => $accessLevel === 'national' ? Branch::orderBy('name')->get() : collect(),
            'trainingTypes' => TrainingType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $user = Auth::user();
        $this->authorizeCreate($user);

        return view('courses.create', $this->formData($user, new Course));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeCreate($user);

        $course = Course::create([
            ...$this->validated($request, $user),
            'status' => Course::STATUS_PLANNED,
            'created_by_user_id' => $user->id,
        ]);

        Log::write(
            'course_created',
            $course,
            $course->only(['branch_id', 'division_id']),
            null,
            $this->loggedValues($course),
            "Course \"{$course->name}\" created"
        );
        $user->touchLastAdminActivity();

        return redirect()->route('courses.show', $course)->with('success', 'Course created.');
    }

    public function show(Course $course): View
    {
        abort_unless($course->isVisibleTo(Auth::user()), 403, 'This course is outside your area.');

        $course->load(['trainingType', 'branch', 'division', 'createdBy', 'closedBy', 'cancelledBy']);

        return view('courses.show', [
            'course' => $course,
            'canManage' => Auth::user()->can('edit_courses') && $course->isManageableBy(Auth::user()),
        ]);
    }

    public function edit(Course $course): View|RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user, $course);

        if (! $course->isPlanned()) {
            return redirect()->route('courses.show', $course)->with('error', 'A closed or cancelled course cannot be changed.');
        }

        return view('courses.edit', $this->formData($user, $course));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user, $course);

        if (! $course->isPlanned()) {
            return redirect()->route('courses.show', $course)->with('error', 'A closed or cancelled course cannot be changed.');
        }

        $old = $this->loggedValues($course);
        $course->update($this->validated($request, $user, $course));
        $new = $this->loggedValues($course);

        $changed = array_keys(array_diff_assoc(array_map('strval', $new), array_map('strval', $old)));

        if ($changed !== []) {
            Log::write(
                'course_updated',
                $course,
                $course->only(['branch_id', 'division_id']),
                array_intersect_key($old, array_flip($changed)),
                array_intersect_key($new, array_flip($changed)),
                "Course \"{$course->name}\" updated"
            );
            $user->touchLastAdminActivity();
        }

        return redirect()->route('courses.show', $course)->with('success', 'Course updated.');
    }

    public function cancel(Course $course): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user, $course);

        if (! $course->isPlanned()) {
            return redirect()->route('courses.show', $course)->with('error', 'Only a planned course can be cancelled.');
        }

        $course->update([
            'status' => Course::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user->id,
        ]);

        Log::write(
            'course_cancelled',
            $course,
            $course->only(['branch_id', 'division_id']),
            ['status' => Course::STATUS_PLANNED],
            ['status' => Course::STATUS_CANCELLED],
            "Course \"{$course->name}\" cancelled"
        );
        $user->touchLastAdminActivity();

        return redirect()->route('courses.show', $course)->with('success', 'Course cancelled.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** Only national and branch staff create courses (add_courses is on the route). */
    private function authorizeCreate(User $user): void
    {
        abort_unless(in_array($user->getAccessLevel(), ['national', 'branch'], true), 403, 'You cannot create courses.');
    }

    private function authorizeManage(User $user, Course $course): void
    {
        abort_unless($course->isManageableBy($user), 403, 'You can only change courses of your own branch.');
    }

    /**
     * Validated course fields. Branch staff always organise for their own
     * branch; national staff choose a branch or leave it empty (national
     * course). A division must belong to the chosen branch.
     */
    private function validated(Request $request, User $user, ?Course $course = null): array
    {
        $isNational = $user->getAccessLevel() === 'national';
        $branchId = $isNational ? $request->input('branch_id') : $user->getScopedId();

        $request->merge(['branch_id' => $branchId ?: null]);

        $typeRule = Rule::exists('training_types', 'id')->where(fn ($q) => $q
            ->where('is_active', true)
            ->when($course?->training_type_id, fn ($q, $currentId) => $q->orWhere('id', $currentId)));

        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'training_type_id' => ['required', 'integer', $typeRule],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'place' => ['nullable', 'string', 'max:191'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'external_facilitators' => ['nullable', 'string', 'max:2000'],
        ], [
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
        ], [
            'training_type_id' => 'training type',
        ]);

        $validator->after(function (Validator $validator) use ($request) {
            $divisionId = $request->input('division_id');
            if (! $divisionId) {
                return;
            }

            $divisionBranchId = Division::whereKey($divisionId)->value('branch_id');
            if ($request->input('branch_id') === null || (int) $divisionBranchId !== (int) $request->input('branch_id')) {
                $validator->errors()->add('division_id', 'The division must belong to the organising branch.');
            }
        });

        $validated = $validator->validate();
        $validated['division_id'] ??= null;

        return $validated;
    }

    private function formData(User $user, Course $course): array
    {
        $accessLevel = $user->getAccessLevel();
        $branchId = old('branch_id', $accessLevel === 'national' ? $course->branch_id : $user->getScopedId());

        return [
            'course' => $course,
            'accessLevel' => $accessLevel,
            'branches' => $accessLevel === 'national'
                ? Branch::orderBy('name')->get(['id', 'name'])
                : Branch::whereKey($user->getScopedId())->get(['id', 'name']),
            'divisions' => $branchId
                ? Division::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'trainingTypes' => TrainingType::query()
                ->where(fn ($q) => $q->where('is_active', true)
                    ->when($course->training_type_id, fn ($q, $currentId) => $q->orWhere('id', $currentId)))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /** The logged fields as plain values (dates as Y-m-d). */
    private function loggedValues(Course $course): array
    {
        $values = $course->only(self::LOGGED_FIELDS);
        $values['start_date'] = $course->start_date?->toDateString();
        $values['end_date'] = $course->end_date?->toDateString();

        return $values;
    }
}
