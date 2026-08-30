<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Helpdesk tickets: creation with an opening message, a message thread, staff
 * assignment, and a guarded status lifecycle.
 */
class HelpdeskService
{
    public static function create(
        string $subject,
        ?int $customerId,
        string $priority,
        User $user,
        ?string $message,
    ): Ticket {
        if (! in_array($priority, Ticket::PRIORITIES, true)) {
            throw new InvalidTransition("Unknown priority: {$priority}.");
        }

        return DB::transaction(function () use ($subject, $customerId, $priority, $user, $message) {
            $ticket = Ticket::create([
                'number' => DocumentService::nextNumber('TIC', Ticket::class),
                'subject' => $subject,
                'customer_id' => $customerId,
                'priority' => $priority,
                'created_by' => $user->id,
            ]);
            if ($message !== null && trim($message) !== '') {
                TicketMessage::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'body' => $message]);
            }

            return $ticket;
        });
    }

    public static function addMessage(Ticket $ticket, User $user, string $body): TicketMessage
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            throw new InvalidTransition('Cannot post to a closed ticket.');
        }
        if (trim($body) === '') {
            throw new InvalidTransition('Message cannot be empty.');
        }
        $ticket->touch(); // bump updated_at so it sorts to the top

        return TicketMessage::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'body' => $body]);
    }

    public static function assign(Ticket $ticket, ?int $userId): Ticket
    {
        $ticket->update(['assigned_to' => $userId]);

        return $ticket;
    }

    public static function transition(Ticket $ticket, string $to): Ticket
    {
        $allowed = Ticket::TRANSITIONS[$ticket->status] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new InvalidTransition("Cannot move a {$ticket->status} ticket to {$to}.");
        }
        $ticket->update(['status' => $to]);

        return $ticket;
    }
}
