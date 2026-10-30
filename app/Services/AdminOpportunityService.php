<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\VerificationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Staff-authored listings use the same review -> verify -> publish evidence
 * chain as ingested listings. A listing created in the admin area still needs
 * a staff approval recorded against it and a separate allowlisted verifier
 * before it can appear in the public feed.
 */
final class AdminOpportunityService
{
    public function __construct(
        private readonly OpportunityPublicationService $publication,
    ) {}

    public function submitForReview(
        Opportunity $opportunity,
        User $reviewer,
        string $evidenceReference,
        string $rationale,
    ): void {
        $policy = $this->publication->reviewerPolicyFor($reviewer);

        if (! in_array($opportunity->status, ['draft', 'reviewed'], true)) {
            throw new RuntimeException('Only draft or already-reviewed listings can be sent for review.');
        }

        $this->guardDeadline($opportunity);

        $evidenceReference = trim($evidenceReference);
        $rationale = trim($rationale);

        if ($evidenceReference === '' && $rationale === '') {
            throw new RuntimeException('Record the source checked, or a short rationale, before approving.');
        }

        DB::transaction(function () use ($opportunity, $reviewer, $policy, $evidenceReference, $rationale): void {
            AuditEvent::query()->create([
                'actor_id' => $reviewer->id,
                'action' => $policy['action'],
                'entity_type' => 'opportunity',
                'entity_id' => $opportunity->id,
                'metadata' => [
                    'evidence_reference' => $evidenceReference !== '' ? $evidenceReference : null,
                    'rationale' => $rationale !== '' ? $rationale : null,
                    'origin' => 'admin_panel',
                ],
                'created_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            ]);

            $opportunity->forceFill(['status' => 'reviewed'])->save();
        });
    }

    public function publish(Opportunity $opportunity, User $verifier): Opportunity
    {
        $policy = $this->publication->verifierPolicyFor($verifier);

        if ($opportunity->status !== 'reviewed') {
            throw new RuntimeException('Only staff-approved listings can be published.');
        }

        $this->guardDeadline($opportunity);

        $review = AuditEvent::query()
            ->where('entity_type', 'opportunity')
            ->where('entity_id', $opportunity->id)
            ->where('action', $policy['action'])
            ->orderByDesc('id')
            ->first();

        if ($review === null) {
            throw new RuntimeException('This listing has no staff approval on record.');
        }

        $reviewer = User::query()->find($review->actor_id);

        if ($reviewer === null) {
            throw new RuntimeException('The staff reviewer account no longer exists.');
        }

        // Enforces the distinct-reviewer rule and both allowlists.
        $this->publication->policyFor($reviewer, $verifier);

        $evidenceUrl = $opportunity->source_url ?: $opportunity->application_url;

        if (blank($evidenceUrl)) {
            throw new RuntimeException('Add the source link before publishing; it is the evidence for the check.');
        }

        $now = CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($opportunity, $verifier, $policy, $evidenceUrl, $now): Opportunity {
            foreach ($policy['required_types'] as $checkType) {
                VerificationRecord::query()->create([
                    'opportunity_id' => $opportunity->id,
                    'reviewer_id' => $verifier->id,
                    'result' => 'verified',
                    'check_type' => $checkType,
                    'evidence_url' => $evidenceUrl,
                    'evidence_summary' => $this->checkSummary($checkType),
                    'evidence_rationale' => 'Checked against the source page on '.$now->toDateString().'.',
                    'restricted_notes' => null,
                    'checked_at' => $now->format('Y-m-d H:i:s'),
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
            }

            $opportunity->forceFill([
                'status' => 'published',
                'verification_status' => 'verified',
                'published_at' => $now,
                'verified_at' => $now,
                'verified_by' => $verifier->id,
            ])->save();

            return $opportunity->fresh();
        });
    }

    public function withdraw(Opportunity $opportunity): void
    {
        if ($opportunity->status !== 'published') {
            throw new RuntimeException('Only published listings can be withdrawn.');
        }

        $opportunity->forceFill([
            'status' => 'draft',
            'published_at' => null,
        ])->save();
    }

    public function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 180, '') ?: 'opportunity';
        $slug = $base;
        $counter = 2;

        while (Opportunity::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;

            if ($counter > 40) {
                $slug = Str::limit($base, 170, '').'-'.substr(hash('sha256', $title.microtime()), 0, 8);
                break;
            }
        }

        return $slug;
    }

    private function guardDeadline(Opportunity $opportunity): void
    {
        if ($opportunity->deadline_at !== null && $opportunity->deadline_at->isPast()) {
            throw new RuntimeException('The listing deadline has passed. Update the deadline first.');
        }
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
