<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marketing campaigns. The audience is the active customers who have the
 * contact detail the channel needs (email or phone). Delivery is delegated to a
 * pluggable sender that is stubbed here — no email/SMS provider is wired up, so
 * `deliver()` only logs. Swap that one method for a real provider to go live.
 */
class MarketingService
{
    public static function create(string $name, string $channel, string $subject, string $body, User $user): Campaign
    {
        if (! in_array($channel, [Campaign::CHANNEL_EMAIL, Campaign::CHANNEL_SMS], true)) {
            throw new InvalidTransition("Unknown channel: {$channel}.");
        }

        return Campaign::create([
            'name' => $name,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'created_by' => $user->id,
        ]);
    }

    /** Active customers reachable on this campaign's channel. */
    public static function audience(Campaign $campaign)
    {
        $field = $campaign->channel === Campaign::CHANNEL_EMAIL ? 'email' : 'phone';

        return Customer::where('is_active', true)
            ->whereNotNull($field)->where($field, '!=', '')
            ->get(['id', 'name', $field])
            ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name, 'contact' => $c->{$field}]);
    }

    /**
     * "Send" the campaign: resolve the audience, record a recipient per
     * customer and hand each to the (stubbed) sender. Returns the count sent.
     */
    public static function send(Campaign $campaign): int
    {
        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            throw new InvalidTransition('This campaign has already been sent.');
        }

        $audience = self::audience($campaign);
        if ($audience->isEmpty()) {
            throw new InvalidTransition('No customers are reachable on this channel yet.');
        }

        return DB::transaction(function () use ($campaign, $audience) {
            foreach ($audience as $person) {
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'customer_id' => $person['id'],
                    'contact' => $person['contact'],
                    'status' => 'sent',
                ]);
                self::deliver($campaign, $person['contact']);
            }
            $campaign->update([
                'status' => Campaign::STATUS_SENT,
                'sent_count' => $audience->count(),
                'sent_at' => now(),
            ]);

            return $audience->count();
        });
    }

    /**
     * The single integration point. No provider is configured, so this only
     * records intent. Replace with a mail/SMS gateway call to go live.
     */
    private static function deliver(Campaign $campaign, string $contact): void
    {
        Log::info('marketing.deliver (stub)', [
            'campaign' => $campaign->id,
            'channel' => $campaign->channel,
            'to' => $contact,
        ]);
    }
}
