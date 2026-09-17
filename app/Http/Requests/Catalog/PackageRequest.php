<?php

namespace App\Http\Requests\Catalog;

use App\DTOs\Catalog\PackageInputData;
use App\Enums\PricingType;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class PackageRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'unit_price' => ['required', 'integer', 'min:1'],
            'minimum_quantity' => ['nullable', 'required_if:pricing_type,fixed', 'integer', 'min:1', 'prohibited_if:pricing_type,per_kg'],
            'minimum_weight_grams' => ['nullable', 'required_if:pricing_type,per_kg', 'integer', 'min:1', 'prohibited_if:pricing_type,fixed'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDto(): PackageInputData
    {
        return new PackageInputData(
            name: $this->string('name')->toString(),
            description: $this->filled('description') ? $this->string('description')->toString() : null,
            pricingType: PricingType::from($this->string('pricing_type')->toString()),
            unitPrice: $this->integer('unit_price'),
            minimumQuantity: $this->filled('minimum_quantity') ? $this->integer('minimum_quantity') : null,
            minimumWeightGrams: $this->filled('minimum_weight_grams') ? $this->integer('minimum_weight_grams') : null,
            estimatedDurationMinutes: $this->integer('estimated_duration_minutes'),
        );
    }
}
