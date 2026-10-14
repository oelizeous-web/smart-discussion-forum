<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $guarded = []; // Allows all fields to be mass-assigned

    public function post() 
    { 
        return $this->belongsTo(Post::class); 
    }

    public function reporter() 
    { 
        return $this->belongsTo(User::class, 'reported_by'); 
    }
}