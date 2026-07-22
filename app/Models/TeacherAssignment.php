<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAssignment extends Model
{
    protected $fillable = [
        'master_teacher_id', 'block_id', 'subject_id', 'school_year', 'semester'
    ];

    public function masterTeacher(): BelongsTo
    {
        return $this->belongsTo(MasterTeacher::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}