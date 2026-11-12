<?php

namespace App\Services;

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\User;
use App\Repositories\DesignRequestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class DesignRequestService
{
    public function __construct(private readonly DesignRequestRepository $designRequests) {}

    /** @param array<string, mixed> $filters */
    public function paginateVisibleTo(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->designRequests->paginateVisibleTo($actor, $filters['scope'] ?? 'mine', $filters, $perPage);
    }

    public function loadDetail(DesignRequest $designRequest): DesignRequest
    {
        return $this->designRequests->loadDetail($designRequest);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int, by_status: array<string, int>, by_design_type: list<array{design_type: string, total: int}>, requests: LengthAwarePaginator}
     */
    public function report(array $filters, int $perPage): array
    {
        $statusCounts = $this->designRequests->reportStatusCounts($filters);
        $byStatus = collect(DesignRequestStatus::cases())
            ->mapWithKeys(fn (DesignRequestStatus $status): array => [$status->value => 0])
            ->all();

        foreach ($statusCounts as $status => $count) {
            if (array_key_exists($status, $byStatus)) {
                $byStatus[$status] = $count;
            }
        }

        return [
            'total' => array_sum($statusCounts),
            'by_status' => $byStatus,
            'by_design_type' => $this->designRequests->reportDesignTypeCounts($filters),
            'requests' => $this->designRequests->paginateReportRequests($filters, $perPage),
        ];
    }

    /** @param array{priority: string, assigned_officer_id: int} $data */
    public function assign(DesignRequest $designRequest, array $data): DesignRequest
    {
        return DB::transaction(function () use ($designRequest, $data): DesignRequest {
            $lockedRequest = $this->designRequests->findForUpdate((int) $designRequest->getKey());

            if ($lockedRequest->status !== DesignRequestStatus::Baru) {
                throw new ConflictHttpException('Request desain hanya dapat ditugaskan saat berstatus baru.');
            }

            return $this->designRequests->update($lockedRequest, [
                'priority' => $data['priority'],
                'assigned_officer_id' => $data['assigned_officer_id'],
                'assigned_at' => now(),
                'status' => DesignRequestStatus::Ditugaskan,
            ]);
        });
    }

    public function reject(DesignRequest $designRequest, string $rejectionNote): DesignRequest
    {
        return DB::transaction(function () use ($designRequest, $rejectionNote): DesignRequest {
            $lockedRequest = $this->designRequests->findForUpdate((int) $designRequest->getKey());

            if ($lockedRequest->status !== DesignRequestStatus::Baru) {
                throw new ConflictHttpException('Request desain hanya dapat ditolak saat berstatus baru.');
            }

            return $this->designRequests->update($lockedRequest, [
                'rejection_note' => $rejectionNote,
                'status' => DesignRequestStatus::Ditolak,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $requester, ?UploadedFile $referenceFile = null): DesignRequest
    {
        if ($requester->unit_id === null) {
            throw ValidationException::withMessages([
                'unit_id' => ['User yang membuat request desain harus memiliki unit.'],
            ]);
        }

        $objectKey = $referenceFile?->store('design-requests/references');

        if ($referenceFile !== null && $objectKey === false) {
            throw new RuntimeException('The reference file could not be stored.');
        }

        try {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                try {
                    $designRequest = $this->designRequests->create([
                        'request_number' => 'DGR-'.now()->format('Y').'-'.sprintf('%06d', random_int(0, 999999)),
                        'title' => $data['title'],
                        'design_type' => $data['design_type'],
                        'description' => $data['description'],
                        'needed_at' => $data['needed_at'],
                        'requester_id' => $requester->getKey(),
                        'unit_id' => $requester->unit_id,
                        'status' => DesignRequestStatus::Baru,
                        'reference_file_object_key' => $objectKey ?: null,
                        'reference_file_original_name' => $referenceFile?->getClientOriginalName(),
                        'reference_file_mime_type' => $referenceFile?->getMimeType(),
                        'reference_file_size' => $referenceFile?->getSize(),
                    ]);

                    return $this->designRequests->loadSummary($designRequest);
                } catch (UniqueConstraintViolationException $exception) {
                    if ($attempt === 9) {
                        throw $exception;
                    }
                }
            }

            throw new RuntimeException('Could not generate a unique design request number.');
        } catch (Throwable $exception) {
            if (is_string($objectKey)) {
                Storage::delete($objectKey);
            }

            throw $exception;
        }
    }
}
