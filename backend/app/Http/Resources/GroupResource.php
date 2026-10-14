<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'creator_id'   => $this->user_id,
            'creator_name' => $this->creator->name ?? 'Unknown',
            'created_at'   => $this->created_at->toDateTimeString(),
            'member_count' => $this->whenCounted('members'),
            'topic_count'  => $this->whenCounted('topics'),
        ];
    }
}