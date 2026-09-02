<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordMessage extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'user_id', 'body'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => trim(($this->author->first_name ?? '').' '.($this->author->last_name ?? '')) ?: $this->author?->email,
            'author_email' => $this->author?->email,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
