<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorialProgress extends Model
{
    // Table is singular; without this Eloquent would look for "tutorial_progresses".
    protected $table = 'tutorial_progress';

    protected $fillable = ['user_id', 'lesson_key', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
