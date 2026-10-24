<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\VerificationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpportunityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now('UTC');
        CarbonImmutable::setTestNow($now);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@oyafind.ng'],
            [
                'name' => 'OyaFind Admin',
                'password' => bcrypt('demo'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

        $staff = User::query()->firstOrCreate(
            ['email' => 'staff@oyafind.ng'],
            [
                'name' => 'Demo Staff Reviewer',
                'password' => bcrypt('demo'),
                'role' => 'staff',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $staff->forceFill(['role' => 'staff', 'status' => 'active'])->save();

        $verifier = User::query()->firstOrCreate(
            ['email' => 'verifier@oyafind.ng'],
            [
                'name' => 'Demo Verifier',
                'password' => bcrypt('demo'),
                'role' => 'staff',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $verifier->forceFill(['role' => 'staff', 'status' => 'active'])->save();

        $categories = Category::query()->where('is_active', true)->get()->keyBy('slug');
        $locations = Location::query()->where('is_active', true)->whereNotNull('reviewed_at')->limit(50)->get();

        $demos = [
            [
                'title' => 'Graduate Trainee Programme',
                'slug' => 'demo-graduate-trainee',
                'summary' => 'Entry-level graduate opportunity with structured training and mentorship.',
                'description' => "Join our graduate trainee programme for hands-on experience across multiple departments. Receive mentorship from senior professionals and build a strong foundation for your career.",
                'category' => 'jobs',
                'organisation' => 'Lagos Business Hub',
                'work_mode' => 'onsite',
                'opportunity_type' => 'full_time',
                'eligibility' => 'Recent graduates with a degree from a recognized institution',
                'requirements' => ['Bachelor\'s degree', 'NYSC completion or exemption'],
                'amount_min' => 80000,
                'amount_max' => 120000,
                'currency' => 'NGN',
                'deadline_at' => $now->addDays(14)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Software Engineering Internship',
                'slug' => 'demo-software-internship',
                'summary' => 'Paid internship for students and recent graduates in tech.',
                'description' => "Work alongside experienced engineers on real projects. Gain exposure to modern web technologies and build a portfolio of work.",
                'category' => 'internships',
                'organisation' => 'TechNova Nigeria',
                'work_mode' => 'hybrid',
                'opportunity_type' => 'internship',
                'eligibility' => 'Students in penultimate/final year or recent graduates',
                'requirements' => ['Basic knowledge of programming', 'Problem-solving skills'],
                'amount_min' => 40000,
                'amount_max' => 60000,
                'currency' => 'NGN',
                'deadline_at' => $now->addDays(21)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Federal Government Scholarship',
                'slug' => 'demo-fg-scholarship',
                'summary' => 'Merit-based scholarship for undergraduate and postgraduate studies.',
                'description' => "Full scholarship covering tuition, accommodation, and living expenses for qualified Nigerian students in public institutions.",
                'category' => 'scholarships',
                'organisation' => 'Federal Ministry of Education',
                'work_mode' => 'onsite',
                'opportunity_type' => 'scholarship',
                'eligibility' => 'Nigerian students with strong academic records',
                'requirements' => ['Good academic performance', 'Admission to a public institution'],
                'deadline_at' => $now->addDays(30)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Small Business Grant',
                'slug' => 'demo-sme-grant',
                'summary' => 'Seed funding for startups and small businesses in Nigeria.',
                'description' => "Non-repayable grant to support early-stage businesses. Focus areas include agriculture, technology, and creative industries.",
                'category' => 'grants',
                'organisation' => 'SMEDAN',
                'work_mode' => 'remote',
                'opportunity_type' => 'grant',
                'eligibility' => 'Registered SMEs with viable business plans',
                'requirements' => ['CAC registration', 'Business plan'],
                'amount_min' => 250000,
                'amount_max' => 500000,
                'currency' => 'NGN',
                'deadline_at' => $now->addDays(10)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Digital Skills Training',
                'slug' => 'demo-digital-training',
                'summary' => 'Free training in web development, data analysis, and digital marketing.',
                'description' => "Intensive 8-week program with hands-on projects, mentorship, and job placement support for successful participants.",
                'category' => 'training',
                'organisation' => 'Digital Nigeria Initiative',
                'work_mode' => 'hybrid',
                'opportunity_type' => 'training',
                'eligibility' => 'Youth aged 18-35 interested in tech careers',
                'requirements' => ['Laptop access', 'Commitment to full program'],
                'deadline_at' => $now->addDays(7)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Freelance Content Writing Gigs',
                'slug' => 'demo-content-gigs',
                'summary' => 'Flexible remote writing opportunities for skilled content creators.',
                'description' => "Ongoing gig opportunities for blog posts, product descriptions, and marketing content. Pay per article with potential for long-term contracts.",
                'category' => 'gigs',
                'organisation' => 'ContentPro Hub',
                'work_mode' => 'remote',
                'opportunity_type' => 'gig',
                'eligibility' => 'Writers with proven portfolio',
                'requirements' => ['Writing samples', 'Good grammar and research skills'],
                'amount_min' => 5000,
                'amount_max' => 15000,
                'currency' => 'NGN',
                'deadline_at' => $now->addDays(45)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Business Development Support',
                'slug' => 'demo-business-support',
                'summary' => 'Free consulting and mentorship for growing businesses.',
                'description' => "Access to business advisors, market research, and networking opportunities to scale your business across Nigeria.",
                'category' => 'business-support',
                'organisation' => 'Enterprise Development Centre',
                'work_mode' => 'hybrid',
                'opportunity_type' => 'support',
                'eligibility' => 'SMEs with 1-3 years of operation',
                'requirements' => ['Business registration', 'Growth potential'],
                'deadline_at' => $now->addDays(20)->format('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Road Construction Tender',
                'slug' => 'demo-tender-construction',
                'summary' => 'Public procurement tender for rural road infrastructure project.',
                'description' => "Eligible contractors invited to bid for the construction and rehabilitation of 15km rural road network in selected LGAs.",
                'category' => 'tenders',
                'organisation' => 'State Ministry of Works',
                'work_mode' => 'onsite',
                'opportunity_type' => 'tender',
                'eligibility' => 'Registered contractors with relevant experience',
                'requirements' => ['CAC certificate', 'Tax clearance', 'Technical capability'],
                'deadline_at' => $now->addDays(12)->format('Y-m-d H:i:s'),
            ],
        ];

        foreach ($demos as $demo) {
            $category = $categories->get($demo['category'] ?? null);
            if (! $category) {
                continue;
            }

            $location = $locations->random() ?? null;

            $opportunity = Opportunity::query()->updateOrCreate(
                ['slug' => $demo['slug']],
                [
                    'title' => $demo['title'],
                    'summary' => $demo['summary'],
                    'description' => $demo['description'],
                    'category_id' => $category->id,
                    'location_id' => $location?->id,
                    'submitted_by' => $staff->id,
                    'organisation_name' => $demo['organisation'],
                    'source_url' => 'https://example.com/opportunities/'.$demo['slug'],
                    'application_url' => 'https://example.com/apply/'.$demo['slug'],
                    'application_method' => 'online',
                    'application_instructions' => 'Submit your application through the provided link with all required documents.',
                    'opportunity_type' => $demo['opportunity_type'] ?? null,
                    'work_mode' => $demo['work_mode'] ?? null,
                    'eligibility' => $demo['eligibility'] ?? null,
                    'requirements' => $demo['requirements'] ?? null,
                    'amount_min' => $demo['amount_min'] ?? null,
                    'amount_max' => $demo['amount_max'] ?? null,
                    'currency' => $demo['currency'] ?? null,
                    'deadline_at' => $demo['deadline_at'],
                    'status' => 'published',
                    'verification_status' => 'verified',
                    'published_at' => $now->subHours(2)->format('Y-m-d H:i:s'),
                    'verified_at' => $now->subHours(1)->format('Y-m-d H:i:s'),
                    'verified_by' => $verifier->id,
                ]
            );

            // Add staff review audit
            $action = config('oyafind.discovery.staff_review_action', 'publish');
            DB::table('audit_logs')->updateOrInsert(
                ['entity_type' => 'opportunity', 'entity_id' => $opportunity->id, 'action' => $action, 'actor_id' => $staff->id],
                [
                    'entity_type' => 'opportunity',
                    'entity_id' => $opportunity->id,
                    'actor_id' => $staff->id,
                    'action' => $action,
                    'metadata' => json_encode([
                        'evidence_reference' => 'https://example.com/review/'.$demo['slug'],
                        'rationale' => 'Verified against source listing and meets publication policy.',
                    ]),
                    'created_at' => $now->subHours(2)->format('Y-m-d H:i:s'),
                ]
            );

            // Add verification record
            VerificationRecord::query()->updateOrCreate(
                [
                    'opportunity_id' => $opportunity->id,
                    'reviewer_id' => $verifier->id,
                    'check_type' => 'source_details',
                ],
                [
                    'result' => 'verified',
                    'evidence_url' => 'https://example.com/source/'.$demo['slug'],
                    'evidence_summary' => 'Source listing verified and details confirmed.',
                    'evidence_rationale' => null,
                    'restricted_notes' => null,
                    'checked_at' => $now->subHours(1)->format('Y-m-d H:i:s'),
                    'created_at' => $now->subHours(1)->format('Y-m-d H:i:s'),
                ]
            );

            // Add another verification for policy compliance if needed
            $existing = VerificationRecord::query()->where('opportunity_id', $opportunity->id)->count();
            if ($existing < 2) {
                VerificationRecord::query()->create([
                    'opportunity_id' => $opportunity->id,
                    'reviewer_id' => $staff->id,
                    'result' => 'verified',
                    'check_type' => 'eligibility',
                    'evidence_url' => 'https://example.com/eligibility/'.$demo['slug'],
                    'evidence_summary' => 'Eligibility criteria confirmed.',
                    'evidence_rationale' => null,
                    'restricted_notes' => null,
                    'checked_at' => $now->subHours(1)->format('Y-m-d H:i:s'),
                    'created_at' => $now->subHours(1)->format('Y-m-d H:i:s'),
                ]);
            }
        }

        CarbonImmutable::setTestNow();
    }
}
