<?php

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
    'email_verification' => [
        'link_expiration_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public opportunity publication policy
    |--------------------------------------------------------------------------
    |
    | No trust policy or reviewer allowlist has been approved. These values
    | intentionally fail closed; do not populate them with production values
    | until the listing-review and verification decisions are approved.
    |
    */
    'discovery' => [
        'enabled' => env('OYAFIND_PUBLIC_DISCOVERY_ENABLED'),
        'staff_review_action' => env('OYAFIND_PUBLIC_STAFF_REVIEW_ACTION'),
        'staff_reviewer_ids' => [],
        'staff_reviewer_roles' => [],
        'verification' => [
            'reviewer_ids' => [],
            'reviewer_roles' => [],
            'allowed_check_types' => [],
            // Required types must jointly cover the approved source/detail checks.
            'required_check_types' => [],
            // Remains unset until owner policy is approved; discovery requires true.
            'require_distinct_reviewers' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner-reviewed location seed records — Nigeria geography catalogue
    |--------------------------------------------------------------------------
    |
    | All 36 states and the Federal Capital Territory (FCT) of Nigeria, plus
    | Nigeria as the country root.  These are official Nigerian administrative
    | divisions as established by the Constitution of the Federal Republic of
    | Nigeria 1999 (as amended) and the National Boundary Commission records.
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

        // ----------------------------------------------------------------
        // Country root
        // ----------------------------------------------------------------
        [
            'name'             => 'Nigeria',
            'slug'             => 'nigeria',
            'location_type'    => 'country',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],

        // ----------------------------------------------------------------
        // 36 States + Federal Capital Territory
        // Listed alphabetically as officially recognised.
        // ----------------------------------------------------------------
        [
            'name'             => 'Abia',
            'slug'             => 'abia',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Adamawa',
            'slug'             => 'adamawa',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Akwa Ibom',
            'slug'             => 'akwa-ibom',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Anambra',
            'slug'             => 'anambra',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Bauchi',
            'slug'             => 'bauchi',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Bayelsa',
            'slug'             => 'bayelsa',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Benue',
            'slug'             => 'benue',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Borno',
            'slug'             => 'borno',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Cross River',
            'slug'             => 'cross-river',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Delta',
            'slug'             => 'delta',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ebonyi',
            'slug'             => 'ebonyi',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Edo',
            'slug'             => 'edo',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ekiti',
            'slug'             => 'ekiti',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Enugu',
            'slug'             => 'enugu',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Federal Capital Territory',
            'slug'             => 'fct-abuja',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Gombe',
            'slug'             => 'gombe',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Imo',
            'slug'             => 'imo',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Jigawa',
            'slug'             => 'jigawa',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Kaduna',
            'slug'             => 'kaduna',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Kano',
            'slug'             => 'kano',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Katsina',
            'slug'             => 'katsina',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Kebbi',
            'slug'             => 'kebbi',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Kogi',
            'slug'             => 'kogi',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Kwara',
            'slug'             => 'kwara',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Lagos',
            'slug'             => 'lagos',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Nasarawa',
            'slug'             => 'nasarawa',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Niger',
            'slug'             => 'niger',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ogun',
            'slug'             => 'ogun',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ondo',
            'slug'             => 'ondo',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Osun',
            'slug'             => 'osun',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Oyo',
            'slug'             => 'oyo',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Plateau',
            'slug'             => 'plateau',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Rivers',
            'slug'             => 'rivers',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Sokoto',
            'slug'             => 'sokoto',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Taraba',
            'slug'             => 'taraba',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Yobe',
            'slug'             => 'yobe',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Zamfara',
            'slug'             => 'zamfara',
            'parent_slug'      => 'nigeria',
            'location_type'    => 'state',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],

        // ----------------------------------------------------------------
        // Oyo State — Local Government Areas
        // The pilot market is Ibadan, Oyo State.  All 33 Oyo LGAs are
        // seeded so users across Oyo can select their area.  LGAs for
        // other states will be added as the product expands.
        // Source: Independent National Electoral Commission (INEC) LGA
        // register for Oyo State; NPC 2006 and 2023 census administrative
        // divisions.  Catalogue origin: oyafind_reviewed.
        // ----------------------------------------------------------------
        [
            'name'             => 'Afijio',
            'slug'             => 'oyo-afijio',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Akinyele',
            'slug'             => 'oyo-akinyele',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Atiba',
            'slug'             => 'oyo-atiba',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Atisbo',
            'slug'             => 'oyo-atisbo',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Egbeda',
            'slug'             => 'oyo-egbeda',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibadan North',
            'slug'             => 'oyo-ibadan-north',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibadan North-East',
            'slug'             => 'oyo-ibadan-north-east',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibadan North-West',
            'slug'             => 'oyo-ibadan-north-west',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibadan South-East',
            'slug'             => 'oyo-ibadan-south-east',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibadan South-West',
            'slug'             => 'oyo-ibadan-south-west',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibarapa Central',
            'slug'             => 'oyo-ibarapa-central',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibarapa East',
            'slug'             => 'oyo-ibarapa-east',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ibarapa North',
            'slug'             => 'oyo-ibarapa-north',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ido',
            'slug'             => 'oyo-ido',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Irepo',
            'slug'             => 'oyo-irepo',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Iseyin',
            'slug'             => 'oyo-iseyin',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Itesiwaju',
            'slug'             => 'oyo-itesiwaju',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Iwajowa',
            'slug'             => 'oyo-iwajowa',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Kajola',
            'slug'             => 'oyo-kajola',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Lagelu',
            'slug'             => 'oyo-lagelu',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ogbomosho North',
            'slug'             => 'oyo-ogbomosho-north',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ogbomosho South',
            'slug'             => 'oyo-ogbomosho-south',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ogo Oluwa',
            'slug'             => 'oyo-ogo-oluwa',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Olorunsogo',
            'slug'             => 'oyo-olorunsogo',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Oluyole',
            'slug'             => 'oyo-oluyole',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ona-Ara',
            'slug'             => 'oyo-ona-ara',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Orelope',
            'slug'             => 'oyo-orelope',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Ori-Ire',
            'slug'             => 'oyo-ori-ire',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Oyo East',
            'slug'             => 'oyo-oyo-east',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Oyo West',
            'slug'             => 'oyo-oyo-west',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Saki East',
            'slug'             => 'oyo-saki-east',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Saki West',
            'slug'             => 'oyo-saki-west',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],
        [
            'name'             => 'Surulere',
            'slug'             => 'oyo-surulere',
            'parent_slug'      => 'oyo',
            'location_type'    => 'lga',
            'is_active'        => true,
            'catalogue_origin' => 'oyafind_reviewed',
            'reviewed_at'      => '2026-10-23 00:00:00',
        ],

    ],
];
