<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TicketService
{
    /**
     * @param  array{service: string, unit_id: int, category?: string|null, description: string}  $data
     */
    public function create(array $data, User $reporter, ?UploadedFile $initialEvidence = null): Ticket
    {
        $objectKey = $initialEvidence?->store('helpdesk/evidence');

        if ($initialEvidence !== null && $objectKey === false) {
            throw new RuntimeException('The initial evidence could not be stored.');
        }

        try {
            return DB::transaction(function () use ($data, $reporter, $initialEvidence, $objectKey): Ticket {
                $ticket = Ticket::create([
                    'ticket_number' => 'pending-'.Str::uuid(),
                    'service' => $data['service'],
                    'reporter_id' => $reporter->getKey(),
                    'unit_id' => $data['unit_id'],
                    'category' => $data['category'] ?? null,
                    'description' => $data['description'],
                    'status' => TicketStatus::Baru,
                    'initial_evidence_object_key' => $objectKey ?: null,
                    'initial_evidence_original_name' => $initialEvidence?->getClientOriginalName(),
                    'initial_evidence_mime_type' => $initialEvidence?->getMimeType(),
                    'initial_evidence_size' => $initialEvidence?->getSize(),
                ]);

                $ticket->update([
                    'ticket_number' => sprintf(
                        '%s-%s-%04d',
                        strtoupper($ticket->service->value),
                        $ticket->created_at->format('Y'),
                        $ticket->getKey(),
                    ),
                ]);

                return $ticket;
            });
        } catch (Throwable $exception) {
            if (is_string($objectKey)) {
                Storage::delete($objectKey);
            }

            throw $exception;
        }
    }
}
