<?php

/*
 * Comma-separated env values become clean lists. An unset value stays empty so
 * the publication policy keeps failing closed instead of inventing reviewers.
 */
$csv = static function (mixed $value): array {
    if (! is_string($value) || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(
        array_map('trim', explode(',', $value)),
        static fn (string $item): bool => $item !== '',
    ));
};

$csvIds = static function (mixed $value) use ($csv): array {
    return array_map(static fn (string $item): int => (int) $item, $csv($value));
};

return [
    /*
    |--------------------------------------------------------------------------
    | Owner-approved feature policies
    |--------------------------------------------------------------------------
    |
    | These switches intentionally have no defaults. A feature stays disabled
    | until its owner-approved policy is explicitly configured.
    |
    */
    'policies' => [
        'account_registration' => env('OYAFIND_ACCOUNT_REGISTRATION'),
        'local_registration_without_verification' => env('OYAFIND_LOCAL_REGISTRATION_WITHOUT_VERIFICATION'),
        'listing_submission' => env('OYAFIND_LISTING_SUBMISSION'),
        'location_catalogue' => env('OYAFIND_LOCATION_CATALOGUE'),
        'notifications' => env('OYAFIND_NOTIFICATIONS'),
    ],
    /*
    | Owner-approved registration mode. `direct` creates an active account with
    | an unverified contact; `email_verification` requires a working mailer.
    | Unset falls back to the legacy local bypass, otherwise email verification.
    */
    'registration' => [
        'mode' => env('OYAFIND_REGISTRATION_MODE'),
    ],
    'email_verification' => [
        'link_expiration_minutes' => 60,
    ],

    /*
    | Sign-in defence. The rate limiter stops bursts from one IP; the lockout
    | remembers failures per account so a distributed guess is stopped too.
    | Both are active at once and neither replaces the other.
    */
    'auth' => [
        'max_attempts' => (int) env('OYAFIND_LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('OYAFIND_LOGIN_DECAY_MINUTES', 15),
        'lockout_minutes' => (int) env('OYAFIND_LOGIN_LOCKOUT_MINUTES', 15),
        'ip_attempts_per_minute' => (int) env('OYAFIND_LOGIN_IP_ATTEMPTS_PER_MINUTE', 10),
        'identifier_attempts_per_minute' => (int) env('OYAFIND_LOGIN_IDENTIFIER_ATTEMPTS_PER_MINUTE', 5),
    ],

    /*
    | Extra disposable email domains to reject at registration, on top of the
    | bundled blocklist in resources/data/disposable-email-domains.txt.
    */
    'registration_blocked_email_domains' => $csv(env('OYAFIND_REGISTRATION_BLOCKED_EMAIL_DOMAINS')),

    /*
    |--------------------------------------------------------------------------
    | Public opportunity publication policy
    |--------------------------------------------------------------------------
    |
    | No trust policy or reviewer allowlist has been approved by default. These
    | values come from the environment and intentionally fail closed; do not
    | populate them with production values until the listing-review and
    | verification decisions are approved.
    |
    */
    'discovery' => [
        'enabled' => env('OYAFIND_PUBLIC_DISCOVERY_ENABLED'),
        'staff_review_action' => env('OYAFIND_PUBLIC_STAFF_REVIEW_ACTION'),
        'staff_reviewer_ids' => $csvIds(env('OYAFIND_PUBLIC_STAFF_REVIEWER_IDS')),
        'staff_reviewer_roles' => $csv(env('OYAFIND_PUBLIC_STAFF_REVIEWER_ROLES')),
        'verification' => [
            'reviewer_ids' => $csvIds(env('OYAFIND_PUBLIC_VERIFIER_IDS')),
            'reviewer_roles' => $csv(env('OYAFIND_PUBLIC_VERIFIER_ROLES')),
            'allowed_check_types' => $csv(env('OYAFIND_PUBLIC_ALLOWED_CHECK_TYPES')),
            // Required types must jointly cover the approved source/detail checks.
            'required_check_types' => $csv(env('OYAFIND_PUBLIC_REQUIRED_CHECK_TYPES')),
            // Remains unset until owner policy is approved; discovery requires true.
            'require_distinct_reviewers' => env('OYAFIND_PUBLIC_REQUIRE_DISTINCT_REVIEWERS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Opportunity ingestion
    |--------------------------------------------------------------------------
    |
    | Ingestion only stages candidates for staff review. These settings control
    | the HTTP client and how much of a source page may be stored as an excerpt;
    | they never decide whether a listing is published.
    |
    */
    'ingestion' => [
        'user_agent' => env(
            'OYAFIND_INGEST_USER_AGENT',
            'OyaFindBot/1.0 (+https://oyafind.ng/sources; opportunity review)',
        ),
        'timeout' => (int) env('OYAFIND_INGEST_TIMEOUT', 20),
        'excerpt_length' => (int) env('OYAFIND_INGEST_EXCERPT_LENGTH', 1500),
        'per_source_limit' => (int) env('OYAFIND_INGEST_PER_SOURCE_LIMIT', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner-reviewed location seed records — Nigeria and beyond
    |--------------------------------------------------------------------------
    |
    | The authoritative catalogue ships as CSV under database/seeders/data/:
    | Nigeria plus all 36 states, the FCT and 774 LGAs, and the reviewed
    | beyond-Nigeria regions/countries in global/locations.csv. These are
    | official Nigerian administrative divisions as established by the
    | Constitution of the Federal Republic of Nigeria 1999 (as amended) and
    | the National Boundary Commission records.
    |
    | catalogue_origin: 'oyafind_reviewed' — names and hierarchy reviewed
    | internally against official Nigerian government sources; no external
    | data pipeline or OSM import is used for this layer.
    |
    | reviewed_by is resolved at seed time to the first administrator account
    | (see LocationSeeder).  The reviewed_at date records when this catalogue
    | entry was authored and approved for inclusion.
    |
    | Parent relationships use parent_slug (resolved to parent_id by the
    | seeder) so that records remain readable without database IDs.
    |
    | Source attribution: Federal Republic of Nigeria — Constitution 1999
    | (as amended); National Boundary Commission of Nigeria.
    |
    */
    'location_catalogue_seed_records' => [
        // The shipped catalogue under database/seeders/data/nigeria/ is the
        // authoritative source. Keeping this empty makes LocationSeeder load
        // the full states + 774 LGA CSV instead of a partial inline subset.
    ],

    /*
    | Owner-approved contact details. These are displayed in the footer and
    | published in the Organization schema, so they must stay reachable.
    */
    'contact' => [
        'phone' => env('OYAFIND_CONTACT_PHONE', '+2347053404846'),
        'email' => env('OYAFIND_CONTACT_EMAIL', 'danwayne0902@yahoo.com'),
    ],

    /*
    | Search metadata that is not derived from the catalogue. `same_as` accepts
    | comma-separated profile URLs (social accounts, directories) that prove
    | the publisher identity to search engines. Leave it empty until the
    | profiles actually exist — never point it at an account OyaFind does not
    | control.
    */
    'seo' => [
        'same_as' => $csv(env('OYAFIND_SEO_SAME_AS')),
    ],
];
