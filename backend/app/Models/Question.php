<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 
        'text', 
        'options', 
        'correct_answer', 
        'points'
    ];

    // This automatically decodes the JSON options into an array
    protected $casts = [
        'options' => 'array', 
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}