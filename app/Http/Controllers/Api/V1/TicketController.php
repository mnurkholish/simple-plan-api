<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketService as TicketServiceEnum;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssigneeOptionsRequest;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ClassificationOptionsRequest;
use App\Http\Requests\ClassifyTicketRequest;
use App\Http\Requests\ListTicketRequest;
use App\Http\Requests\RejectTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\AssigneeOptionResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index(ListTicketRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->validated();

        return TicketResource::collection(
            $this->service->paginateVisibleTo(
                $request->user(),
                $filters,
                $request->integer('per_page', 15),
            ),
        );
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($this->service->loadDetail($ticket));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->service->create(
            $request->validated(),
            $request->user(),
            $request->file('initial_evidence'),
        );

        return (new TicketResource($ticket))
            ->additional(['message' => 'Tiket berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function classificationOptions(ClassificationOptionsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->classificationOptions(
                $request->enum('service', TicketServiceEnum::class),
            ),
        ]);
    }

    public function classify(ClassifyTicketRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('classify', $ticket);

        $ticket = $this->service->classify(
            $ticket,
            $request->validated(),
            $request->user(),
        );

        return (new TicketResource($this->service->loadSummary($ticket)))
            ->additional(['message' => 'Tiket berhasil diklasifikasi.']);
    }

    public function reject(RejectTicketRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('reject', $ticket);

        $ticket = $this->service->reject(
            $ticket,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return (new TicketResource($this->service->loadSummary($ticket)))
            ->additional(['message' => 'Tiket berhasil ditolak.']);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('assign', $ticket);

        $validated = $request->validated();
        $isReassignment = $ticket->status === TicketStatus::Diproses;
        $ticket = $this->service->assign(
            $ticket,
            $validated,
            $request->user(),
        );

        return (new TicketResource($this->service->loadSummary($ticket)))
            ->additional(['message' => $isReassignment
                ? 'Petugas berhasil ditugaskan ulang.'
                : 'Petugas berhasil ditugaskan.']);
    }

    public function assigneeOptions(
        AssigneeOptionsRequest $request,
        Ticket $ticket,
    ): AnonymousResourceCollection {
        Gate::authorize('assign', $ticket);

        return AssigneeOptionResource::collection(
            $this->service->assigneeOptions(
                $ticket,
                $request->string('search')->toString() ?: null,
            ),
        );
    }
    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/de-escalate",
     *     operationId="deEscalateTicket",
     *     tags={"Helpdesk"},
     *     summary="Menarik kembali tiket dari status eskalasi (De-escalate)",
     *     description="Hanya petugas yang ditugaskan yang dapat melakukan ini.",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="ticket",
     *         in="path",
     *         required=true,
     *         description="ID dari tiket",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="Accept",
     *         in="header",
     *         required=true,
     *         description="Wajib diisi application/json agar tidak ter-redirect ke rute login",
     *         @OA\Schema(type="string", default="application/json")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil ditarik"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - Token tidak valid atau tidak dikirim"
     *     )
     * )
     */
    public function deEscalate(Ticket $ticket)
    {
        // Otorisasi: Pastikan user yang login adalah teknisi yang di-assign
        if (auth()->id() !== $ticket->assigned_officer_id) {
            return response()->json(['message' => 'User does not have the right permissions.'], 403);
        }

        // Validasi: Hanya tiket berstatus eskalasi yang bisa ditarik kembali
        if ($ticket->status->value !== 'eskalasi') {
            return response()->json(['message' => 'Hanya tiket berstatus eskalasi yang dapat diproses kembali.'], 409);
        }

        // Update status kembali ke 'diproses'
        $ticket->update(['status' => 'diproses']);

        // Catat ke riwayat status
        $ticket->statusHistories()->create([
            'from_status' => 'eskalasi',
            'to_status' => 'diproses',
            'changed_by_id' => auth()->id(),
            'notes' => 'Eskalasi selesai, tiket diproses kembali oleh teknisi.'
        ]);

        return response()->json([
            'message' => 'Tiket berhasil ditarik kembali dan sedang diproses.',
            'data' => $ticket
        ], 200);
    }
}
