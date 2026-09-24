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

    public function verify(Ticket $ticket): Ticket
    {
        return $this->updateWhenStatus(
            $ticket,
            TicketStatus::Baru,
            ['status' => TicketStatus::Terverifikasi->value],
            'Hanya tiket berstatus baru yang dapat diverifikasi.',
        );
    }

    public function reject(Ticket $ticket, string $reason, User $changedBy): Ticket
    {
        return DB::transaction(function () use ($ticket, $reason, $changedBy): Ticket {
            $updatedTicket = $this->updateWhenStatus(
                $ticket,
                TicketStatus::Baru,
                ['status' => TicketStatus::Ditolak->value],
                'Hanya tiket berstatus baru yang dapat ditolak.',
            );

            $updatedTicket->statusHistories()->create([
                'from_status' => TicketStatus::Baru->value,
                'to_status' => TicketStatus::Ditolak->value,
                'changed_by_id' => $changedBy->getKey(),
                'notes' => $reason,
            ]);

            return $updatedTicket;
        });
    }

    public function assign(Ticket $ticket, TicketPriority $priority, int $officerId): Ticket
    {
        return $this->updateWhenStatus(
            $ticket,
            TicketStatus::Terverifikasi,
            [
                'priority' => $priority->value,
                'assigned_officer_id' => $officerId,
            ],
            'Hanya tiket berstatus terverifikasi yang dapat diberi prioritas dan petugas.',
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

                if ($lockedTicket->status !== TicketStatus::Diproses) {
                    throw new ConflictHttpException('Hanya tiket berstatus diproses yang dapat diperbarui penanganannya.');
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

                $attributes = ['status' => $targetStatus->value];

                if ($targetStatus === TicketStatus::Selesai) {
                    $attributes['completed_at'] = $completedAt;
                }

                $lockedTicket->update($attributes);

                return $lockedTicket;
            });
        } catch (Throwable $exception) {
            if (is_string($objectKey)) {
                Storage::delete($objectKey);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateWhenStatus(
        Ticket $ticket,
        TicketStatus $requiredStatus,
        array $attributes,
        string $conflictMessage,
    ): Ticket {
        $updatedRows = Ticket::query()
            ->whereKey($ticket->getKey())
            ->where('status', $requiredStatus->value)
            ->update($attributes);

        if ($updatedRows === 0) {
            throw new ConflictHttpException($conflictMessage);
        }

        return $ticket->refresh();
    }
}
