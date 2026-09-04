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
 *     url="http://localhost:8000/api/v1",
 *     description="Local API server"
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
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Ahmad Ilyas"),
 *     @OA\Property(property="email", type="string", nullable=true, example="juniyasyos@gmail.com"),
 *     @OA\Property(property="nip", type="string", nullable=true, example="0000.00000"),
 *     @OA\Property(property="avatar", type="string", nullable=true),
 *     @OA\Property(property="email_verified_at", type="string", nullable=true, format="date-time"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="roles", type="array", @OA\Items(ref="#/components/schemas/Role")),
 *     @OA\Property(property="units", type="array", @OA\Items(ref="#/components/schemas/Unit")),
 *     @OA\Property(property="permissions", type="object", @OA\AdditionalProperties(type="boolean")),
 *     @OA\Property(property="created_at", type="string", nullable=true, format="date-time")
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
     *         @OA\Property(property="email", type="string", example="juniyasyos@gmail.com"),
     *         @OA\Property(property="nip", type="string", example="0000.00000"),
     *         @OA\Property(property="login", type="string", example="juniyasyos@gmail.com"),
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
     *     @OA\Parameter(ref="#/components/parameters/SearchParam"),
     *     @OA\Parameter(ref="#/components/parameters/IncludeParam"),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", example="-created_at")),
     *
     *     @OA\Response(response=200, description="Paginated users", @OA\JsonContent(
     *
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *         @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *     ))
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
     *
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="nip", type="string", nullable=true),
     *         @OA\Property(property="password", type="string"),
     *         @OA\Property(property="password_confirmation", type="string"),
     *         @OA\Property(property="selectedRoles", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="selectedUnits", type="array", @OA\Items(type="integer")),
     *         @OA\Property(property="status", type="string", enum={"active","inactive","suspended"})
     *     )),
     *
     *     @OA\Response(response=201, description="Created user", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User")))
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
     *     path="/users/{user}",
     *     tags={"Users"},
     *     summary="Show user",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="User", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User")))
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
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/User")),
     *
     *     @OA\Response(response=200, description="Updated user", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User")))
     * )
     */
    public function usersUpdate(): void {}

    /**
     * @OA\Delete(
     *     path="/users/{ids}",
     *     tags={"Users"},
     *     summary="Delete one or many users",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ids", in="path", required=true, description="Comma separated IDs", @OA\Schema(type="string", example="2,3")),
     *
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function usersDestroy(): void {}

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
}
