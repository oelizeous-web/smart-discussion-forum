<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name', 'user_id', 'points_per_post', 'points_per_quiz_completion'];

    public function creator() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function topics() {
        return $this->hasMany(Topic::class);
    }

    public function quizzes() {
        return $this->hasMany(Quiz::class);
    }

    public function members() {
        return $this->belongsToMany(User::class, 'group_members');
    }
}