<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBackupSettingRequest;
use App\Http\Resources\BackupSettingResource;
use App\Services\BackupSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BackupSettingController extends Controller
{
    public function __construct(private readonly BackupSettingService $settings) {}

    public function show(Request $request): BackupSettingResource
    {
        abort_unless($request->user()->hasRole('super-admin'), Response::HTTP_FORBIDDEN);

        return new BackupSettingResource($this->settings->current());
    }

    public function update(UpdateBackupSettingRequest $request): BackupSettingResource
    {
        return new BackupSettingResource($this->settings->update($request->validated()));
    }
}
