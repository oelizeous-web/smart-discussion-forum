<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'quiz_id'        => $this->quiz_id,
            'user_id'        => $this->user_id,
            'student_name'   => $this->whenLoaded('user', fn() => $this->user->name),
            'start_time'     => $this->start_time ? $this->start_time->toDateTimeString() : null,
            'submitted_at'   => $this->submitted_at ? $this->submitted_at->toDateTimeString() : null,
            'answers'        => $this->answers, // Laravel casts this to a clean array automatically
            'auto_submitted' => $this->auto_submitted,
        ];
    }
}