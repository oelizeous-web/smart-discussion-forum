<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // can_access / participant_status are only set when this resource is
        // built from TopicController::index(); default to "visible" for any
        // other call site (e.g. TopicController::store's single-topic return).
        $canAccess = $this->can_access ?? true;

        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'description'        => $canAccess ? $this->description : null,
            'group_id'           => $this->group_id,
            'post_count'         => $canAccess ? $this->post_count : null,
            'original_poster'    => $this->author->name ?? 'Unknown System User',
            'created_at'         => $this->created_at->toDateTimeString(),
            'is_private'         => (bool) $this->is_private,
            'locked'             => !$canAccess,
            'participant_status' => $this->participant_status ?? null,
        ];
    }
}