<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['content', 'topic_id', 'user_id','attachment_path'];

    public function topic() {
        return $this->belongsTo(Topic::class);
    }

    public function author() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
