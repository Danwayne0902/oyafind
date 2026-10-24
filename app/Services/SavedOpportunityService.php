<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\SavedOpportunity;
use App\Models\User;
use App\Repositories\OpportunityDiscoveryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SavedOpportunityService
{
    public function __construct(
        private readonly AccountAuthenticationService $authentication,
        private readonly OpportunityDiscoveryRepository $opportunities,
    ) {}

    /**
     * @return Collection<int, SavedOpportunity>
     */
    public function listSaved(User $user): Collection
    {
        $saved = SavedOpportunity::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->with(['opportunity.category', 'opportunity.location.parent'])
            ->get();

        if ($saved->isEmpty()) {
            return $saved;
        }

        $opportunityIds = $saved->pluck('opportunity_id')->unique()->all();
        $publicQuery = $this->opportunities->publicListingQuery([]);
        $publicIds = $publicQuery !== null
            ? $publicQuery->whereIn('id', $opportunityIds)->pluck('id')->all()
            : [];
        $publicIdLookup = array_flip($publicIds);

        foreach ($saved as $record) {
            $isPublic = isset($publicIdLookup[$record->opportunity_id]);
            $record->setAttribute('is_available', $isPublic);

            if (! $isPublic && $record->opportunity !== null) {
                // Do not disclose restricted/expired details
                $record->opportunity->makeHidden([
                    'description',
                    'eligibility',
                    'requirements',
                    'source_url',
                    'application_url',
                    'application_method',
                    'application_instructions',
                    'amount_min',
                    'amount_max',
                    'currency',
                ]);
            }
        }

        return $saved;
    }

    public function save(User $user, int $opportunityId): SavedOpportunity
    {
        if (! $this->authentication->isSaveEligible($user)) {
            throw new AccessDeniedHttpException(
                'Contact verification is required before saving opportunities.'
            );
        }

        $publicOpportunity = $this->opportunities->findPublicById($opportunityId);
        if (! $publicOpportunity) {
            throw new NotFoundHttpException('This opportunity is not available to save.');
        }

        // Idempotent save
        $record = SavedOpportunity::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'opportunity_id' => $opportunityId,
            ],
            [
                'created_at' => now(),
            ],
        );

        $record->setAttribute('is_available', true);

        return $record;
    }

    public function unsave(User $user, int $opportunityId): void
    {
        SavedOpportunity::query()
            ->where('user_id', $user->id)
            ->where('opportunity_id', $opportunityId)
            ->delete();
    }

    public function isSaved(User $user, int $opportunityId): bool
    {
        return SavedOpportunity::query()
            ->where('user_id', $user->id)
            ->where('opportunity_id', $opportunityId)
            ->exists();
    }
}
