<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Location;
use App\Support\PolicyConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OpportunityIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'work_mode' => ['sometimes', 'nullable', 'string', 'max:40'],
            'sort' => ['sometimes', 'in:newest,deadline,title'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];

        foreach (array_diff(array_keys($this->query->all()), array_keys($rules)) as $parameter) {
            $rules[$parameter] = ['prohibited'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Query filters are accepted from the URL only, never from a request body.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->query->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $filters = $this->query->all();
            if (isset($filters['category']) && $filters['category'] !== '') {
                $exists = Category::query()
                    ->where('slug', $filters['category'])
                    ->where('is_active', true)
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('category', 'The selected category is invalid.');
                }
            }

            if (isset($filters['location']) && $filters['location'] !== '') {
                $policies = $this->container->make(PolicyConfiguration::class);
                $locationIsSelectable = $policies->isEnabled('location_catalogue')
                    && Location::query()
                        ->where('slug', $filters['location'])
                        ->where('is_active', true)
                        ->whereNotNull('reviewed_at')
                        ->whereNotNull('reviewed_by')
                        ->whereNotNull('catalogue_origin')
                        ->whereRaw("TRIM(catalogue_origin) <> ''")
                        ->exists();

                if (! $locationIsSelectable) {
                    $validator->errors()->add('location', 'The selected location is invalid.');
                }
            }
        });
    }
}
