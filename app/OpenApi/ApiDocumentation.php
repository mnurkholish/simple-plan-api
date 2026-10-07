<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Simple Plan API",
 *     version="1.0.0",
 *     description="REST API documentation for Simple Plan backend. Protected endpoints use Laravel Sanctum Bearer Token."
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="Current API server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Sanctum"
 * )
 *
 * @OA\Tag(name="Auth", description="Authentication and account recovery")
 * @OA\Tag(name="SSO", description="IAM/SSO bridge")
 * @OA\Tag(name="Dashboard", description="Dashboard data and live stats")
 * @OA\Tag(name="Profile", description="Authenticated user profile")
 * @OA\Tag(name="Users", description="User management")
 * @OA\Tag(name="Roles", description="Role and permission management")
 * @OA\Tag(name="Units", description="Unit management")
 * @OA\Tag(name="Backups", description="Database backup management")
 * @OA\Tag(name="Example Live", description="Market proxy and background task demo")
 * @OA\Tag(name="Impersonation", description="Sanctum token impersonation")
 * @OA\Tag(name="Notifications", description="Notification and activity summary")
 * @OA\Tag(name="Assets", description="Asset lookup")
 * @OA\Tag(name="Helpdesk", description="Helpdesk TIK and Sarpras tickets")
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=2),
 *     @OA\Property(property="name", type="string", example="Dr. Rani"),
 *     @OA\Property(property="email", type="string", nullable=true, example="rani@hospital.com"),
 *     @OA\Property(property="nip", type="string", nullable=true, example="19801234567754"),
 *     @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatar.jpg"),
 *     @OA\Property(property="email_verified_at", type="string", nullable=true, format="date-time", example="2026-09-24T10:00:00Z"),
 *     @OA\Property(property="jabatan", type="string", nullable=true, example="Dokter Kandungan"),
 *     @OA\Property(property="no_hp", type="string", nullable=true, example="081234567890"),
 *     @OA\Property(property="status_user", type="string", example="Aktif"),
 *     @OA\Property(property="alasan_nonaktif", type="string", nullable=true, example="Cuti melahirkan"),
 *     @OA\Property(property="role", type="string", example="user"),
 *     @OA\Property(property="unit", ref="#/components/schemas/Unit"),
 *     @OA\Property(property="created_at", type="string", nullable=true, format="date-time", example="2026-09-01T10:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="UserSearchUnit",
 *     type="object",
 *     required={"id","unit_name"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="unit_name", type="string", example="ICU")
 * )
 *
 * @OA\Schema(
 *     schema="UserSearchResult",
 *     type="object",
 *     required={"id","name","unit"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Dr. Rani"),
 *     @OA\Property(property="unit", ref="#/components/schemas/UserSearchUnit", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Role",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="super-admin"),
 *     @OA\Property(property="guard_name", type="string", example="web"),
 *     @OA\Property(property="permissions", type="array", @OA\Items(ref="#/components/schemas/Permission")),
 *     @OA\Property(property="created_at", type="string", nullable=true, format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Permission",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="users-access")
 * )
 *
 * @OA\Schema(
 *     schema="Unit",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="unit_name", type="string", example="ICU"),
 *     @OA\Property(property="slug", type="string", nullable=true, example="icu"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="users_count", type="integer", nullable=true),
 *     @OA\Property(property="users", type="array", @OA\Items(ref="#/components/schemas/User")),
 *     @OA\Property(property="created_at", type="string", nullable=true, format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="UnitSearchResult",
 *     type="object",
 *     required={"id","unit_name"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="unit_name", type="string", example="ICU")
 * )
 *
 * @OA\Schema(
 *     schema="Notification",
 *     type="object",
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Login berhasil"),
 *     @OA\Property(property="subtitle", type="string"),
 *     @OA\Property(property="icon", type="string", example="IconLogin"),
 *     @OA\Property(property="color", type="string", example="emerald"),
 *     @OA\Property(property="time", type="string", nullable=true),
 *     @OA\Property(property="read_at", type="string", nullable=true, format="date-time"),
 *     @OA\Property(property="created_at", type="string", nullable=true, format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(property="errors", type="object", @OA\AdditionalProperties(type="array", @OA\Items(type="string")))
 * )
 *
 * @OA\Schema(
 *     schema="MessageResponse",
 *     type="object",
 *
 *     @OA\Property(property="message", type="string", example="Operasi berhasil.")
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *
 *     @OA\Property(property="current_page", type="integer"),
 *     @OA\Property(property="from", type="integer", nullable=true),
 *     @OA\Property(property="last_page", type="integer"),
 *     @OA\Property(property="path", type="string"),
 *     @OA\Property(property="per_page", type="integer"),
 *     @OA\Property(property="to", type="integer", nullable=true),
 *     @OA\Property(property="total", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="TicketReporterSummary",
 *     type="object",
 *     required={"id","name"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Rina Pelapor")
 * )
 *
 * @OA\Schema(
 *     schema="TicketUnitSummary",
 *     type="object",
 *     required={"id","name"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Unit Rawat Jalan")
 * )
 *
 * @OA\Schema(
 *     schema="TicketOfficerSummary",
 *     type="object",
 *     required={"id","name"},
 *
 *     @OA\Property(property="id", type="integer", example=2),
 *     @OA\Property(property="name", type="string", example="Petugas Terpilih")
 * )
 *
 * @OA\Schema(
 *     schema="TicketAssetSummary",
 *     type="object",
 *     required={"id","asset_number","name","brand","location","status"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="asset_number", type="string", example="AST-0001"),
 *     @OA\Property(property="name", type="string", example="Komputer Poli"),
 *     @OA\Property(property="brand", type="string", nullable=true, example="Dell"),
 *     @OA\Property(property="location", type="string", nullable=true, example="Poli Umum"),
 *     @OA\Property(property="status", type="string", example="active")
 * )
 *
 * @OA\Schema(
 *     schema="TicketStatusHistory",
 *     type="object",
 *     required={"id","from_status","to_status","changed_by","notes","created_at"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="from_status", type="string", enum={"baru","diklasifikasi","ditugaskan","diproses","eskalasi","terselesaikan","terverifikasi","ditutup","ditolak"}, nullable=true, example="baru"),
 *     @OA\Property(property="to_status", type="string", enum={"baru","diklasifikasi","ditugaskan","diproses","eskalasi","terselesaikan","terverifikasi","ditutup","ditolak"}, example="diklasifikasi"),
 *     @OA\Property(property="changed_by", ref="#/components/schemas/TicketReporterSummary", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="TicketFileMetadata",
 *     type="object",
 *     required={"original_name","mime_type","size"},
 *
 *     @OA\Property(property="original_name", type="string", example="kerusakan-komputer.jpg"),
 *     @OA\Property(property="mime_type", type="string", example="image/jpeg"),
 *     @OA\Property(property="size", type="integer", example=245760),
 *     @OA\Property(property="url", type="string", format="uri", description="Temporary URL included only in the ticket detail/show response and generated at runtime from the object key on the configured filesystem disk.", example="https://simple-plan.internal/storage/local/helpdesk/evidence/example.jpg?expires=1790985600&signature=example")
 * )
 *
 * @OA\Schema(
 *     schema="TicketHandling",
 *     type="object",
 *     required={"id","notes","status","started_at","completed_at","handled_by","result_photo","created_at"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="notes", type="string", example="Kabel daya dikencangkan dan perangkat diuji."),
 *     @OA\Property(property="status", type="string", enum={"diproses","terselesaikan"}, description="Current ticket status, not a ticket_handlings column.", example="diproses"),
 *     @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="handled_by", ref="#/components/schemas/TicketOfficerSummary"),
 *     @OA\Property(property="result_photo", ref="#/components/schemas/TicketFileMetadata", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="TicketNamedReference",
 *     type="object",
 *     required={"id","name"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Perangkat Komputer")
 * )
 *
 * @OA\Schema(
 *     schema="ClassificationOptionsResponse",
 *     type="object",
 *     required={"data"},
 *
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         required={"service","quality_categories","it_tags","sarpras_categories"},
 *         @OA\Property(property="service", type="string", enum={"tik","sarpras"}, example="tik"),
 *         @OA\Property(property="quality_categories", type="array", @OA\Items(ref="#/components/schemas/TicketNamedReference")),
 *         @OA\Property(property="it_tags", type="array", @OA\Items(ref="#/components/schemas/TicketNamedReference")),
 *         @OA\Property(property="sarpras_categories", type="array", @OA\Items(ref="#/components/schemas/TicketNamedReference"))
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="TicketTikDetail",
 *     type="object",
 *     required={"quality_category","it_tag","custom_it_tag_text"},
 *
 *     @OA\Property(property="quality_category", ref="#/components/schemas/TicketNamedReference"),
 *     @OA\Property(property="it_tag", ref="#/components/schemas/TicketNamedReference"),
 *     @OA\Property(property="custom_it_tag_text", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="TicketSarprasDetail",
 *     type="object",
 *     required={"sarpras_category"},
 *
 *     @OA\Property(property="sarpras_category", ref="#/components/schemas/TicketNamedReference")
 * )
 *
 * @OA\Schema(
 *     schema="TicketRead",
 *     type="object",
 *     required={"id","ticket_number","service","category","tik_detail","sarpras_detail","description","status","rejection_reason","priority","asset","reporter","unit","assigned_officer","classified_by","initial_evidence","classified_at","assigned_at","sla_started_at","sla_deadline","completed_at","closed_at","created_at","updated_at"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="ticket_number", type="string", pattern="^(TIK|SPR)-[0-9]{4}-[0-9]{6}$", example="TIK-2026-000001"),
 *     @OA\Property(property="service", type="string", enum={"tik","sarpras"}, example="tik"),
 *     @OA\Property(property="category", type="string", nullable=true, description="Compatibility label derived from the ticket detail relation.", example="Perangkat Komputer"),
 *     @OA\Property(property="tik_detail", ref="#/components/schemas/TicketTikDetail", nullable=true),
 *     @OA\Property(property="sarpras_detail", ref="#/components/schemas/TicketSarprasDetail", nullable=true),
 *     @OA\Property(property="description", type="string", example="Komputer tidak dapat menyala."),
 *     @OA\Property(property="status", type="string", enum={"baru","diklasifikasi","diproses","eskalasi","terselesaikan","ditutup","ditolak"}, example="baru"),
 *     @OA\Property(property="rejection_reason", type="string", nullable=true, description="Compatibility field derived from the latest rejected status history notes.", example="Informasi kerusakan tidak sesuai."),
 *     @OA\Property(property="priority", type="string", enum={"critical","high","medium","low"}, nullable=true, example="high"),
 *     @OA\Property(property="asset", ref="#/components/schemas/TicketAssetSummary", nullable=true),
 *     @OA\Property(property="reporter", ref="#/components/schemas/TicketReporterSummary"),
 *     @OA\Property(property="unit", ref="#/components/schemas/TicketUnitSummary"),
 *     @OA\Property(property="assigned_officer", ref="#/components/schemas/TicketOfficerSummary", nullable=true),
 *     @OA\Property(property="classified_by", ref="#/components/schemas/TicketReporterSummary", nullable=true),
 *     @OA\Property(property="initial_evidence", ref="#/components/schemas/TicketFileMetadata", nullable=true),
 *     @OA\Property(property="classified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="assigned_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="sla_started_at", type="string", format="date-time", nullable=true, description="Time the ticket first entered diproses and its SLA started."),
 *     @OA\Property(property="sla_deadline", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="closed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="handlings", type="array", @OA\Items(ref="#/components/schemas/TicketHandling")),
 *     @OA\Property(property="status_histories", type="array", @OA\Items(ref="#/components/schemas/TicketStatusHistory")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="CreateTicketRequest",
 *     type="object",
 *     required={"service","description"},
 *
 *     @OA\Property(property="service", type="string", enum={"tik","sarpras"}, example="tik"),
 *     @OA\Property(property="asset_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="description", type="string", example="Komputer tidak dapat menyala."),
 *     @OA\Property(property="initial_evidence", type="string", format="binary", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="TicketCreateResponse",
 *     type="object",
 *     required={"message","data"},
 *
 *     @OA\Property(property="message", type="string", example="Tiket berhasil dibuat."),
 *     @OA\Property(property="data", ref="#/components/schemas/TicketRead")
 * )
 *
 * @OA\Schema(
 *     schema="RejectTicketRequest",
 *     type="object",
 *     required={"reason"},
 *
 *     @OA\Property(property="reason", type="string", example="Informasi kerusakan tidak sesuai.")
 * )
 *
 * @OA\Schema(
 *     schema="ClassifyTicketRequest",
 *     description="Request shape is selected from the ticket service in the path.",
 *     oneOf={
 *         @OA\Schema(ref="#/components/schemas/ClassifyTikTicketRequest"),
 *         @OA\Schema(ref="#/components/schemas/ClassifySarprasTicketRequest")
 *     }
 * )
 * @OA\Schema(
 *     schema="ClassifyTikTicketRequest",
 *     type="object",
 *     required={"quality_category_id","it_tag_id"},
 *
 *     @OA\Property(property="quality_category_id", type="integer", example=1),
 *     @OA\Property(property="it_tag_id", type="integer", example=1),
 *     @OA\Property(property="custom_it_tag_text", type="string", maxLength=255, nullable=true, description="Optional and only accepted when the selected IT Tag is Lain-lain.")
 * )
 *
 * @OA\Schema(
 *     schema="ClassifySarprasTicketRequest",
 *     type="object",
 *     required={"sarpras_category_id"},
 *
 *     @OA\Property(property="sarpras_category_id", type="integer", description="Active Sarpras, Elektronik, or Alkes category ID.", example=1)
 * )
 *
 * @OA\Schema(
 *     schema="AssignTicketRequest",
 *     type="object",
 *     required={"assigned_officer_id"},
 *
 *     @OA\Property(property="assigned_officer_id", type="integer", example=2),
 *     @OA\Property(property="priority", type="string", enum={"critical","high","medium","low"}, description="Required for initial assignment and prohibited for reassignment.", example="high")
 * )
 *
 * @OA\Schema(
 *     schema="AssigneeOption",
 *     type="object",
 *     required={"id","name","jabatan"},
 *
 *     @OA\Property(property="id", type="integer", example=2),
 *     @OA\Property(property="name", type="string", example="Budi Petugas"),
 *     @OA\Property(property="jabatan", type="string", nullable=true, example="Teknisi Jaringan")
 * )
 *
 * @OA\Schema(
 *     schema="AssigneeOptionCollectionResponse",
 *     type="object",
 *     required={"data"},
 *
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AssigneeOption"))
 * )
 *
 * @OA\Schema(
 *     schema="CreateTicketHandlingRequest",
 *     type="object",
 *     required={"notes","status","started_at","completed_at"},
 *
 *     @OA\Property(property="notes", type="string", example="Kabel daya dikencangkan dan perangkat diuji."),
 *     @OA\Property(property="status", type="string", enum={"diproses","terselesaikan"}, description="Diproses accepts diproses or terselesaikan. Stored on tickets.status, not ticket_handlings.", example="diproses"),
 *     @OA\Property(property="started_at", type="string", format="date-time", description="Required repair start time, editable by the officer."),
 *     @OA\Property(property="completed_at", type="string", format="date-time", description="Required repair completion time. Must be equal to or after started_at."),
 *     @OA\Property(property="result_photo", type="string", format="binary", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="TicketActionResponse",
 *     type="object",
 *     required={"message","data"},
 *
 *     @OA\Property(property="message", type="string", example="Tiket berhasil diklasifikasi."),
 *     @OA\Property(property="data", ref="#/components/schemas/TicketRead")
 * )
 *
 * @OA\Schema(
 *     schema="TicketPaginationLinks",
 *     type="object",
 *     required={"first","last","prev","next"},
 *
 *     @OA\Property(property="first", type="string", format="uri"),
 *     @OA\Property(property="last", type="string", format="uri"),
 *     @OA\Property(property="prev", type="string", format="uri", nullable=true),
 *     @OA\Property(property="next", type="string", format="uri", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="TicketPaginationLinkItem",
 *     type="object",
 *     required={"url","label","page","active"},
 *
 *     @OA\Property(property="url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="label", type="string"),
 *     @OA\Property(property="page", type="integer", nullable=true),
 *     @OA\Property(property="active", type="boolean")
 * )
 *
 * @OA\Schema(
 *     schema="TicketPaginationMeta",
 *     type="object",
 *     required={"current_page","from","last_page","links","path","per_page","to","total"},
 *
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="from", type="integer", nullable=true, example=1),
 *     @OA\Property(property="last_page", type="integer", example=3),
 *     @OA\Property(property="links", type="array", @OA\Items(ref="#/components/schemas/TicketPaginationLinkItem")),
 *     @OA\Property(property="path", type="string", format="uri"),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="to", type="integer", nullable=true, example=15),
 *     @OA\Property(property="total", type="integer", example=42)
 * )
 *
 * @OA\Schema(
 *     schema="TicketCollectionResponse",
 *     type="object",
 *     required={"data","links","meta"},
 *
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TicketRead")),
 *     @OA\Property(property="links", ref="#/components/schemas/TicketPaginationLinks"),
 *     @OA\Property(property="meta", ref="#/components/schemas/TicketPaginationMeta")
 * )
 *
 * @OA\Schema(
 *     schema="TicketReadResponse",
 *     type="object",
 *     required={"data"},
 *
 *     @OA\Property(property="data", ref="#/components/schemas/TicketRead")
 * )
 *
 * @OA\Schema(
 *     schema="TicketReadError",
 *     type="object",
 *     required={"message"},
 *
 *     @OA\Property(property="message", type="string", example="Resource not found."),
 *     @OA\Property(property="errors", type="object", nullable=true, @OA\AdditionalProperties(type="array", @OA\Items(type="string")))
 * )
 *
 * @OA\Parameter(
 *     parameter="PageParam",
 *     name="page",
 *     in="query",
 *
 *     @OA\Schema(type="integer", minimum=1)
 * )
 *
 * @OA\Parameter(
 *     parameter="PerPageParam",
 *     name="per_page",
 *     in="query",
 *     description="Number of records per page or all.",
 *
 *     @OA\Schema(oneOf={@OA\Schema(type="integer", minimum=1), @OA\Schema(type="string", enum={"all"})})
 * )
 *
 * @OA\Parameter(
 *     parameter="SearchParam",
 *     name="search",
 *     in="query",
 *
 *     @OA\Schema(type="string")
 * )
 *
 * @OA\Parameter(
 *     parameter="IncludeParam",
 *     name="include",
 *     in="query",
 *
 *     @OA\Schema(type="string", example="roles,units")
 * )
 */
class ApiDocumentation
{
    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Auth"},
     *     summary="Login and create Sanctum bearer token",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="email", type="string", example="rsch@gmail.com"),
     *         @OA\Property(property="nip", type="string", example="0000.00000"),
     *         @OA\Property(property="login", type="string", example="rsch@gmail.com"),
     *         @OA\Property(property="password", type="string", example="password"),
     *         @OA\Property(property="device_name", type="string", example="swagger")
     *     )),
     *
     *     @OA\Response(response=200, description="Login successful", @OA\JsonContent(
     *
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="token_type", type="string", example="Bearer"),
     *         @OA\Property(property="token", type="string"),
     *         @OA\Property(property="user", ref="#/components/schemas/User")
     *     )),
     *
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function login(): void {}

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Auth"},
     *     summary="Logout and revoke current token",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Logged out", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout(): void {}

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     tags={"Auth"},
     *     summary="Get authenticated user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Current user", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User"))),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me(): void {}

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     tags={"Auth"},
     *     summary="Register user",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","email","password","password_confirmation"},
     *
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="nip", type="string", nullable=true),
     *         @OA\Property(property="password", type="string"),
     *         @OA\Property(property="password_confirmation", type="string"),
     *         @OA\Property(property="device_name", type="string")
     *     )),
     *
     *     @OA\Response(response=201, description="Registered", @OA\JsonContent(
     *
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="token_type", type="string", example="Bearer"),
     *         @OA\Property(property="token", type="string"),
     *         @OA\Property(property="user", ref="#/components/schemas/User")
     *     )),
     *
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function register(): void {}

    /**
     * @OA\Post(
     *     path="/auth/forgot-password",
     *     tags={"Auth"},
     *     summary="Send reset password link",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"email"}, @OA\Property(property="email", type="string"))),
     *
     *     @OA\Response(response=200, description="Reset link response", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function forgotPassword(): void {}

    /**
     * @OA\Post(
     *     path="/auth/reset-password",
     *     tags={"Auth"},
     *     summary="Reset password",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"token","email","password","password_confirmation"},
     *
     *         @OA\Property(property="token", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="password", type="string"),
     *         @OA\Property(property="password_confirmation", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Password reset response", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function resetPassword(): void {}

    /**
     * @OA\Post(
     *     path="/auth/email/verification-notification",
     *     tags={"Auth"},
     *     summary="Resend email verification notification",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Verification email response", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function resendVerification(): void {}

    /**
     * @OA\Get(
     *     path="/auth/email/verify/{id}/{hash}",
     *     tags={"Auth"},
     *     summary="Verify email with signed URL",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="hash", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Email verified", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Invalid signature")
     * )
     */
    public function verifyEmail(): void {}

    /**
     * @OA\Post(
     *     path="/auth/confirm-password",
     *     tags={"Auth"},
     *     summary="Confirm current password",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"password"}, @OA\Property(property="password", type="string"))),
     *
     *     @OA\Response(response=200, description="Password confirmed", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function confirmPassword(): void {}

    /**
     * @OA\Get(
     *     path="/auth/sso/config",
     *     tags={"SSO"},
     *     summary="Get SSO configuration",
     *
     *     @OA\Response(response=200, description="SSO config", @OA\JsonContent(
     *
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="enabled", type="boolean"),
     *             @OA\Property(property="local_login_allowed", type="boolean"),
     *             @OA\Property(property="login_url", type="string", nullable=true),
     *             @OA\Property(property="logout_url", type="string", nullable=true)
     *         )
     *     ))
     * )
     */
    public function ssoConfig(): void {}

    /**
     * @OA\Get(
     *     path="/auth/sso/login",
     *     tags={"SSO"},
     *     summary="Redirect to IAM login",
     *
     *     @OA\Response(response=302, description="Redirect response"),
     *     @OA\Response(response=409, description="SSO disabled")
     * )
     */
    public function ssoLogin(): void {}

    /**
     * @OA\Get(
     *     path="/auth/sso/callback",
     *     tags={"SSO"},
     *     summary="Receive IAM callback and create one-time exchange code",
     *
     *     @OA\Parameter(name="token", in="query", required=false, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=302, description="Redirect to frontend callback")
     * )
     */
    public function ssoCallback(): void {}

    /**
     * @OA\Post(
     *     path="/auth/sso/exchange",
     *     tags={"SSO"},
     *     summary="Exchange one-time SSO code for Sanctum token",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"code"},
     *
     *         @OA\Property(property="code", type="string"),
     *         @OA\Property(property="device_name", type="string", example="simple-plan-fe")
     *     )),
     *
     *     @OA\Response(response=200, description="Token response", @OA\JsonContent(
     *
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="token_type", type="string", example="Bearer"),
     *         @OA\Property(property="token", type="string"),
     *         @OA\Property(property="user", ref="#/components/schemas/User")
     *     ))
     * )
     */
    public function ssoExchange(): void {}

    /**
     * @OA\Get(
     *     path="/dashboard",
     *     tags={"Dashboard"},
     *     summary="Get dashboard data",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Dashboard data")
     * )
     */
    public function dashboard(): void {}

    /**
     * @OA\Get(
     *     path="/dashboard/live-stats",
     *     tags={"Dashboard"},
     *     summary="Get live dashboard stats",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Live stats")
     * )
     */
    public function liveStats(): void {}

    /**
     * @OA\Get(
     *     path="/profile",
     *     tags={"Profile"},
     *     summary="Get profile",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Profile", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User")))
     * )
     */
    public function profileShow(): void {}

    /**
     * @OA\Patch(
     *     path="/profile",
     *     tags={"Profile"},
     *     summary="Update profile",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="nip", type="string", nullable=true),
     *         @OA\Property(property="avatar", type="string", format="binary", nullable=true)
     *     ))),
     *
     *     @OA\Response(response=200, description="Updated profile", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User")))
     * )
     */
    public function profileUpdate(): void {}

    /**
     * @OA\Put(
     *     path="/profile/password",
     *     tags={"Profile"},
     *     summary="Update password",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"current_password","password","password_confirmation"},
     *
     *         @OA\Property(property="current_password", type="string"),
     *         @OA\Property(property="password", type="string"),
     *         @OA\Property(property="password_confirmation", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Password updated", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function profilePassword(): void {}

    /**
     * @OA\Delete(
     *     path="/profile",
     *     tags={"Profile"},
     *     summary="Delete authenticated account",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"password"}, @OA\Property(property="password", type="string"))),
     *
     *     @OA\Response(response=200, description="Account deleted", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function profileDestroy(): void {}

    /**
     * @OA\Get(
     *     path="/users",
     *     tags={"Users"},
     *     summary="List users",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(ref="#/components/parameters/PageParam"),
     *     @OA\Parameter(ref="#/components/parameters/PerPageParam"),
     *     @OA\Parameter(name="filter[search]", in="query", description="Search by name, email, or NIP", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[unit_id]", in="query", description="Filter by unit ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(ref="#/components/parameters/IncludeParam"),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", example="-created_at")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Normal Flow & Alternative Flow (Pencarian Ditemukan/Tidak Ditemukan)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Examples(example="found", summary="Normal Flow (Ditemukan berdasar unit)", value={"success": true, "message": "Berhasil mengambil daftar user.", "data": {{"id": 2, "nama": "Dr. Andi Setiawan", "unit_id": 1, "role": "user"}}}),
     *             @OA\Examples(example="not_found", summary="Alternative Flow (Tidak ditemukan)", value={"success": true, "message": "Berhasil mengambil daftar user.", "data": {}}),
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    public function usersIndex(): void {}

    /**
     * @OA\Post(
     *     path="/users",
     *     tags={"Users"},
     *     summary="Create user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","email","password","password_confirmation"},
     *         example={
     *             "name": "Dr. Rani",
     *             "email": "rani@hospital.com",
     *             "nip": "19801234567754",
     *             "password": "password123",
     *             "password_confirmation": "password123",
     *             "unit_id": 1,
     *             "jabatan": "Dokter Kandungan",
     *             "no_hp": "081234567890",
     *             "role": "user"
     *         },
     *
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="nip", type="string", nullable=true),
     *         @OA\Property(property="password", type="string"),
     *         @OA\Property(property="password_confirmation", type="string"),
     *         @OA\Property(property="unit_id", type="integer"),
     *         @OA\Property(property="jabatan", type="string"),
     *         @OA\Property(property="no_hp", type="string"),
     *         @OA\Property(property="role", type="string", enum={"super admin","koordinator-sarpras","petugas-tik","petugas-sarpras","user","management"})
     *     )),
     *
     *     @OA\Response(response=201, description="Created user", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User"))),
     *     @OA\Response(response=422, description="Alternative Flow: Validasi Gagal (Atribut kosong atau NIP/No HP sudah ada)", @OA\JsonContent(example={"message": "Form wajib diisi", "errors": {"nip": {"NIP sudah terdaftar di sistem."}, "password": {"Form wajib diisi"}}}))
     * )
     */
    public function usersStore(): void {}

    /**
     * @OA\Get(
     *     path="/users/options",
     *     tags={"Users"},
     *     summary="Get role and unit options for user forms",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="User form options")
     * )
     */
    public function usersOptions(): void {}

    /**
     * @OA\Get(
     *     path="/users/search",
     *     tags={"Users"},
     *     summary="Search users by name",
     *     operationId="searchUsersByName",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         required=true,
     *         description="Full or partial user name",
     *
     *         @OA\Schema(type="string", minLength=1, maxLength=255, example="Rani")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User search results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/UserSearchResult")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Missing users-access permission"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function usersSearch(): void {}

    /**
     * @OA\Get(
     *     path="/assets/search",
     *     tags={"Assets"},
     *     summary="Search assets by asset number or name",
     *     operationId="searchAssets",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="unit", in="query", required=true, description="Unit ID", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="search", in="query", required=false, description="Asset number or name", @OA\Schema(type="string", minLength=1, maxLength=255, example="Meja")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Asset search results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TicketAssetSummary"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Missing tickets-create permission"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function assetsSearch(): void {}

    /**
     * @OA\Get(
     *     path="/users/{user}",
     *     tags={"Users"},
     *     summary="Show user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Normal Flow: Berhasil mengambil detail user", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User")))
     * )
     */
    public function usersShow(): void {}

    /**
     * @OA\Put(
     *     path="/users/{user}",
     *     tags={"Users"},
     *     summary="Update user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         example={
     *             "name": "Dr. Rani (Updated)",
     *             "email": "rani.updated@hospital.com",
     *             "nip": "19801234567754",
     *             "unit_id": 1,
     *             "jabatan": "Dokter Kandungan",
     *             "no_hp": "081234567899",
     *             "role": "user"
     *         },
     *
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="nip", type="string", nullable=true),
     *         @OA\Property(property="password", type="string", nullable=true),
     *         @OA\Property(property="password_confirmation", type="string", nullable=true),
     *         @OA\Property(property="unit_id", type="integer"),
     *         @OA\Property(property="jabatan", type="string"),
     *         @OA\Property(property="no_hp", type="string"),
     *         @OA\Property(property="role", type="string", enum={"super admin","koordinator-sarpras","petugas-tik","petugas-sarpras","user","management"})
     *     )),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Updated user",
     *
     *         @OA\JsonContent(
     *             example={
     *                 "data": {
     *                     "id": 2,
     *                     "name": "Dr. Rani (Updated)",
     *                     "email": "rani.updated@hospital.com",
     *                     "nip": "19801234567754",
     *                     "unit_id": 1,
     *                     "jabatan": "Dokter Kandungan",
     *                     "no_hp": "081234567899",
     *                     "role": "user"
     *                 }
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Alternative Flow: Data tidak lengkap/format salah", @OA\JsonContent(example={"message": "Format data tidak sesuai", "errors": {"no_hp": {"Format data tidak sesuai."}}}))
     * )
     */
    public function usersUpdate(): void {}

    /**
     * @OA\Patch(
     *     path="/users/{user}/status",
     *     tags={"Users"},
     *     summary="Toggle user status (Aktif/Nonaktif)",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Menangani dua skenario: Nonaktif dengan alasan, atau Aktif kembali dengan alasan_nonaktif null",
     *
     *         @OA\JsonContent(
     *             example={"status_user": "Nonaktif", "alasan_nonaktif": "Pindah tugas"},
     *
     *             @OA\Property(property="status_user", type="string", enum={"Aktif","Nonaktif"}),
     *             @OA\Property(property="alasan_nonaktif", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Normal & Alternative Flow: Berhasil menonaktifkan user ATAU berhasil mengaktifkan kembali user.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Examples(example="deactivated", summary="Normal Flow (Berhasil menonaktifkan)", value={"message": "Status user berhasil diubah.", "data": {"status_user": "Nonaktif", "alasan_nonaktif": "Cuti Panjang"}}),
     *             @OA\Examples(example="reactivated", summary="Alternative Flow (Berhasil mengaktifkan kembali)", value={"message": "Status user berhasil diubah.", "data": {"status_user": "Aktif", "alasan_nonaktif": null}}),
     *
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Alternative Flow: Alasan nonaktif tidak dipilih saat menonaktifkan", @OA\JsonContent(example={"message": "Format data tidak sesuai", "errors": {"alasan_nonaktif": {"Format data tidak sesuai."}}}))
     * )
     */
    public function usersToggleStatus(): void {}

    /**
     * @OA\Get(
     *     path="/roles",
     *     tags={"Roles"},
     *     summary="List roles",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(ref="#/components/parameters/PageParam"),
     *     @OA\Parameter(ref="#/components/parameters/PerPageParam"),
     *     @OA\Parameter(ref="#/components/parameters/SearchParam"),
     *     @OA\Parameter(ref="#/components/parameters/IncludeParam"),
     *
     *     @OA\Response(response=200, description="Paginated roles", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Role"))))
     * )
     */
    public function rolesIndex(): void {}

    /**
     * @OA\Post(
     *     path="/roles",
     *     tags={"Roles"},
     *     summary="Create role",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","permissions"}, @OA\Property(property="name", type="string"), @OA\Property(property="permissions", type="array", @OA\Items(type="integer")))),
     *
     *     @OA\Response(response=201, description="Created role", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Role")))
     * )
     */
    public function rolesStore(): void {}

    /**
     * @OA\Get(
     *     path="/roles/permissions",
     *     tags={"Roles"},
     *     summary="List available permissions",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Permissions", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Permission"))))
     * )
     */
    public function rolesPermissions(): void {}

    /**
     * @OA\Get(
     *     path="/roles/{role}",
     *     tags={"Roles"},
     *     summary="Show role",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="role", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Role", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Role")))
     * )
     */
    public function rolesShow(): void {}

    /**
     * @OA\Put(
     *     path="/roles/{role}",
     *     tags={"Roles"},
     *     summary="Update role",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="role", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","permissions"}, @OA\Property(property="name", type="string"), @OA\Property(property="permissions", type="array", @OA\Items(type="integer")))),
     *
     *     @OA\Response(response=200, description="Updated role", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Role")))
     * )
     */
    public function rolesUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/roles/{ids}",
     *     tags={"Roles"},
     *     summary="Delete one or many roles",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ids", in="path", required=true, description="Comma separated IDs", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function rolesDestroy(): void {}

    /**
     * @OA\Get(
     *     path="/units",
     *     tags={"Units"},
     *     summary="List units",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(ref="#/components/parameters/PageParam"),
     *     @OA\Parameter(ref="#/components/parameters/PerPageParam"),
     *     @OA\Parameter(ref="#/components/parameters/SearchParam"),
     *     @OA\Parameter(ref="#/components/parameters/IncludeParam"),
     *
     *     @OA\Response(response=200, description="Paginated units", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Unit"))))
     * )
     */
    public function unitsIndex(): void {}

    /**
     * @OA\Get(
     *     path="/units/search",
     *     tags={"Units"},
     *     summary="Search units by name",
     *     operationId="searchUnits",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="search", in="query", required=false, description="Unit name", @OA\Schema(type="string", minLength=1, maxLength=255, example="ICU")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Unit search results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UnitSearchResult"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Missing tickets-create permission"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function unitsSearch(): void {}

    /**
     * @OA\Post(
     *     path="/units",
     *     tags={"Units"},
     *     summary="Create unit",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"unit_name"}, @OA\Property(property="unit_name", type="string"), @OA\Property(property="description", type="string", nullable=true))),
     *
     *     @OA\Response(response=201, description="Created unit", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Unit")))
     * )
     */
    public function unitsStore(): void {}

    /**
     * @OA\Get(
     *     path="/units/users",
     *     tags={"Units"},
     *     summary="List users available for unit sync",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Users options")
     * )
     */
    public function unitsUsers(): void {}

    /**
     * @OA\Get(
     *     path="/units/{unit}",
     *     tags={"Units"},
     *     summary="Show unit",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="unit", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Unit", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Unit")))
     * )
     */
    public function unitsShow(): void {}

    /**
     * @OA\Put(
     *     path="/units/{unit}",
     *     tags={"Units"},
     *     summary="Update unit",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="unit", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"unit_name"}, @OA\Property(property="unit_name", type="string"), @OA\Property(property="description", type="string", nullable=true))),
     *
     *     @OA\Response(response=200, description="Updated unit", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Unit")))
     * )
     */
    public function unitsUpdate(): void {}

    /**
     * @OA\Post(
     *     path="/units/{unit}/users",
     *     tags={"Units"},
     *     summary="Sync unit users",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="unit", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"user_ids"}, @OA\Property(property="user_ids", type="array", @OA\Items(type="integer")))),
     *
     *     @OA\Response(response=200, description="Synced unit users", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Unit")))
     * )
     */
    public function unitsSyncUsers(): void {}

    /**
     * @OA\Delete(
     *     path="/units/{unit}",
     *     tags={"Units"},
     *     summary="Delete unit",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="unit", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function unitsDestroy(): void {}

    /**
     * @OA\Get(
     *     path="/backups",
     *     tags={"Backups"},
     *     summary="List backups and schedule config",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Backup index")
     * )
     */
    public function backupsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/backups",
     *     tags={"Backups"},
     *     summary="Create database backup",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Backup created")
     * )
     */
    public function backupsStore(): void {}

    /**
     * @OA\Post(
     *     path="/backups/schedule",
     *     tags={"Backups"},
     *     summary="Save backup schedule",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="frequency", type="string", enum={"none","daily","weekly","monthly"}), @OA\Property(property="time", type="string", example="00:00"))),
     *
     *     @OA\Response(response=200, description="Schedule saved")
     * )
     */
    public function backupsSchedule(): void {}

    /**
     * @OA\Get(
     *     path="/backups/download",
     *     tags={"Backups"},
     *     summary="Download backup file",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="path", in="query", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Backup file", @OA\MediaType(mediaType="application/zip"))
     * )
     */
    public function backupsDownload(): void {}

    /**
     * @OA\Delete(
     *     path="/backups",
     *     tags={"Backups"},
     *     summary="Delete backup file",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"path"}, @OA\Property(property="path", type="string"))),
     *
     *     @OA\Response(response=200, description="Backup deleted", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function backupsDestroy(): void {}

    /**
     * @OA\Get(
     *     path="/example-live",
     *     tags={"Example Live"},
     *     summary="Get Example Live metadata",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Metadata")
     * )
     */
    public function exampleLiveIndex(): void {}

    /**
     * @OA\Get(
     *     path="/example-live/real-market",
     *     tags={"Example Live"},
     *     summary="Get market data proxy",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="range", in="query", @OA\Schema(type="string", enum={"live","1d","7d","30d"})),
     *
     *     @OA\Response(response=200, description="Market data")
     * )
     */
    public function exampleLiveMarket(): void {}

    /**
     * @OA\Post(
     *     path="/example-live/start",
     *     tags={"Example Live"},
     *     summary="Start background task",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="steps", type="integer", minimum=1, maximum=60, example=10))),
     *
     *     @OA\Response(response=202, description="Task started")
     * )
     */
    public function exampleLiveStart(): void {}

    /**
     * @OA\Get(
     *     path="/example-live/status/{taskId}",
     *     tags={"Example Live"},
     *     summary="Check background task status",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="taskId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Task status"),
     *     @OA\Response(response=404, description="Task not found")
     * )
     */
    public function exampleLiveStatus(): void {}

    /**
     * @OA\Post(
     *     path="/impersonation/{user}/start",
     *     tags={"Impersonation"},
     *     summary="Start impersonating a user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Impersonation token response"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function impersonationStart(): void {}

    /**
     * @OA\Post(
     *     path="/impersonation/stop",
     *     tags={"Impersonation"},
     *     summary="Stop impersonation and revoke impersonation token",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Impersonation stopped")
     * )
     */
    public function impersonationStop(): void {}

    /**
     * @OA\Get(
     *     path="/impersonation/status",
     *     tags={"Impersonation"},
     *     summary="Get impersonation status",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Impersonation status")
     * )
     */
    public function impersonationStatus(): void {}

    /**
     * @OA\Get(
     *     path="/notifications",
     *     tags={"Notifications"},
     *     summary="List unread notifications and activity summary",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Notifications", @OA\JsonContent(
     *
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Notification")),
     *         @OA\Property(property="activity", type="array", @OA\Items(ref="#/components/schemas/Notification")),
     *         @OA\Property(property="unread_count", type="integer")
     *     ))
     * )
     */
    public function notificationsIndex(): void {}

    /**
     * @OA\Patch(
     *     path="/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="Mark one notification as read",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Notification read")
     * )
     */
    public function notificationsRead(): void {}

    /**
     * @OA\Patch(
     *     path="/notifications/read-all",
     *     tags={"Notifications"},
     *     summary="Mark all notifications as read",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Notifications read")
     * )
     */
    public function notificationsReadAll(): void {}

    /**
     * @OA\Get(
     *     path="/tickets",
     *     operationId="listTickets",
     *     tags={"Helpdesk"},
     *     summary="List Helpdesk tickets",
     *     description="Returns TIK and Sarpras tickets ordered from newest to oldest. Requires the tickets-access permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=15)),
     *     @OA\Parameter(name="service", in="query", required=false, description="Ticket service.", @OA\Schema(type="string", enum={"tik","sarpras"})),
     *     @OA\Parameter(name="status", in="query", required=false, description="Ticket status.", @OA\Schema(type="string", enum={"baru","diklasifikasi","diproses","eskalasi","terselesaikan","ditutup","ditolak"})),
     *     @OA\Parameter(name="priority", in="query", required=false, description="Ticket priority.", @OA\Schema(type="string", enum={"critical","high","medium","low"})),
     *     @OA\Parameter(name="date_from", in="query", required=false, description="Inclusive created date lower bound.", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, description="Inclusive created date upper bound.", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search by ticket reporter name or unit name.", @OA\Schema(type="string", maxLength=255)),
     *     @OA\Parameter(name="status_sla", in="query", required=false, description="Filter tiket berdasarkan status SLA secara on-the-fly.", @OA\Schema(type="string", enum={"melewati_batas","mendekati_batas","tepat_waktu"})),
     *
     *     @OA\Response(response=200, description="Paginated tickets", @OA\JsonContent(ref="#/components/schemas/TicketCollectionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Missing tickets-access permission", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid filters", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketsIndex(): void {}

    /**
     * @OA\Post(
     *     path="/tickets",
     *     operationId="createTicket",
     *     tags={"Helpdesk"},
     *     summary="Create a Helpdesk ticket",
     *     description="Creates a TIK or Sarpras ticket with status baru. Reporter and unit are taken from the authenticated user. Requires the tickets-create permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/CreateTicketRequest"))),
     *
     *     @OA\Response(response=201, description="Ticket created", @OA\JsonContent(ref="#/components/schemas/TicketCreateResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Missing tickets-create permission", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid ticket data", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketsStore(): void {}

    /**
     * @OA\Get(
     *     path="/tickets/classification-options",
     *     operationId="listTicketClassificationOptions",
     *     tags={"Helpdesk"},
     *     summary="Get ticket classification options",
     *     description="Returns active quality categories and IT tags for TIK, or active Sarpras categories for Sarpras. Requires the tickets-verify permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="service", in="query", required=true, description="Ticket service.", @OA\Schema(type="string", enum={"tik","sarpras"}, example="tik")),
     *
     *     @OA\Response(response=200, description="Classification options", @OA\JsonContent(ref="#/components/schemas/ClassificationOptionsResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Missing tickets-verify permission", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid service", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketClassificationOptions(): void {}

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/classify",
     *     operationId="classifyTicket",
     *     tags={"Helpdesk"},
     *     summary="Classify a new Helpdesk ticket",
     *     description="TIK classification is limited to Super Admin. Sarpras classification is allowed for Super Admin and Koordinator Sarpras. The endpoint retains tickets-verify as its general capability permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ClassifyTicketRequest")),
     *
     *     @OA\Response(response=200, description="Ticket classified", @OA\JsonContent(ref="#/components/schemas/TicketActionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Actor cannot classify this ticket service", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=409, description="Ticket state conflict", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid classification data", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketsClassify(): void {}

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/reject",
     *     operationId="rejectTicket",
     *     tags={"Helpdesk"},
     *     summary="Reject a new Helpdesk ticket",
     *     description="The actor must be allowed to classify the ticket service, the ticket must be new, and the tickets-reject permission is required.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RejectTicketRequest")),
     *
     *     @OA\Response(response=200, description="Ticket rejected", @OA\JsonContent(ref="#/components/schemas/TicketActionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Missing tickets-reject permission", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=409, description="Ticket state conflict", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid rejection data", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketsReject(): void {}

    /**
     * @OA\Get(
     *     path="/tickets/{ticket}/assignee-options",
     *     operationId="getTicketAssigneeOptions",
     *     tags={"Helpdesk"},
     *     summary="Search eligible officers for assignment",
     *     description="Returns active Super Admin or Petugas TIK for a TIK ticket, and active Koordinator Sarpras or Petugas Sarpras for a Sarpras ticket. Search matches name or jabatan. TIK is limited to Super Admin; Sarpras is limited to Koordinator Sarpras. Requires the tickets-assign permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Optional name or jabatan search.", @OA\Schema(type="string", maxLength=255)),
     *
     *     @OA\Response(response=200, description="Eligible assignee options", @OA\JsonContent(ref="#/components/schemas/AssigneeOptionCollectionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Actor cannot assign this ticket service", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=409, description="Ticket state conflict", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid search query", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketAssigneeOptions(): void {}

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/assign",
     *     operationId="assignTicket",
     *     tags={"Helpdesk"},
     *     summary="Assign or reassign a ticket officer",
     *     description="TIK assignment is limited to Super Admin and accepts Super Admin or Petugas TIK as assignee. Sarpras assignment is limited to Koordinator Sarpras and accepts Koordinator Sarpras or Petugas Sarpras as assignee. Initial assignment from diklasifikasi requires priority, sets assigned_at and sla_started_at, starts the SLA deadline, and transitions directly to diproses. Reassignment from diproses updates the officer and assigned_at without resetting the first SLA start or deadline. Requires the tickets-assign permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AssignTicketRequest")),
     *
     *     @OA\Response(response=200, description="Officer assigned", @OA\JsonContent(ref="#/components/schemas/TicketActionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Actor or selected officer is not eligible for this ticket service", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=409, description="Ticket state conflict", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid assignment data", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketsAssign(): void {}

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/escalate",
     *     operationId="escalateTicket",
     *     tags={"Helpdesk"},
     *     summary="Escalate a processing ticket",
     *     description="Super Admin may escalate TIK and Sarpras tickets without assignment. Koordinator Sarpras may escalate Sarpras tickets without assignment. Petugas TIK and Petugas Sarpras may only escalate assigned tickets for their respective service. Targets are Manajemen, Vendor, or Tim Terkait. Requires tickets-handle permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"target", "notes"},
     *
     *         @OA\Property(property="target", type="string", enum={"Manajemen","Vendor","Tim Terkait"}, example="Manajemen"),
     *         @OA\Property(property="notes", type="string", example="Butuh persetujuan anggaran pergantian sparepart.")
     *     )),
     *
     *     @OA\Response(response=200, description="Ticket escalated", @OA\JsonContent(ref="#/components/schemas/TicketActionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Missing permission or actor is outside the allowed service and assignment scope", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid escalation data", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketsEscalate(): void {}

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/de-escalate",
     *     operationId="deEscalateTicket",
     *     tags={"Helpdesk"},
     *     summary="Menarik kembali tiket dari status eskalasi (De-escalate)",
     *     description="Hanya petugas yang ditugaskan dengan permission tickets-handle yang dapat melakukan ini.",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="ticket",
     *         in="path",
     *         required=true,
     *         description="ID dari tiket",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept",
     *         in="header",
     *         required=true,
     *         description="Wajib diisi application/json agar tidak ter-redirect ke rute login",
     *
     *         @OA\Schema(type="string", default="application/json")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil ditarik"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - Token tidak valid atau tidak dikirim"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Missing permission or wrong officer",
     *
     *         @OA\JsonContent(ref="#/components/schemas/TicketReadError")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Ticket not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/TicketReadError")
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Ticket state conflict",
     *
     *         @OA\JsonContent(ref="#/components/schemas/TicketReadError")
     *     )
     * )
     */
    public function ticketsDeEscalate(): void {}

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/handlings",
     *     operationId="createTicketHandling",
     *     tags={"Helpdesk"},
     *     summary="Add ticket handling history",
     *     description="Super Admin may handle TIK and Sarpras tickets without assignment. Koordinator Sarpras may handle Sarpras tickets without assignment. Petugas TIK and Petugas Sarpras may only handle tickets currently assigned to them for their respective service. A diproses ticket can remain diproses without status history or become terselesaikan with status history. tickets.completed_at uses the manually supplied completed_at value. Handling never recalculates SLA. Requires the tickets-handle permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/CreateTicketHandlingRequest"))),
     *
     *     @OA\Response(response=200, description="Ticket handling history stored", @OA\JsonContent(ref="#/components/schemas/TicketActionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Missing tickets-handle permission or actor is outside the allowed service and assignment scope", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=409, description="Ticket state conflict", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=422, description="Invalid handling data", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function ticketHandlingsStore(): void {}

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/verify",
     *     operationId="verifyTicketResolution",
     *     tags={"Helpdesk"},
     *     summary="Verify a completed ticket and close it",
     *     description="Only the ticket reporter may verify a terselesaikan ticket. Verification transitions directly to ditutup, records closed_at, and creates status history without using terverifikasi.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"is_approved"},
     *
     *         @OA\Property(property="is_approved", type="boolean", example=true),
     *         @OA\Property(property="keterangan_kendala", type="string", nullable=true, example="Masalah jaringan masih berlanjut.")
     *     )),
     *
     *     @OA\Response(response=200, description="Ticket resolution verified and closed", @OA\JsonContent(ref="#/components/schemas/TicketActionResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Actor is not the ticket reporter", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=409, description="Ticket is not completed", @OA\JsonContent(ref="#/components/schemas/TicketReadError"))
     * )
     */
    public function ticketVerifyResolution(): void {}

    /**
     * @OA\Get(
     *     path="/tickets/{ticket}",
     *     operationId="showTicket",
     *     tags={"Helpdesk"},
     *     summary="Show a Helpdesk ticket",
     *     description="Requires the tickets-access permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ticket", in="path", required=true, description="Internal ticket ID.", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Ticket detail", @OA\JsonContent(ref="#/components/schemas/TicketReadResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=403, description="Missing tickets-access permission", @OA\JsonContent(ref="#/components/schemas/TicketReadError")),
     *     @OA\Response(response=404, description="Ticket not found", @OA\JsonContent(ref="#/components/schemas/TicketReadError"))
     * )
     */
    public function ticketsShow(): void {}
}
