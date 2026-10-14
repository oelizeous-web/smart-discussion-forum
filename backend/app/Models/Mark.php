<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    // 1. Allow mass assignment for these columns
    protected $fillable = ['user_id', 'quiz_id', 'score'];

    // 2. Define relationships needed for the dashboard
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}