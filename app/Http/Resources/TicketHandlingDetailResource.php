<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketHandlingDetailResource extends TicketHandlingResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($data['result_photo'] !== null) {
            $data['result_photo']['url'] = Storage::temporaryUrl(
                $this->result_photo_object_key,
                now()->addMinutes((int) config('filesystems.temporary_url_expiration_minutes', 5)),
            );
        }

        return $data;
    }
}
