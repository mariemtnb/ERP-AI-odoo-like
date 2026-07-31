<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Accounting journal (journal comptable). Tunisian bookkeeping splits entries
 * by journal — ventes, achats, caisse, banque, chèques, effets, échéances,
 * avances — which is what auditors and accountants expect to see.
 */
class Journal extends Model
{
    public const TYPES = [
        'sales', 'purchase', 'cash', 'bank', 'cheque',
        'commercial_paper', 'installment', 'advance', 'misc',
    ];

    /** Well-known codes the posting services look up. */
    public const SALES = 'VT';
    public const PURCHASE = 'AC';
    public const CASH = 'CA';
    public const BANK = 'BQ';
    public const CHEQUE = 'CH';
    public const COMMERCIAL_PAPER = 'EF';
    public const INSTALLMENT = 'EH';
    public const ADVANCE = 'AV';
    public const MISC = 'OD';

    protected $fillable = ['code', 'name', 'name_fr', 'type', 'is_active'];

    protected $attributes = ['name_fr' => '', 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /** Resolve a journal code to its id, or null when the journal is missing. */
    public static function idFor(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        return static::where('code', $code)->value('id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_fr' => $this->name_fr,
            'type' => $this->type,
            'is_active' => $this->is_active,
        ];
    }
}
