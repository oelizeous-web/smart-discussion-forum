<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    // Ensure 'answers' and 'auto_submitted' are allowed to be saved!
    protected $fillable = [
        'quiz_id', 
        'user_id', 
        'start_time', 
        'submitted_at', 
        'answers', 
        'auto_submitted'
    ];

    protected $casts = [
        'start_time'     => 'datetime',
        'submitted_at'   => 'datetime',
        'auto_submitted' => 'boolean',
        'answers'        => 'array', 
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}




