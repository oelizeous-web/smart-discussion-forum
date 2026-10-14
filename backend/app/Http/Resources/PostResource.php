<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'author_name' => $this->author->name ?? 'Unknown Member',
            'topic_id'    => $this->topic_id,
            'content'     => $this->content,
            'receiver_id' => $this->receiver_id, 
            'timestamp'   => $this->created_at->toDateTimeString(),
        ];
    }
}