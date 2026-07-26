<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cheque or effet de commerce (traite / kembya).
 *
 * Both share one table because their lifecycle is identical — received or
 * issued, deposited for collection, cleared or bounced. `kind` selects the
 * vocabulary shown to the user and the account-mapping keys used when posting
 * (see InstrumentService::accountKey).
 */
class PaymentInstrument extends Model
{
    use Auditable;

    public const KIND_CHEQUE = 'cheque';
    public const KIND_TRAITE = 'traite';
    public const KINDS = [self::KIND_CHEQUE, self::KIND_TRAITE];

    public const DIRECTION_IN = 'incoming';
    public const DIRECTION_OUT = 'outgoing';
    public const DIRECTIONS = [self::DIRECTION_IN, self::DIRECTION_OUT];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_DEPOSITED = 'deposited';
    public const STATUS_PENDING = 'pending_clearance';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SETTLED = 'settled';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_RECEIVED,
        self::STATUS_DEPOSITED, self::STATUS_PENDING, self::STATUS_CLEARED,
        self::STATUS_BOUNCED, self::STATUS_CANCELLED, self::STATUS_SETTLED,
    ];

    /** Statuses where the instrument still represents money we expect to move. */
    public const OPEN_STATUSES = [
        self::STATUS_ISSUED, self::STATUS_RECEIVED,
        self::STATUS_DEPOSITED, self::STATUS_PENDING,
    ];

    protected $fillable = [
        'number', 'kind', 'direction', 'instrument_reference', 'amount',
        'issue_date', 'due_date', 'place_of_issue',
        'customer_id', 'supplier_id', 'counterparty_name', 'status',
        'bank_account_id', 'drawee_bank_id', 'drawee_rib',
        'reference_type', 'reference_id',
        'deposited_at', 'cleared_at', 'bounced_at', 'bounce_reason', 'bank_fees',
        'notes', 'created_by',
    ];

    protected $attributes = [
        'instrument_reference' => '', 'place_of_issue' => '', 'counterparty_name' => '',
        'status' => self::STATUS_DRAFT, 'drawee_rib' => '', 'reference_type' => '',
        'bounce_reason' => '', 'bank_fees' => 0, 'notes' => '',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'deposited_at' => 'date:Y-m-d',
            'cleared_at' => 'date:Y-m-d',
            'bounced_at' => 'date:Y-m-d',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function draweeBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'drawee_bank_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InstrumentEvent::class, 'instrument_id')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'owner_id')
            ->where('owner_type', Attachment::OWNER_INSTRUMENT);
    }

    public function isIncoming(): bool
    {
        return $this->direction === self::DIRECTION_IN;
    }

    /** Past its due date and still not cleared. */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && in_array($this->status, self::OPEN_STATUSES, true)
            && $this->due_date->isPast();
    }

    public function counterpartyLabel(): string
    {
        return $this->customer?->name
            ?? $this->supplier?->name
            ?? $this->counterparty_name;
    }

    public function toApi(bool $withEvents = false): array
    {
        $data = [
            'id' => $this->id,
            'number' => $this->number,
            'kind' => $this->kind,
            'direction' => $this->direction,
            'instrument_reference' => $this->instrument_reference,
            'amount' => (string) $this->amount,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'place_of_issue' => $this->place_of_issue,
            'customer_id' => $this->customer_id,
            'supplier_id' => $this->supplier_id,
            'counterparty_name' => $this->counterpartyLabel(),
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'bank_account_id' => $this->bank_account_id,
            'bank_account_label' => $this->bankAccount?->label,
            'drawee_bank_id' => $this->drawee_bank_id,
            'drawee_bank_name' => $this->draweeBank?->short_name,
            'drawee_rib' => $this->drawee_rib,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'deposited_at' => $this->deposited_at?->format('Y-m-d'),
            'cleared_at' => $this->cleared_at?->format('Y-m-d'),
            'bounced_at' => $this->bounced_at?->format('Y-m-d'),
            'bounce_reason' => $this->bounce_reason,
            'bank_fees' => (string) $this->bank_fees,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
        if ($withEvents) {
            $data['events'] = $this->events->map(fn (InstrumentEvent $e) => $e->toApi())->values()->all();
            $data['attachments'] = $this->attachments->map(fn (Attachment $a) => $a->toApi())->values()->all();
        }

        return $data;
    }
}
