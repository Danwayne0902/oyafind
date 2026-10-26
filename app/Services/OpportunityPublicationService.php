<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Opportunity;
use App\Models\SourceItem;
use App\Models\User;
use App\Models\VerificationRecord;
use App\Repositories\OpportunityDiscoveryRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only path from a staged source item to a public opportunity.
 *
 * Publishing requires an owner-approved publication policy, a staff reviewer
 * and a separate verifier. Every required check type receives an append-only
 * evidence record, so the public "checked" label always points at an
 * attributable review.
 */
class OpportunityPublicationService
{
    public function __construct(
        private readonly OpportunityDiscoveryRepository $discovery,
    ) {}

    /**
     * Staff approval. The item is queued for a separate verifier and is still
     * not public. Reviewer and verifier must therefore run different commands.
     */
    public function approve(SourceItem $item, User $reviewer): void
    {
        $this->reviewerPolicyFor($reviewer);

        if ($item->status !== SourceItem::STATUS_PENDING) {
            throw new RuntimeException('Only pending candidates can be approved.');
        }

        if ($item->deadline_at !== null && $item->deadline_at->isPast()) {
            throw new RuntimeException('The listing deadline has passed.');
        }

        if (! Category::query()
            ->where('slug', $item->category_slug)
            ->where('is_active', true)
            ->exists()) {
            throw new RuntimeException('The listing has no approved category. Choose a category before approving.');
        }

        $item->forceFill([
            'status' => SourceItem::STATUS_REVIEWED,
            'status_reason' => null,
            'reviewed_at' => CarbonImmutable::now('UTC'),
            'reviewed_by' => $reviewer->id,
        ])->save();
    }

    public function publish(SourceItem $item, User $reviewer, User $verifier): Opportunity
    {
        $policy = $this->policyFor($reviewer, $verifier);

        if ($item->status !== SourceItem::STATUS_REVIEWED) {
            throw new RuntimeException('Only staff-approved candidates can be verified and published.');
        }

        if ($item->reviewed_by !== $reviewer->id) {
            throw new RuntimeException('The recorded staff reviewer must complete this publication.');
        }

        if ($item->deadline_at !== null && $item->deadline_at->isPast()) {
            throw new RuntimeException('The listing deadline has passed.');
        }

        $category = Category::query()
            ->where('slug', $item->category_slug)
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            throw new RuntimeException('The listing has no approved category. Choose a category before publishing.');
        }

        $now = CarbonImmutable::now('UTC');
        $reviewedAt = $item->reviewed_at ?? $now;
        $item->loadMissing('source');

