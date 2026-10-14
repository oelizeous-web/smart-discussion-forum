<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'agreed_to_rules',
        'status',
        'warning_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }

    // --- Existing Relationships ---

    public function groups() 
    {
        return $this->belongsToMany(Group::class, 'group_members');
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function blacklist()
    {
        return $this->hasOne(Blacklist::class);
    }

    public function accessibleTopics()
    {
        // A user can access many topics
        return $this->belongsToMany(Topic::class, 'thread_participants', 'user_id', 'topic_id');
    }

    // --- New Moderation & Inactivity Relationships & Methods ---

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Determine the exact last time the user communicated.
     * Used by the Inactivity Cron Job.
     */
    public function lastCommunicationDate()
    {
        $lastPost = $this->posts()->latest()->first();
        
        // If they have never posted, measure from the day they created their account
        return $lastPost ? $lastPost->created_at : $this->created_at;
    }

    /**
     * Check if the user is currently serving an active suspension.
     * Used by API Middleware and Login checks.
     */
    public function isSuspended()
    {
        return $this->blacklist()
            ->where('status', 'SUSPENDED')
            ->where('expiry_date', '>', now())
            ->exists();
    }

    /**
     * Issue a warning to the user and auto-suspend if they hit the limit.
     */
// --- Moderation Actions inside admin.members ---

    public function issueWarning(): void
    {
        if ($this->status === 'SUSPENDED') {
            return; // already at the hardest penalty; no further escalation
        }

        $newCount = $this->warning_count + 1;

        if ($newCount >= 3) {
            // 3rd strike: hard suspension
            $this->update(['warning_count' => $newCount, 'status' => 'SUSPENDED']);

            $this->blacklist()->updateOrCreate(
                ['user_id' => $this->id],
                ['warning_count' => $newCount, 'status' => 'SUSPENDED', 'expiry_date' => now()->addDays(7)]
            );
        } elseif ($newCount === 2) {
            // 2nd strike: soft compliance period
            $this->update(['warning_count' => $newCount, 'status' => 'COMPLIANCE_PERIOD']);

            $this->blacklist()->updateOrCreate(
                ['user_id' => $this->id],
                ['warning_count' => $newCount, 'status' => 'COMPLIANCE_PERIOD', 'expiry_date' => now()->addHours(48)]
            );
        } else {
            // 1st strike: just record it
            $this->update(['warning_count' => $newCount]);

            $this->blacklist()->updateOrCreate(
                ['user_id' => $this->id],
                ['warning_count' => $newCount]
            );
        }
    }

    public function pardon()
    {
        $this->update(['warning_count' => 0, 'status' => 'ACTIVE']);

        if ($this->blacklist) {
            $this->blacklist->update([
                'warning_count' => 0,
                'status'        => 'ACTIVE',
                'expiry_date'   => null,
            ]);
        }
    }

    public function manualSuspend()
    {
        $this->update(['status' => 'SUSPENDED']);

        $this->blacklist()->updateOrCreate(
            ['user_id' => $this->id],
            ['status' => 'SUSPENDED', 'expiry_date' => now()->addDays(7)]
        );
    }
}