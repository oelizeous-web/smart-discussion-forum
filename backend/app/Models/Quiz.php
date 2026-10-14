<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'creator_id',
        'group_id',
        'time_limit',
        'auto_submit',
        'start_time',
    ];

    // Cast attributes to native types / Carbon date instances
    protected $casts = [
        'start_time'  => 'datetime',
        'auto_submit' => 'boolean',
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
    
    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

        public function hasClosed(): bool
    {
        if (!$this->start_time) {
            return false;
        }
        return now()->isAfter($this->start_time->copy()->addMinutes($this->time_limit));
    }

}