        return DB::transaction(function () use ($item, $reviewer, $verifier, $policy, $category, $now, $reviewedAt): Opportunity {
            $opportunity = Opportunity::query()->create([
                'title' => Str::limit($item->title, 200, ''),
                'slug' => $this->uniqueSlug($item),
                'summary' => Str::limit((string) ($item->summary ?: $item->excerpt), 300, '…'),
                'description' => (string) ($item->excerpt ?: $item->summary),
                'category_id' => $category->id,
                'location_id' => $item->location_id,
                'submitted_by' => $reviewer->id,
                'organisation_name' => Str::limit((string) ($item->organisation_name ?: $item->source?->name), 200, ''),
                'source_url' => $item->canonical_url,
                'application_url' => $item->canonical_url,
                'application_method' => null,
                'application_instructions' => null,
                'opportunity_type' => null,
                'work_mode' => $this->workMode($item),
                'eligibility' => null,
                'requirements' => null,
                'amount_min' => null,
                'amount_max' => null,
                'currency' => null,
                'deadline_at' => $item->deadline_at,
                'status' => 'published',
                'verification_status' => 'verified',
                'published_at' => $now,
                'verified_at' => $now,
                'verified_by' => $verifier->id,
            ]);

            foreach ($policy['required_types'] as $checkType) {
                VerificationRecord::query()->create([
                    'opportunity_id' => $opportunity->id,
                    'reviewer_id' => $verifier->id,
                    'result' => 'verified',
                    'check_type' => $checkType,
                    'evidence_url' => $item->canonical_url,
                    'evidence_summary' => $this->checkSummary($checkType),
                    'evidence_rationale' => 'Checked against the source page on '.$now->toDateString().'.',
                    'restricted_notes' => null,
                    'checked_at' => $now->format('Y-m-d H:i:s'),
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
            }

            AuditEvent::query()->create([
                'actor_id' => $reviewer->id,
                'action' => $policy['action'],
                'entity_type' => 'opportunity',
                'entity_id' => $opportunity->id,
                'metadata' => [
                    'source_item_id' => $item->id,
                    'source' => $item->source?->slug,
                    'evidence_reference' => $item->canonical_url,
                    'rationale' => 'Approved from the ingestion queue on '.$reviewedAt->toDateString().
                        '; verified on '.$now->toDateString().'.',
                ],
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $item->forceFill([
                'status' => SourceItem::STATUS_APPROVED,
                'status_reason' => null,
                'reviewed_at' => $now,
                'reviewed_by' => $reviewer->id,
                'opportunity_id' => $opportunity->id,
            ])->save();

            return $opportunity;
        });
    }

    public function reject(SourceItem $item, User $actor, ?string $reason = null): void
    {
        if (! in_array($item->status, [SourceItem::STATUS_PENDING, SourceItem::STATUS_REVIEWED], true)) {
            throw new RuntimeException('Only pending or staff-approved candidates can be rejected.');
        }

        $policy = $this->basePolicy();
        $isReviewer = in_array($actor->id, $policy['staff_ids'], true)
            && in_array($actor->role, $policy['staff_roles'], true);
        $isVerifier = in_array($actor->id, $policy['verifier_ids'], true)
            && in_array($actor->role, $policy['verifier_roles'], true);

        if ($actor->status !== 'active' || (! $isReviewer && ! $isVerifier)) {
            throw new RuntimeException('Only an active, allowlisted reviewer or verifier can reject a candidate.');
        }

        $wasReviewed = $item->status === SourceItem::STATUS_REVIEWED;

        $item->forceFill([
            'status' => SourceItem::STATUS_REJECTED,
            'status_reason' => $reason !== null && $reason !== ''
                ? Str::limit($reason, 200, '')
                : ($wasReviewed ? 'rejected_by_verifier' : 'rejected_by_reviewer'),
            'reviewed_at' => CarbonImmutable::now('UTC'),
            'reviewed_by' => $actor->id,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewerPolicyFor(User $reviewer): array
    {
        $policy = $this->basePolicy();

        if ($reviewer->status !== 'active'
            || ! in_array($reviewer->id, $policy['staff_ids'], true)
            || ! in_array($reviewer->role, $policy['staff_roles'], true)) {
            throw new RuntimeException('The selected reviewer is not an active, allowlisted staff reviewer.');
        }

        return $policy;
    }

    /**
     * @return array<string, mixed>
     */
    public function verifierPolicyFor(User $verifier): array
    {
        $policy = $this->basePolicy();

        if ($verifier->status !== 'active'
            || ! in_array($verifier->id, $policy['verifier_ids'], true)
            || ! in_array($verifier->role, $policy['verifier_roles'], true)) {
            throw new RuntimeException('The selected verifier is not an active, allowlisted verifier.');
        }

        return $policy;
    }

    /**
     * @return array<string, mixed>
     */
    public function policyFor(User $reviewer, User $verifier): array
    {
        $policy = $this->reviewerPolicyFor($reviewer);
        $this->verifierPolicyFor($verifier);

        if ($reviewer->id === $verifier->id || in_array($verifier->id, $policy['staff_ids'], true)) {
            throw new RuntimeException('The staff reviewer and the source verifier must be different people.');
        }

        return $policy;
    }

    /**
     * @return array<string, mixed>
     */
    private function basePolicy(): array
    {
        $policy = $this->discovery->publicationPolicy();

        if ($policy === null) {
            throw new RuntimeException(
                'Publication policy is not enabled. Set OYAFIND_PUBLIC_DISCOVERY_ENABLED=true, '.
                'the staff reviewer and verifier allowlists, the check types, and '.
                'OYAFIND_PUBLIC_REQUIRE_DISTINCT_REVIEWERS=true.'
            );
        }

        return $policy;
    }

    private function uniqueSlug(SourceItem $item): string
    {
        $base = Str::limit(Str::slug($item->title), 180, '') ?: 'opportunity';
        $slug = $base;
        $counter = 2;

        while (Opportunity::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;

            if ($counter > 40) {
                $slug = Str::limit($base, 170, '').'-'.substr(hash('sha256', $item->canonical_url), 0, 8);
                break;
            }
        }

        return $slug;
    }

    private function workMode(SourceItem $item): ?string
    {
        $text = Str::lower($item->title.' '.(string) $item->excerpt);

        return match (true) {
            str_contains($text, 'remote') => 'remote',
            str_contains($text, 'hybrid') => 'hybrid',
            str_contains($text, 'on-site'), str_contains($text, 'onsite') => 'onsite',
            default => null,
        };
    }

    private function checkSummary(string $checkType): string
    {
        return match ($checkType) {
            'source_live' => 'The source page was live and the listing was still present at check time.',
            'details_match' => 'The title, organisation, location and deadline matched the source page.',
            'deadline_current' => 'The source states a deadline that is still in the future.',
            default => 'Checked against the source page.',
        };
    }
}
