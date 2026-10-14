<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'student_name'    => $this->student->name ?? 'Unknown Student',
            'quiz_id'         => $this->quiz_id,
            'quiz_title'      => $this->quiz->title ?? 'Unknown Quiz',
            'score'           => $this->score,
            'completion_date' => $this->created_at->toDateTimeString(),
        ];
    }
}