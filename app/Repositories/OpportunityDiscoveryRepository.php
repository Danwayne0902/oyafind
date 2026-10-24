<?php

namespace App\Repositories;

use App\Models\Opportunity;
use App\Support\PolicyConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query boundary for opportunities eligible for public discovery.
 *
 * Publication and verification columns are not trusted as proof. Eligibility
 * is determined by attributable audit and verification records instead.
 */
class OpportunityDiscoveryRepository
{
    public function __construct(
        private readonly PolicyConfiguration $policies,
    ) {}

    /**
     * Return a database query already restricted to evidence-backed public
     * listings. Returning null means trust policy is incomplete and fails shut.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Opportunity>|null
     */
    public function publicListingQuery(array $filters): ?Builder
    {
        $policy = $this->publicationPolicy();
        if ($policy === null) {
            return null;
        }

        $now = CarbonImmutable::now('UTC');
        $query = Opportunity::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now->format('Y-m-d H:i:s'))
            ->where(function (Builder $verificationStatus): void {
                $verificationStatus
                    ->whereNull('verification_status')
                    ->orWhere('verification_status', '!=', 'flagged');
            })
            ->whereDoesntHave('reports', fn (Builder $reports) => $reports->where('status', 'flagged'))
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true))
            ->whereHas('verificationRecords', fn (Builder $records) => $this->latestQualifyingVerification(
                $records,
                $policy,
                $now,
            ))
            ->whereExists(fn ($audit) => $this->qualifyingStaffReview($audit, $policy));

        foreach ($policy['required_types'] as $type) {
            $query->whereHas('verificationRecords', fn (Builder $records) => $this->latestQualifyingVerification(
                $records,
                $policy,
                $now,
                $type,
            ));
            // A currently valid recheck cannot retroactively authorize
            // publication. Require qualifying evidence as of publication too.
            $query->whereHas('verificationRecords', fn (Builder $records) => $this->latestQualifyingVerification(
                $records,
                $policy,
                $now,
                $type,
                beforePublication: true,
            ));
        }

        $this->applyDeadlineAndFreshnessPolicy($query, $policy, $now);

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("summary LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("organisation_name LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if (filled($filters['category'] ?? null)) {
            $query->whereHas('category', fn (Builder $category) => $category
                ->where('slug', $filters['category'])
                ->where('is_active', true));
        }

        if (filled($filters['location'] ?? null)) {
            if (! $this->policies->isEnabled('location_catalogue')) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereHas('location', fn (Builder $location) => $this->selectableLocation($location)
                ->where('slug', $filters['location']));
        }

        if (filled($filters['work_mode'] ?? null)) {
            $query->where('work_mode', $filters['work_mode']);
        }

        return $query->with(['category', 'location.parent']);
    }

    public function findPublicBySlug(string $slug): ?Opportunity
    {
        return $this->publicListingQuery([])
            ?->where('slug', $slug)
            ->first();
    }

    public function findPublicById(int $id): ?Opportunity
    {
        return $this->publicListingQuery([])
            ?->where('id', $id)
            ->first();
    }

    /**
     * @return array{
     *   action: string,
     *   staff_ids: list<int>,
     *   staff_roles: list<string>,
     *   verifier_ids: list<int>,
     *   verifier_roles: list<string>,
     *   allowed_types: list<string>,
     *   required_types: list<string>,
     *   distinct: true
     * }|null
     */
    private function publicationPolicy(): ?array
    {
        $configuration = config('oyafind.discovery');
        if (! is_array($configuration) || ($configuration['enabled'] ?? null) !== true) {
            return null;
        }

        $verification = $configuration['verification'] ?? null;
        if (! is_array($verification)) {
            return null;
        }

        $action = $configuration['staff_review_action'] ?? null;
        $staffIds = $this->positiveIds($configuration['staff_reviewer_ids'] ?? null);
        $staffRoles = $this->strings($configuration['staff_reviewer_roles'] ?? null);
        $verifierIds = $this->positiveIds($verification['reviewer_ids'] ?? null);
        $verifierRoles = $this->strings($verification['reviewer_roles'] ?? null);
        $allowedTypes = $this->strings($verification['allowed_check_types'] ?? null);
        $requiredTypes = $this->strings($verification['required_check_types'] ?? null);

        if (! is_string($action) || trim($action) === ''
            || $staffIds === [] || $staffRoles === []
            || $verifierIds === [] || $verifierRoles === []
            || $allowedTypes === [] || $requiredTypes === []
            || array_diff($requiredTypes, $allowedTypes) !== []
            || ($verification['require_distinct_reviewers'] ?? null) !== true) {
            return null;
        }

        return [
            'action' => $action,
            'staff_ids' => $staffIds,
            'staff_roles' => $staffRoles,
            'verifier_ids' => $verifierIds,
            'verifier_roles' => $verifierRoles,
            'allowed_types' => $allowedTypes,
            'required_types' => $requiredTypes,
            'distinct' => true,
        ];
    }

    /**
     * Require current deadlines and a fresh recheck for no-deadline listings.
     * Stored DATETIME values are interpreted as UTC; the product's deadline
     * timezone/boundary policy remains unresolved and is not localized here.
     *
     * @param  array<string, mixed>  $policy
     */
    private function applyDeadlineAndFreshnessPolicy(
        Builder $query,
        array $policy,
        CarbonImmutable $now,
    ): void {
        $nowValue = $now->format('Y-m-d H:i:s');
        $freshnessBoundary = $now->subDays(30)->format('Y-m-d H:i:s');

        $query->where(function (Builder $deadlines) use (
            $nowValue,
            $freshnessBoundary,
            $policy,
            $now,
        ): void {
            $deadlines
                ->where(function (Builder $knownDeadline) use ($nowValue): void {
                    $knownDeadline
                        ->whereNotNull('deadline_at')
                        ->where('deadline_at', '>', $nowValue);
                })
                ->orWhere(function (Builder $noDeadline) use ($freshnessBoundary, $policy, $now): void {
                    $noDeadline
                        ->whereNull('deadline_at')
                        ->where(function (Builder $freshness) use ($freshnessBoundary, $policy, $now): void {
                            $freshness->where('published_at', '>=', $freshnessBoundary)
                                ->orWhere(function (Builder $rechecks) use ($freshnessBoundary, $policy, $now): void {
                                    foreach ($policy['required_types'] as $type) {
                                        $rechecks->whereHas('verificationRecords', fn (Builder $records) => $this->latestQualifyingVerification(
                                            $records,
                                            $policy,
                                            $now,
                                            $type,
                                            $freshnessBoundary,
                                        ));
                                    }
                                });
                        });
                });
        });
    }

    /**
     * The latest record for each required check type must be verified,
     * attributable to an allowlisted verifier, and carry evidence/rationale.
     * An unresolved later check cannot be bypassed by an older verified row.
     *
     * @param  array<string, mixed>  $policy
     */
    private function latestQualifyingVerification(
        Builder $records,
        array $policy,
        CarbonImmutable $now,
        ?string $type = null,
        ?string $minimumCheckedAt = null,
        bool $beforePublication = false,
    ): Builder {
        $records
            ->whereIn('check_type', $type === null ? $policy['required_types'] : [$type])
            ->whereIn('check_type', $policy['allowed_types'])
            ->whereIn('reviewer_id', $policy['verifier_ids'])
            ->whereNotIn('reviewer_id', $policy['staff_ids'])
            ->whereIn('result', ['verified'])
            ->where('checked_at', '<=', $now->format('Y-m-d H:i:s'))
            ->where('created_at', '<=', $now->format('Y-m-d H:i:s'))
            ->whereHas('reviewer', fn (Builder $reviewer) => $reviewer
                ->whereIn('role', $policy['verifier_roles']))
            ->where(function (Builder $evidence): void {
                $evidence
                    ->whereRaw("TRIM(COALESCE(evidence_url, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE(evidence_rationale, '')) <> ''");
            })
            ->whereNotExists(function ($newer) use ($now, $beforePublication): void {
                $newer->selectRaw('1')
                    ->from('opportunity_verifications as newer_verifications')
                    ->whereColumn(
                        'newer_verifications.opportunity_id',
                        'opportunity_verifications.opportunity_id',
                    )
                    ->whereColumn(
                        'newer_verifications.check_type',
                        'opportunity_verifications.check_type',
                    )
                    ->where('newer_verifications.checked_at', '<=', $now->format('Y-m-d H:i:s'))
                    ->when($beforePublication, function ($query): void {
                        $query
                            ->whereColumn('newer_verifications.checked_at', '<=', 'opportunities.published_at')
                            ->whereColumn('newer_verifications.created_at', '<=', 'opportunities.published_at');
                    })
                    ->where(function ($later): void {
                        $later
                            ->whereColumn(
                                'newer_verifications.checked_at',
                                '>',
                                'opportunity_verifications.checked_at',
                            )
                            ->orWhere(function ($tie): void {
                                $tie
                                    ->whereColumn(
                                        'newer_verifications.checked_at',
                                        '=',
                                        'opportunity_verifications.checked_at',
                                    )
                                    ->whereColumn(
                                        'newer_verifications.id',
                                        '>',
                                        'opportunity_verifications.id',
                                    );
                            });
                    });
            });

        if ($beforePublication) {
            $records
                ->whereColumn('opportunity_verifications.checked_at', '<=', 'opportunities.published_at')
                ->whereColumn('opportunity_verifications.created_at', '<=', 'opportunities.published_at');
        }

        if ($minimumCheckedAt !== null) {
            $records->where('checked_at', '>=', $minimumCheckedAt);
        }

        return $records;
    }

    /**
     * Staff approval evidence is an attributable audit-log review action. The
     * action/actor alone is insufficient: the record must contain a reference
     * or rationale, and must precede publication.
     *
     * @param  array<string, mixed>  $policy
     */
    private function qualifyingStaffReview($audit, array $policy): void
    {
        $driver = DB::connection()->getDriverName();
        $evidenceReference = $driver === 'sqlite'
            ? "json_extract(audit_logs.metadata, '$.evidence_reference')"
            : "JSON_UNQUOTE(JSON_EXTRACT(audit_logs.metadata, '$.evidence_reference'))";
        $evidenceReferenceType = $driver === 'sqlite'
            ? "json_type(audit_logs.metadata, '$.evidence_reference') = 'text'"
            : "JSON_TYPE(JSON_EXTRACT(audit_logs.metadata, '$.evidence_reference')) = 'STRING'";
        $rationale = $driver === 'sqlite'
            ? "json_extract(audit_logs.metadata, '$.rationale')"
            : "JSON_UNQUOTE(JSON_EXTRACT(audit_logs.metadata, '$.rationale'))";
        $rationaleType = $driver === 'sqlite'
            ? "json_type(audit_logs.metadata, '$.rationale') = 'text'"
            : "JSON_TYPE(JSON_EXTRACT(audit_logs.metadata, '$.rationale')) = 'STRING'";

        $audit->selectRaw('1')
            ->from('audit_logs')
            ->join('users as review_actors', 'review_actors.id', '=', 'audit_logs.actor_id')
            ->whereColumn('audit_logs.entity_id', 'opportunities.id')
            ->whereColumn('audit_logs.created_at', '<=', 'opportunities.published_at')
            ->where('audit_logs.entity_type', 'opportunity')
            ->where('audit_logs.action', $policy['action'])
            ->whereIn('audit_logs.actor_id', $policy['staff_ids'])
            ->whereIn('review_actors.role', $policy['staff_roles'])
            ->whereRaw(
                "(({$evidenceReferenceType} AND NULLIF(TRIM(COALESCE({$evidenceReference}, '')), '') IS NOT NULL) OR ".
                "({$rationaleType} AND NULLIF(TRIM(COALESCE({$rationale}, '')), '') IS NOT NULL))",
            );
    }

    private function selectableLocation(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('reviewed_by')
            ->whereNotNull('catalogue_origin')
            ->whereRaw("TRIM(catalogue_origin) <> ''");
    }

    /**
     * @return list<int>
     */
    private function positiveIds(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($value): int => is_numeric($value) ? (int) $value : 0, $values),
            static fn (int $value): bool => $value > 0,
        )));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($value): string => is_string($value) ? trim($value) : '', $values),
            static fn (string $value): bool => $value !== '',
        )));
    }
}
