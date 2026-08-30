<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use Auditable;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** Allowed forward/back transitions. */
    public const TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_IN_PROGRESS, self::STATUS_CLOSED],
        self::STATUS_IN_PROGRESS => [self::STATUS_RESOLVED, self::STATUS_OPEN, self::STATUS_CLOSED],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED, self::STATUS_IN_PROGRESS],
        self::STATUS_CLOSED => [],
    ];

    protected $fillable = [
        'number', 'subject', 'customer_id', 'priority', 'status', 'assigned_to', 'created_by',
    ];

    protected $attributes = ['priority' => 'normal', 'status' => self::STATUS_OPEN];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'subject' => $this->subject,
            'customer' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assignee_email' => $this->assignee?->email,
            'created_by_email' => $this->creator?->email,
            'messages_count' => $this->messages()->count(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
