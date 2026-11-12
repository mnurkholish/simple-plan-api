<?php

namespace App\Services;

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\User;
use App\Repositories\DesignRequestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DesignRequestService
{
    public function __construct(private readonly DesignRequestRepository $designRequests) {}

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

    /**
     * Menugaskan request desain ke Petugas TIK dan memperbarui status menjadi Ditugaskan.
     *
     * @param  array<string, mixed>  $data
     */
    public function assignTask(int|string|DesignRequest $id, array $data): DesignRequest
    {
        $designRequest = $id instanceof DesignRequest ? $id : DesignRequest::findOrFail($id);

        if ($designRequest->status !== DesignRequestStatus::Baru) {
            throw ValidationException::withMessages([
                'status' => ['Hanya request desain dengan status Baru yang dapat ditugaskan.'],
            ]);
        }

        $priorityInput = (string) ($data['prioritas'] ?? $data['priority'] ?? 'Sedang');
        $priority = ucfirst(strtolower($priorityInput));
        $officerId = (int) ($data['petugas_desain_id'] ?? $data['assigned_officer_id']);

        $designRequest->update([
            'status' => DesignRequestStatus::Ditugaskan,
            'priority' => $priority,
            'assigned_officer_id' => $officerId,
            'assigned_at' => now(),
        ]);

        return $designRequest->fresh(['requester', 'unit', 'assignedOfficer', 'drafts']);
    }

    public function getTasksByAssignee(int|string $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->designRequests->getTasksByAssignee($userId, $perPage);
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     period: array{start_date: string, end_date: string},
     *     total: int,
     *     by_status: array<string, int>,
     *     by_design_type: array<string, int>,
     *     requests: Collection<int, DesignRequest>
     * }
     */
    public function getReportSummary(string $startDate, string $endDate): array
    {
        return $this->designRequests->getReportSummary($startDate, $endDate);
    }
}
