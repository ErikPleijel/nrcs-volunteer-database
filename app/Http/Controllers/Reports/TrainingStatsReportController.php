<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrainingStatsReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        $userDivisionId = null;
        $userDivision = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userDivisionId = $scopedId;
                $userBranchId = $userDivision->branch_id;
            }
        }

        // ── Dropdowns ──────────────────────────────────────────────────────
        $branches = collect();
        $divisions = collect();
        $redCrossUnits = collect();

        switch ($accessLevel) {
            case 'national':
                $branches = Branch::orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)->orderBy('name')->get();
                }
                break;
            case 'branch':
                $branches = Branch::where('id', $userBranchId)->get();
                $divisions = Division::where('branch_id', $userBranchId)->orderBy('name')->get();
                break;
            case 'division':
                if ($userDivision) {
                    $branches = Branch::where('id', $userDivision->branch_id)->get();
                    $divisions = Division::where('id', $userDivisionId)->get();
                    $redCrossUnits = RedCrossUnit::where('division_id', $userDivisionId)->orderBy('name')->get();
                }
                break;
        }

        $selectedDivisionId = $request->input('division_id', $userDivisionId);
        if ($selectedDivisionId && $accessLevel !== 'division') {
            $redCrossUnits = RedCrossUnit::where('division_id', $selectedDivisionId)->orderBy('name')->get();
        }

        $trainingTypes = TrainingType::active()->orderBy('name')->get();
        $activeTab = in_array($request->input('tab'), ['coverage', 'staleness', 'expiry', 'certificates'])
            ? $request->input('tab')
            : 'coverage';
        $selectedTypeId = $request->input('training_type_id');
        $selectedType = $selectedTypeId ? $trainingTypes->firstWhere('id', $selectedTypeId) : null;

        // ── Determine area mode ────────────────────────────────────────────
        // When a training type is selected, rows = areas instead of training types
        $areaMode = $selectedType !== null;

        // Build the list of area rows when in area mode
        $areaRows = collect();
        if ($areaMode) {
            switch ($accessLevel) {
                case 'national':
                    $areaRows = Branch::orderBy('name')->get()->map(fn ($b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'scope' => 'branch',
                        'field' => 'branch_id',
                    ]);
                    break;
                case 'branch':
                    $areaRows = Division::where('branch_id', $userBranchId)->orderBy('name')->get()->map(fn ($d) => [
                        'id' => $d->id,
                        'name' => $d->name,
                        'scope' => 'division',
                        'field' => 'division_id',
                    ]);
                    break;
                case 'division':
                    $areaRows = RedCrossUnit::where('division_id', $userDivisionId)->orderBy('name')->get()->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'scope' => 'unit',
                        'field' => 'red_cross_unit_id',
                    ]);
                    break;
            }
        }

        // ── Coverage/Expiry/Certificates drill state ─────────────────────────
        // Multi-level click-through (Training Type → Branch → Division → Unit),
        // driven by which scope params are present rather than accessLevel alone.
        // Other tabs keep using the $accessLevel-only $areaRows built above.
        $drillLevel = null;
        if (in_array($activeTab, ['coverage', 'expiry', 'certificates']) && $areaMode) {
            if ($accessLevel === 'national') {
                if ($request->filled('branch_id') && $request->filled('division_id')) {
                    $drillLevel = 'unit';
                } elseif ($request->filled('branch_id')) {
                    $drillLevel = 'division';
                } else {
                    $drillLevel = 'branch';
                }
            } elseif ($accessLevel === 'branch') {
                $drillLevel = $request->filled('division_id') ? 'unit' : 'division';
            } elseif ($accessLevel === 'division') {
                $drillLevel = 'unit';
            }

            switch ($drillLevel) {
                case 'branch':
                    $areaRows = Branch::orderBy('name')->get()->map(fn ($b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'scope' => 'branch',
                        'field' => 'branch_id',
                    ]);
                    break;
                case 'division':
                    $divisionParentBranchId = $accessLevel === 'national' ? $request->input('branch_id') : $userBranchId;
                    $areaRows = Division::where('branch_id', $divisionParentBranchId)->orderBy('name')->get()->map(fn ($d) => [
                        'id' => $d->id,
                        'name' => $d->name,
                        'scope' => 'division',
                        'field' => 'division_id',
                    ]);
                    break;
                case 'unit':
                    $unitParentDivisionId = $accessLevel === 'division' ? $userDivisionId : $request->input('division_id');
                    $areaRows = RedCrossUnit::where('division_id', $unitParentDivisionId)->orderBy('name')->get()->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'scope' => 'unit',
                        'field' => 'red_cross_unit_id',
                    ]);
                    break;
            }
        }

        // ── Current branch/division for breadcrumb labels ───────────────────
        $currentBranch = null;
        if ($accessLevel === 'branch' || $accessLevel === 'division') {
            $currentBranch = Branch::find($userBranchId);
        } elseif ($request->filled('branch_id')) {
            $currentBranch = Branch::find($request->input('branch_id'));
        }

        $currentDivision = null;
        if ($accessLevel === 'division') {
            $currentDivision = $userDivision;
        } elseif ($request->filled('division_id')) {
            $currentDivision = Division::find($request->input('division_id'));
        }

        // ── Certificates-only date range ────────────────────────────────────
        $certDateFrom = $request->input('cert_date_from');
        $certDateTo = $request->input('cert_date_to');

        // ── Population filter (Coverage/Staleness/Expiry only) ─────────────
        $population = $request->input('population', 'volunteers');
        $applyPopulation = function ($query) use ($population) {
            if ($population === 'all') {
                // Genuine catch-all: every operational user, regardless of
                // unit/membership-payment status — matches the Filter
                // Wizard's "Volunteers and Members" definition.
                $query->whereIn('lifecycle_status', User::OPERATIONAL_STATUSES);
            } else {
                $query->volunteers();
            }

            return $query;
        };

        // ── Shared scope builder ───────────────────────────────────────────
        $scopeTrainings = function ($query, $applyPop = false) use ($request, $accessLevel, $userBranchId, $userDivisionId, $selectedTypeId, $applyPopulation) {
            $query->active();

            if ($applyPop) {
                $query->whereHas('user', fn ($q) => $applyPopulation($q));
            }

            if ($accessLevel === 'branch' && $userBranchId) {
                $query->where('branch_id', $userBranchId);
            } elseif ($accessLevel === 'division' && $userDivisionId) {
                $query->where('division_id', $userDivisionId);
            }
            if ($accessLevel === 'national' && $request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
                $query->where('division_id', $request->division_id);
            }
            if ($request->filled('red_cross_unit_id')) {
                $query->whereHas('user', fn ($q) => $q->where('red_cross_unit_id', $request->red_cross_unit_id));
            }
            if ($selectedTypeId) {
                $query->where('training_type_id', $selectedTypeId);
            }

            return $query;
        };

        // Scope trainings for a specific area row
        $scopeTrainingsForArea = function ($query, array $area, $applyPop = false) use ($scopeTrainings) {
            $query = $scopeTrainings($query, $applyPop);
            if ($area['scope'] === 'unit') {
                $query->whereHas('user', fn ($q) => $q->where('red_cross_unit_id', $area['id']));
            } else {
                $query->where($area['field'], $area['id']);
            }

            return $query;
        };

        // ── VIEW 1: Expiry timeline ────────────────────────────────────────
        $expiryData = [];
        $expiryAreaHeader = $activeTab === 'expiry' && $areaMode
            ? ($drillLevel === 'branch' ? 'Branch' : ($drillLevel === 'division' ? 'Division' : 'RC Unit'))
            : 'Training Type';
        if ($activeTab === 'expiry') {
            $now = Carbon::now();
            $buckets = [
                'past_3' => ['label' => '> 3mo ago', 'from' => null,                      'to' => $now->copy()->subMonths(3)],
                'past_2' => ['label' => '2 mo ago',  'from' => $now->copy()->subMonths(3), 'to' => $now->copy()->subMonths(2)],
                'past_1' => ['label' => '1 mo ago',  'from' => $now->copy()->subMonths(2), 'to' => $now->copy()->subMonths(1)],
                'next_1' => ['label' => 'In 1 mo',   'from' => $now->copy(),               'to' => $now->copy()->addMonths(1)],
                'next_2' => ['label' => 'In 2 mo',   'from' => $now->copy()->addMonths(1), 'to' => $now->copy()->addMonths(2)],
                'next_3' => ['label' => 'In 3 mo',   'from' => $now->copy()->addMonths(2), 'to' => $now->copy()->addMonths(3)],
                'next_4' => ['label' => 'In 4 mo',   'from' => $now->copy()->addMonths(3), 'to' => $now->copy()->addMonths(4)],
                'next_5' => ['label' => 'In 5 mo',   'from' => $now->copy()->addMonths(4), 'to' => $now->copy()->addMonths(5)],
                'next_6' => ['label' => 'In 6 mo',   'from' => $now->copy()->addMonths(5), 'to' => $now->copy()->addMonths(6)],
                'future' => ['label' => '> 6 mo',    'from' => $now->copy()->addMonths(6), 'to' => null],
            ];

            $buildExpiryRow = function ($label, $queryScope) use ($buckets) {
                $row = ['label' => $label, 'buckets' => [], 'total' => 0];
                foreach ($buckets as $key => $bucket) {
                    $q = $queryScope(Training::query())->whereNotNull('valid_years');
                    if ($bucket['from'] && $bucket['to']) {
                        $q->whereBetween(DB::raw('DATE_ADD(training_date, INTERVAL valid_years YEAR)'), [$bucket['from'], $bucket['to']]);
                    } elseif (! $bucket['from'] && $bucket['to']) {
                        $q->where(DB::raw('DATE_ADD(training_date, INTERVAL valid_years YEAR)'), '<', $bucket['to']);
                    } elseif ($bucket['from'] && ! $bucket['to']) {
                        $q->where(DB::raw('DATE_ADD(training_date, INTERVAL valid_years YEAR)'), '>=', $bucket['from']);
                    }
                    $count = $q->count();
                    $row['buckets'][$key] = $count;
                    $row['total'] += $count;
                }

                return $row;
            };

            if ($areaMode) {
                foreach ($areaRows as $area) {
                    $row = $buildExpiryRow($area['name'], fn ($q) => $scopeTrainingsForArea($q, $area, true));
                    $row['id'] = $area['id'];
                    $expiryData[] = $row;
                }
            } else {
                $expiryTypes = $trainingTypes->whereNotNull('validity_years_limit');
                foreach ($expiryTypes as $type) {
                    $row = $buildExpiryRow($type->name, fn ($q) => $scopeTrainings($q, true)->where('training_type_id', $type->id)->whereNotNull('valid_years'));
                    $row['id'] = $type->id;
                    if ($row['total'] > 0) {
                        $expiryData[] = $row;
                    }
                }
            }
        }

        // ── VIEW 2: Coverage ──────────────────────────────────────────────
        $coverageData = [];          // used for area mode only
        $coverageDataFirstAid = [];  // non-area mode: first-aid types (+ "Any First Aid" summary)
        $coverageDataOther = [];     // non-area mode: all other types
        if ($activeTab === 'coverage') {
            $buildPersonQuery = function () use ($request, $accessLevel, $userBranchId, $userDivisionId, $applyPopulation) {
                $q = $applyPopulation(User::query());
                if ($accessLevel === 'branch' && $userBranchId) {
                    $q->where('branch_id', $userBranchId);
                } elseif ($accessLevel === 'division' && $userDivisionId) {
                    $q->where('division_id', $userDivisionId);
                }
                if ($accessLevel === 'national' && $request->filled('branch_id')) {
                    $q->where('branch_id', $request->branch_id);
                }
                if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
                    $q->where('division_id', $request->division_id);
                }
                if ($request->filled('red_cross_unit_id')) {
                    $q->where('red_cross_unit_id', $request->red_cross_unit_id);
                }

                return $q;
            };

            $trainedSubQuery = function ($q) use ($selectedTypeId, $request) {
                $q->where('is_deleted', false);
                if ($selectedTypeId) {
                    $q->where('training_type_id', $selectedTypeId);
                }
                if ($request->filled('date_from')) {
                    $q->where('training_date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q->where('training_date', '<=', $request->date_to);
                }
            };

            if ($areaMode) {
                foreach ($areaRows as $area) {
                    $pq = $buildPersonQuery();
                    if ($area['scope'] === 'unit') {
                        $pq->where('red_cross_unit_id', $area['id']);
                    } else {
                        $pq->where($area['field'], $area['id']);
                    }
                    $total = $pq->count();
                    $trained = (clone $pq)->whereHas('trainings', $trainedSubQuery)->count();
                    $coverageData[] = [
                        'label' => $area['name'],
                        'trained' => $trained,
                        'not_trained' => $total - $trained,
                        'total' => $total,
                    ];
                }
            } else {
                $totalActive = $buildPersonQuery()->count();

                // "Any First Aid" summary — distinct people with at least one
                // non-deleted first-aid training (not a sum of the per-type rows).
                $trainedAnyFa = (clone $buildPersonQuery())->whereHas('trainings', function ($q) use ($request) {
                    $q->where('is_deleted', false)
                        ->whereHas('trainingType', fn ($t) => $t->where('is_first_aid', true));
                    if ($request->filled('date_from')) {
                        $q->where('training_date', '>=', $request->date_from);
                    }
                    if ($request->filled('date_to')) {
                        $q->where('training_date', '<=', $request->date_to);
                    }
                })->count();
                $coverageDataFirstAid[] = [
                    'label' => 'Any First Aid',
                    'trained' => $trainedAnyFa,
                    'not_trained' => $totalActive - $trainedAnyFa,
                    'total' => $totalActive,
                    'is_summary' => true,
                ];

                $coverageTypes = $trainingTypes;
                foreach ($coverageTypes as $type) {
                    $trained = (clone $buildPersonQuery())->whereHas('trainings', function ($q) use ($type, $request) {
                        $q->where('is_deleted', false)->where('training_type_id', $type->id);
                        if ($request->filled('date_from')) {
                            $q->where('training_date', '>=', $request->date_from);
                        }
                        if ($request->filled('date_to')) {
                            $q->where('training_date', '<=', $request->date_to);
                        }
                    })->count();
                    $row = [
                        'label' => $type->name,
                        'trained' => $trained,
                        'not_trained' => $totalActive - $trained,
                        'total' => $totalActive,
                    ];
                    if ($trained > 0 || ($totalActive - $trained) > 0) {
                        if ($type->is_first_aid) {
                            $coverageDataFirstAid[] = $row;
                        } else {
                            $coverageDataOther[] = $row;
                        }
                    }
                }
            }
        }

        // ── VIEW 3: Certificates ──────────────────────────────────────────
        $certificateData = [];          // used for area mode only
        $certificateDataFirstAid = [];  // non-area mode: first-aid types
        $certificateDataOther = [];     // non-area mode: all other types
        $certAreaHeader = $activeTab === 'certificates' && $areaMode
            ? ($drillLevel === 'branch' ? 'Branch' : ($drillLevel === 'division' ? 'Division' : 'RC Unit'))
            : 'Training Type';
        if ($activeTab === 'certificates') {
            $buildCertRow = function ($label, $trainingIds) {
                if ($trainingIds->isEmpty()) {
                    return ['label' => $label, 'attendance_printed' => 0, 'competence_printed' => 0, 'no_certificate' => 0, 'total' => 0];
                }
                $attendance = CertificatePrint::whereIn('training_id', $trainingIds)->where('certificate_type', 'training_attendance')->whereNull('deleted_at')->count();
                $competence = CertificatePrint::whereIn('training_id', $trainingIds)->where('certificate_type', 'training_competence')->whereNull('deleted_at')->count();
                $anyPrinted = CertificatePrint::whereIn('training_id', $trainingIds)->whereNull('deleted_at')->distinct('training_id')->count();

                return [
                    'label' => $label,
                    'attendance_printed' => $attendance,
                    'competence_printed' => $competence,
                    'no_certificate' => max(0, $trainingIds->count() - $anyPrinted),
                    'total' => $trainingIds->count(),
                ];
            };

            if ($areaMode) {
                foreach ($areaRows as $area) {
                    $certQuery = $scopeTrainingsForArea(Training::query(), $area);
                    if ($request->filled('cert_date_from')) {
                        $certQuery->where('training_date', '>=', $request->input('cert_date_from'));
                    }
                    if ($request->filled('cert_date_to')) {
                        $certQuery->where('training_date', '<=', $request->input('cert_date_to'));
                    }
                    $ids = $certQuery->pluck('id');
                    $row = $buildCertRow($area['name'], $ids);
                    $row['id'] = $area['id'];
                    $certificateData[] = $row;
                }
            } else {
                foreach ($trainingTypes as $type) {
                    $certQuery = $scopeTrainings(Training::query())->where('training_type_id', $type->id);
                    if ($request->filled('cert_date_from')) {
                        $certQuery->where('training_date', '>=', $request->input('cert_date_from'));
                    }
                    if ($request->filled('cert_date_to')) {
                        $certQuery->where('training_date', '<=', $request->input('cert_date_to'));
                    }
                    $ids = $certQuery->pluck('id');
                    if ($ids->isEmpty()) {
                        continue;
                    }
                    $row = $buildCertRow($type->name, $ids);
                    $row['id'] = $type->id;
                    if ($row['total'] > 0) {
                        if ($type->is_first_aid) {
                            $certificateDataFirstAid[] = $row;
                        } else {
                            $certificateDataOther[] = $row;
                        }
                    }
                }
            }
        }

        // ── VIEW 5: First Aid Staleness ────────────────────────────────────
        $stalenessData = [];
        $stalenessAreaHeader = 'Branch';
        $totalsRow = [];
        $areaField = null;
        if ($activeTab === 'staleness') {
            $now = Carbon::now();
            $c12 = $now->copy()->subMonths(12)->toDateString();
            $c24 = $now->copy()->subMonths(24)->toDateString();
            $c36 = $now->copy()->subMonths(36)->toDateString();
            $c48 = $now->copy()->subMonths(48)->toDateString();
            $c60 = $now->copy()->subMonths(60)->toDateString();

            // Rows drill by the most specific active location scope.
            $effBranchId = $accessLevel === 'national' ? $request->input('branch_id') : $userBranchId;
            $effDivisionId = in_array($accessLevel, ['national', 'branch']) ? $request->input('division_id') : $userDivisionId;

            if ($effDivisionId) {
                $areaField = 'red_cross_unit_id';
                $areaList = RedCrossUnit::where('division_id', $effDivisionId)->orderBy('name')->get(['id', 'name']);
                $stalenessAreaHeader = 'RC Unit';
            } elseif ($effBranchId) {
                $areaField = 'division_id';
                $areaList = Division::where('branch_id', $effBranchId)->orderBy('name')->get(['id', 'name']);
                $stalenessAreaHeader = 'Division';
            } else {
                $areaField = 'branch_id';
                $areaList = Branch::orderBy('name')->get(['id', 'name']);
                $stalenessAreaHeader = 'Branch';
            }

            // One grouped pass over a scoped base query → [area_id => row of band counts]
            $bandPass = function ($base) use ($request, $accessLevel, $userBranchId, $userDivisionId, $areaField, $c12, $c24, $c36, $c48, $c60) {
                if ($accessLevel === 'branch' && $userBranchId) {
                    $base->where('branch_id', $userBranchId);
                } elseif ($accessLevel === 'division' && $userDivisionId) {
                    $base->where('division_id', $userDivisionId);
                }
                if ($accessLevel === 'national' && $request->filled('branch_id')) {
                    $base->where('branch_id', $request->branch_id);
                }
                if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
                    $base->where('division_id', $request->division_id);
                }
                if ($request->filled('red_cross_unit_id')) {
                    $base->where('red_cross_unit_id', $request->red_cross_unit_id);
                }

                return $base->whereNotNull('last_first_aid_at')
                    ->groupBy($areaField)
                    ->selectRaw("{$areaField} as area_id")
                    ->selectRaw('SUM(last_first_aid_at < ? AND last_first_aid_at >= ?) as b12', [$c12, $c24])
                    ->selectRaw('SUM(last_first_aid_at < ? AND last_first_aid_at >= ?) as b24', [$c24, $c36])
                    ->selectRaw('SUM(last_first_aid_at < ? AND last_first_aid_at >= ?) as b36', [$c36, $c48])
                    ->selectRaw('SUM(last_first_aid_at < ? AND last_first_aid_at >= ?) as b48', [$c48, $c60])
                    ->selectRaw('SUM(last_first_aid_at < ?) as ge60', [$c60])
                    ->get()
                    ->keyBy('area_id');
            };

            $populationRows = $bandPass($applyPopulation(User::query()));

            $keys = ['b12', 'b24', 'b36', 'b48', 'ge60'];
            foreach ($areaList as $area) {
                $r = $populationRows->get($area->id);
                $counts = [];
                foreach ($keys as $k) {
                    $counts[$k] = (int) ($r->$k ?? 0);
                }
                $stalenessData[] = [
                    'id' => $area->id,
                    'label' => $area->name,
                    'counts' => $counts,
                ];
            }

            // Totals row — sum each band across all areas already in $stalenessData.
            foreach ($keys as $k) {
                $totalsRow[$k] = array_sum(array_column(array_column($stalenessData, 'counts'), $k));
            }
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return view('reports.trainings.stats', compact(
            'trainingTypes',
            'branches',
            'divisions',
            'redCrossUnits',
            'accessLevel',
            'userBranchId',
            'userDivisionId',
            'activeTab',
            'expiryData',
            'coverageData',
            'coverageDataFirstAid',
            'coverageDataOther',
            'certificateData',
            'certificateDataFirstAid',
            'certificateDataOther',
            'stalenessData',
            'stalenessAreaHeader',
            'totalsRow',
            'areaMode',
            'selectedType',
            'selectedTypeId',
            'drillLevel',
            'currentBranch',
            'currentDivision',
            'areaRows',
            'population',
            'areaField',
            'expiryAreaHeader',
            'certDateFrom',
            'certDateTo',
            'certAreaHeader'
        ));
    }
}
