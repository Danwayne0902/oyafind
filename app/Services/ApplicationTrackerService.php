<?php

namespace App\Services;

use App\Models\ApplicationTracker;
use App\Models\User;
use App\Repositories\OpportunityDiscoveryRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApplicationTrackerService
{
    public const ALLOWED_STATUSES = [
        'interested',
        'preparing',
        'applied',
        'interview',
        'successful',
        'unsuccessful',
        'user-reported',
    ];

    public function __construct(
        private readonly OpportunityDiscoveryRepository $opportunities,
    ) {}

    /**
     * @return Collection<int, ApplicationTracker>
     */
    public function listApplications(User $user): Collection
    {
        $applications = ApplicationTracker::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->with(['opportunity.category', 'opportunity.location.parent', 'opportunity.latestVerifiedCheck'])
            ->get();

        if ($applications->isEmpty()) {
            return $applications;
        }

        $opportunityIds = $applications->pluck('opportunity_id')->unique()->all();
        $publicQuery = $this->opportunities->publicListingQuery([]);
        $publicIds = $publicQuery !== null
            ? $publicQuery->whereIn('id', $opportunityIds)->pluck('id')->all()
            : [];
        $publicIdLookup = array_flip($publicIds);

        foreach ($applications as $record) {
            $isPublic = isset($publicIdLookup[$record->opportunity_id]);
            $record->setAttribute('is_available', $isPublic);

            if (! $isPublic && $record->opportunity !== null) {
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

        return $applications;
    }

    /**
     * @param  array{status?: string, notes?: string|null, applied_at?: string|null}  $data
     */
    public function track(User $user, int $opportunityId, array $data = []): ApplicationTracker
    {
        $status = $data['status'] ?? 'interested';
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['The tracker status must be one of: '.implode(', ', self::ALLOWED_STATUSES).'.'],
            ]);
        }

        if ($this->opportunities->findPublicById($opportunityId) === null) {
            throw new NotFoundHttpException('Opportunity not found.');
        }

        $appliedAt = ! empty($data['applied_at'])
            ? CarbonImmutable::parse($data['applied_at'])
            : null;

        return ApplicationTracker::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'opportunity_id' => $opportunityId,
            ],
            [
                'status' => $status,
                'status_source' => 'user-reported',
                'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
                'applied_at' => $appliedAt,
            ],
        );
    }

    /**
     * @param  array{status?: string, notes?: string|null, applied_at?: string|null}  $data
     */
    public function update(User $user, int $id, array $data): ApplicationTracker
    {
        /** @var ApplicationTracker|null $tracker */
        $tracker = ApplicationTracker::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $tracker) {
            throw new NotFoundHttpException('Application tracker record not found.');
        }

        if (array_key_exists('status', $data)) {
            if (! in_array($data['status'], self::ALLOWED_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => ['The tracker status must be one of: '.implode(', ', self::ALLOWED_STATUSES).'.'],
                ]);
            }
            $tracker->status = $data['status'];
            $tracker->status_source = 'user-reported';
        }

        if (array_key_exists('notes', $data)) {
            $tracker->notes = $data['notes'] !== null ? (string) $data['notes'] : null;
        }

        if (array_key_exists('applied_at', $data)) {
            $tracker->applied_at = ! empty($data['applied_at'])
                ? CarbonImmutable::parse($data['applied_at'])
                : null;
        }

        $tracker->save();

        return $tracker->fresh(['opportunity.category', 'opportunity.location.parent']);
    }

    public function delete(User $user, int $id): void
    {
        ApplicationTracker::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->delete();
    }
}
