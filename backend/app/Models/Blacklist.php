<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $fillable = [
        'user_id',
        'warning_count',
        'status',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}