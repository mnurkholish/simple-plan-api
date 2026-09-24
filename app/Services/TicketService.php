<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketService as TicketServiceEnum;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class TicketService
{
    /**
     * @param  array{service: string, unit_id: int, quality_category_id?: int|null, it_tag_id?: int|null, custom_it_tag_text?: string|null, sarpras_category_id?: int|null, description: string}  $data
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

                if (
                    $data['service'] === TicketServiceEnum::Tik->value
                    && isset($data['quality_category_id'], $data['it_tag_id'])
                ) {
                    $ticket->tikDetail()->create([
                        'quality_category_id' => $data['quality_category_id'],
                        'it_tag_id' => $data['it_tag_id'],
                        'custom_it_tag_text' => $data['custom_it_tag_text'] ?? null,
                    ]);
                }

                if (
                    $data['service'] === TicketServiceEnum::Sarpras->value
                    && isset($data['sarpras_category_id'])
                ) {
                    $ticket->sarprasDetail()->create([
                        'sarpras_category_id' => $data['sarpras_category_id'],
                    ]);
                }

                return $ticket;
            });
        } catch (Throwable $exception) {
            if (is_string($objectKey)) {
                Storage::delete($objectKey);
            }

            throw $exception;
        }
    }

    public function verify(Ticket $ticket, User $changedBy): Ticket
    {
        return $this->transitionStatus(
            $ticket,
            TicketStatus::Diklasifikasi,
            $changedBy,
        );
    }

    public function reject(Ticket $ticket, string $reason, User $changedBy): Ticket
    {
        return $this->transitionStatus(
            $ticket,
            TicketStatus::Ditolak,
            $changedBy,
            $reason,
        );
    }

    public function assign(
        Ticket $ticket,
        TicketPriority $priority,
        int $officerId,
        User $changedBy,
    ): Ticket {
        return $this->transitionStatusWithAttributes(
            $ticket,
            TicketStatus::Ditugaskan,
            $changedBy,
            attributes: [
                'priority' => $priority->value,
                'assigned_officer_id' => $officerId,
                'assigned_at' => now(),
            ],
        );
    }

    /**
     * @param  array{notes: string, status: string, started_at?: string|null, completed_at?: string|null}  $data
     */
    public function addHandling(
        Ticket $ticket,
        array $data,
        User $handledBy,
        ?UploadedFile $resultPhoto = null,
    ): Ticket {
        $objectKey = $resultPhoto?->store('helpdesk/handling-results');

        if ($resultPhoto !== null && $objectKey === false) {
            throw new RuntimeException('The result photo could not be stored.');
        }

        try {
            return DB::transaction(function () use ($ticket, $data, $handledBy, $resultPhoto, $objectKey): Ticket {
                $lockedTicket = Ticket::query()
                    ->whereKey($ticket->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($lockedTicket->status, [TicketStatus::Ditugaskan, TicketStatus::Diproses], true)) {
                    throw new ConflictHttpException('Tiket harus berstatus ditugaskan atau diproses untuk menerima penanganan.');
                }

                $targetStatus = TicketStatus::from($data['status']);
                $completedAt = $data['completed_at'] ?? null;

                $lockedTicket->handlings()->create([
                    'handled_by_id' => $handledBy->getKey(),
                    'notes' => $data['notes'],
                    'started_at' => $data['started_at'] ?? null,
                    'completed_at' => $completedAt,
                    'result_photo_object_key' => $objectKey ?: null,
                    'result_photo_original_name' => $resultPhoto?->getClientOriginalName(),
                    'result_photo_mime_type' => $resultPhoto?->getMimeType(),
                    'result_photo_size' => $resultPhoto?->getSize(),
                ]);

                $attributes = [];

                if ($targetStatus === TicketStatus::Terselesaikan) {
                    $attributes['completed_at'] = $completedAt;
                }

                $this->applyStatusTransition(
                    $lockedTicket,
                    $targetStatus,
                    $handledBy,
                    $data['notes'],
                    $attributes,
                );

                return $lockedTicket->refresh();
            });
        } catch (Throwable $exception) {
            if (is_string($objectKey)) {
                Storage::delete($objectKey);
            }

            throw $exception;
        }
    }

    public function transitionStatus(
        Ticket $ticket,
        TicketStatus $targetStatus,
        ?User $changedBy = null,
        ?string $notes = null,
    ): Ticket {
        return $this->transitionStatusWithAttributes(
            $ticket,
            $targetStatus,
            $changedBy,
            $notes,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transitionStatusWithAttributes(
        Ticket $ticket,
        TicketStatus $targetStatus,
        ?User $changedBy,
        ?string $notes = null,
        array $attributes = [],
    ): Ticket {
        return DB::transaction(function () use ($ticket, $targetStatus, $changedBy, $notes, $attributes): Ticket {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyStatusTransition(
                $lockedTicket,
                $targetStatus,
                $changedBy,
                $notes,
                $attributes,
            );

            return $lockedTicket->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyStatusTransition(
        Ticket $ticket,
        TicketStatus $targetStatus,
        ?User $changedBy,
        ?string $notes,
        array $attributes = [],
    ): bool {
        $currentStatus = $ticket->status;

        if ($currentStatus === $targetStatus) {
            if ($attributes !== []) {
                $ticket->update($attributes);
            }

            return false;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw new ConflictHttpException(sprintf(
                'Status tiket tidak dapat diubah dari %s menjadi %s.',
                $currentStatus->value,
                $targetStatus->value,
            ));
        }

        $ticket->update([
            ...$attributes,
            'status' => $targetStatus->value,
        ]);

        $ticket->statusHistories()->create([
            'from_status' => $currentStatus->value,
            'to_status' => $targetStatus->value,
            'changed_by_id' => $changedBy?->getKey(),
            'notes' => $notes,
        ]);

        return true;
    }
}
