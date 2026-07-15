<?php

namespace App\Http\Controllers;

use App\Models\TutorialProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TutorialController extends Controller
{
    public function index()
    {
        $allLevels   = config('tutorials.levels');
        $allLessons  = config('tutorials.lessons');
        $accessLevel = Auth::user()->getAccessLevel();
        $doneKeys    = Auth::user()->tutorialProgress->pluck('lesson_key')->flip();

        $levels = collect($allLevels)
            ->map(function ($levelConfig, $lvl) use ($allLessons, $accessLevel, $doneKeys) {
                $lessons = collect($allLessons)
                    ->filter(fn($l) => $l['level'] === $lvl)
                    ->sortBy('order')
                    ->map(fn($l, $k) => array_merge($l, [
                        'key'       => $k,
                        'completed' => $doneKeys->has($k),
                    ]));

                return array_merge($levelConfig, [
                    'level'    => $lvl,
                    'unlocked' => in_array($accessLevel, $levelConfig['min_access']),
                    'lessons'  => $lessons,
                ]);
            })
            ->sortKeys();

        return view('tutorials.index', compact('levels'));
    }

    public function level(int $level)
    {
        return redirect()->route('tutorials.index');
    }

    public function lesson(string $key)
    {
        $lessons = config('tutorials.lessons');

        if (!isset($lessons[$key])) {
            abort(404);
        }

        $lesson = $lessons[$key];
        $levelConfig = config('tutorials.levels')[$lesson['level']];
        $accessLevel = Auth::user()->getAccessLevel();

        if (!in_array($accessLevel, $levelConfig['min_access'])) {
            abort(403);
        }

        return view($lesson['view'], [
            'lessonKey'   => $key,
            'lessonTitle' => $lesson['title'],
            'lessonLevel' => $lesson['level'],
        ]);
    }

    public function complete(Request $request, string $key)
    {
        Log::info('tutorial progress hit', ['user' => optional(auth()->user())->id, 'payload' => $request->all()]);

        $lessons = config('tutorials.lessons');

        if (!isset($lessons[$key])) {
            return response()->json(['ok' => false], 404);
        }

        $lesson = $lessons[$key];
        $levelConfig = config('tutorials.levels')[$lesson['level']];
        $accessLevel = Auth::user()->getAccessLevel();

        if (!in_array($accessLevel, $levelConfig['min_access'])) {
            return response()->json(['ok' => false], 403);
        }

        try {
            TutorialProgress::firstOrCreate(
                ['user_id' => Auth::id(), 'lesson_key' => $key],
                ['completed_at' => now()]
            );
        } catch (\Throwable $e) {
            Log::error('tutorial progress write failed', ['e' => $e->getMessage()]);
            throw $e;
        }

        return response()->json(['ok' => true]);
    }
}
