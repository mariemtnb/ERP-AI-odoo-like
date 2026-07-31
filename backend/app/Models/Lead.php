<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use Auditable;

    public const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];

    protected $fillable = [
        'name', 'company', 'email', 'phone', 'source', 'status', 'notes',
        'assigned_to', 'customer_id', 'created_by',
    ];

    protected $attributes = [
        'company' => '', 'email' => '', 'phone' => '', 'source' => '',
        'status' => 'new', 'notes' => '',
    ];

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function toApi(bool $withActivities = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'status' => $this->status,
            'notes' => $this->notes,
            'assigned_to' => $this->assigned_to,
            'assigned_to_email' => $this->assignee?->email,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
        if ($withActivities) {
            $data['activities'] = $this->activities->map(fn ($a) => $a->toApi())->values()->all();
        }

        return $data;
    }
}
