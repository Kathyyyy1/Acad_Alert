<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionRecommendation extends Model
{
    protected $fillable = [
        'student_id', 'risk_factors', 'suggested_actions', 'generated_at'
    ];

    protected $casts = [
        'risk_factors' => 'array',
        'suggested_actions' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}