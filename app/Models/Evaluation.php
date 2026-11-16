<?php

namespace App\Models;

use App\Services\EvaluationScoringService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'employee_id',
        'user_id',
        'division_id',
        'period_month',
        'period_year',
        'total_score',
        'average_score',
        'predicate',
        'notes',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'total_score' => 'float',
        'average_score' => 'float',
        'period_month' => 'integer',
        'period_year' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(EvaluationDetail::class);
    }

    public function getBadgeClassAttribute(): string
    {
        $score = (float) $this->total_score;
        if ($score >= 85.0) {
            return 'badge-success';
        }
        if ($score >= 70.0) {
            return 'badge-info';
        }
        if ($score >= 55.0) {
            return 'badge-warning';
        }

        return 'badge-danger';
    }

    public function getMonthNameAttribute(): string
    {
        return EvaluationScoringService::getMonthName((int) $this->period_month);
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->month_name.' '.$this->period_year;
    }
}
