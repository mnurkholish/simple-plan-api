<?php

namespace App\Http\Requests;

use App\Models\AssetItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $item = $this->route('asset_item');
        $itemId = $item instanceof AssetItem ? $item->id : $item;
        $classificationId = $item instanceof AssetItem
            ? $item->asset_classification_id
            : AssetItem::query()->whereKey($itemId)->value('asset_classification_id');

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('asset_items')
                    ->where(fn ($query) => $query->where('asset_classification_id', $classificationId))
                    ->ignore($itemId),
            ],
        ];
    }
}
