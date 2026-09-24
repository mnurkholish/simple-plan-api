<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketService as TicketServiceEnum;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class TicketService
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly UserRepository $users,
        private readonly SlaService $sla,
    ) {}

    /**
     * @param  array{service: string, description: string, asset_id?: int|null}  $data
     */
    public function create(array $data, User $reporter, ?UploadedFile $initialEvidence = null): Ticket
    {
        if ($reporter->unit_id === null) {
            throw ValidationException::withMessages([
                'unit_id' => ['User yang membuat tiket harus memiliki unit.'],
            ]);
        }

        $objectKey = $initialEvidence?->store('helpdesk/evidence');

        if ($initialEvidence !== null && $objectKey === false) {
            throw new RuntimeException('The initial evidence could not be stored.');
        }

        try {
            $service = TicketServiceEnum::from($data['service']);
            $year = now()->format('Y');

            $ticket = Cache::lock("ticket-number:{$service->value}:{$year}", 10)
                ->block(5, fn (): Ticket => DB::transaction(function () use (
                    $data,
                    $reporter,
                    $initialEvidence,
                    $objectKey,
                    $service,
                    $year,
                ): Ticket {
                    return $this->tickets->create([
                        'ticket_number' => $this->nextTicketNumber($service, $year),
                        'service' => $service->value,
                        'reporter_id' => $reporter->getKey(),
                        'unit_id' => $reporter->unit_id,
                        'asset_id' => $data['asset_id'] ?? null,
                        'description' => $data['description'],
                        'status' => TicketStatus::Baru,
                        'initial_evidence_object_key' => $objectKey ?: null,
                        'initial_evidence_original_name' => $initialEvidence?->getClientOriginalName(),
                        'initial_evidence_mime_type' => $initialEvidence?->getMimeType(),
                        'initial_evidence_size' => $initialEvidence?->getSize(),
                    ]);
                }));

            return $this->tickets->loadSummary($ticket);
        } catch (Throwable $exception) {
            if (is_string($objectKey)) {
                Storage::delete($objectKey);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{service?: string|null, status?: string|null, date_from?: string|null, date_to?: string|null, search?: string|null}  $filters
     */
    public function paginateVisibleTo(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->tickets->paginateVisibleTo($actor, $filters, $perPage);
    }

    public function loadDetail(Ticket $ticket): Ticket
    {
        return $this->tickets->loadDetail($ticket);
    }

    public function loadSummary(Ticket $ticket): Ticket
    {
        return $this->tickets->loadSummary($ticket);
    }

    /**
     * @param  array{quality_category_id?: int, it_tag_id?: int, custom_it_tag_text?: string|null, sarpras_category_id?: int}  $data
     */
    public function classify(Ticket $ticket, array $data, User $classifiedBy): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $classifiedBy): Ticket {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanTransitionTo($lockedTicket, TicketStatus::Diklasifikasi);

            $classifiedAt = now();

            if ($lockedTicket->service === TicketServiceEnum::Tik) {
                $lockedTicket->tikDetail()->create([
                    'quality_category_id' => $data['quality_category_id'],
                    'it_tag_id' => $data['it_tag_id'],
                    'custom_it_tag_text' => $data['custom_it_tag_text'] ?? null,
                ]);
            } else {
                $lockedTicket->sarprasDetail()->create([
                    'sarpras_category_id' => $data['sarpras_category_id'],
                ]);
            }

            $this->applyStatusTransition(
                $lockedTicket,
                TicketStatus::Diklasifikasi,
                $classifiedBy,
                notes: null,
                attributes: [
                    'classified_by_id' => $classifiedBy->getKey(),
                    'classified_at' => $classifiedAt,
                ],
            );

            return $lockedTicket->refresh();
        });
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

    /**
     * @param  array{assigned_officer_id: int, priority?: string}  $data
     */
    public function assign(Ticket $ticket, array $data, User $changedBy): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $changedBy): Ticket {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $assignedAt = now();
            $attributes = [
                'assigned_officer_id' => $data['assigned_officer_id'],
                'assigned_at' => $assignedAt,
            ];

            if ($lockedTicket->status === TicketStatus::Diklasifikasi) {
                $priority = TicketPriority::from($data['priority']);
                $attributes['priority'] = $priority->value;
                $attributes['sla_deadline'] = $this->sla->calculateDeadline($assignedAt, $priority);
            }

            $this->applyStatusTransition(
                $lockedTicket,
                TicketStatus::Ditugaskan,
                $changedBy,
                notes: null,
                attributes: $attributes,
            );

            return $lockedTicket->refresh();
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function assigneeOptions(Ticket $ticket, ?string $search = null): Collection
    {
        if (! in_array($ticket->status, [
            TicketStatus::Diklasifikasi,
            TicketStatus::Ditugaskan,
            TicketStatus::Diproses,
        ], true)) {
            throw new ConflictHttpException('Kandidat petugas hanya tersedia untuk assignment atau reassignment.');
        }

        return $this->users->getAssigneeOptions(
            $ticket->service === TicketServiceEnum::Tik ? 'petugas-tik' : 'petugas-sarpras',
            $search,
        );
    }

    /**
     * @param  array{notes: string, status: string, started_at: string, completed_at: string}  $data
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

                if ((int) $lockedTicket->assigned_officer_id !== (int) $handledBy->getKey()) {
                    throw new AuthorizationException('Hanya petugas yang sedang ditugaskan yang dapat menangani tiket.');
                }

                if (! in_array($lockedTicket->status, [TicketStatus::Ditugaskan, TicketStatus::Diproses], true)) {
                    throw new ConflictHttpException('Tiket harus berstatus ditugaskan atau diproses untuk menerima penanganan.');
                }

                $targetStatus = TicketStatus::from($data['status']);
                $completedAt = $data['completed_at'];

                $lockedTicket->handlings()->create([
                    'handled_by_id' => $handledBy->getKey(),
                    'notes' => $data['notes'],
                    'started_at' => $data['started_at'],
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

        $this->assertCanTransitionTo($ticket, $targetStatus);

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

    private function assertCanTransitionTo(Ticket $ticket, TicketStatus $targetStatus): void
    {
        if ($ticket->status->canTransitionTo($targetStatus)) {
            return;
        }

        throw new ConflictHttpException(sprintf(
            'Status tiket tidak dapat diubah dari %s menjadi %s.',
            $ticket->status->value,
            $targetStatus->value,
        ));
    }

    private function nextTicketNumber(TicketServiceEnum $service, string $year): string
    {
        $numberPrefix = "{$service->ticketNumberPrefix()}-{$year}-";
        $latestNumber = $this->tickets->latestTicketNumber($service, $numberPrefix);
        $nextSequence = $latestNumber === null
            ? 1
            : ((int) substr($latestNumber, strlen($numberPrefix))) + 1;

        if ($nextSequence > 999999) {
            throw new RuntimeException("Urutan nomor tiket {$service->value} tahun {$year} sudah habis.");
        }

        return $numberPrefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
