<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveOpportunityRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Opportunity;
use App\Services\AdminOpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class OpportunityController extends Controller
{
    public function __construct(
        private readonly AdminOpportunityService $listings,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $opportunities = Opportunity::query()
            ->with(['category:id,name', 'location:id,name'])
            ->when(in_array($status, ['draft', 'reviewed', 'published'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($match) use ($search): void {
                    $match->where('title', 'like', '%'.$search.'%')
                        ->orWhere('organisation_name', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'reviewed' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.opportunities.index', [
            'opportunities' => $opportunities,
            'status' => $status,
            'search' => $search,
            'counts' => [
                'all' => Opportunity::query()->count(),
                'draft' => Opportunity::query()->where('status', 'draft')->count(),
                'reviewed' => Opportunity::query()->where('status', 'reviewed')->count(),
                'published' => Opportunity::query()->where('status', 'published')->count(),
            ],
            'canReview' => $request->user()?->isStaffReviewer() === true,
            'canPublish' => $request->user()?->isPublicationVerifier() === true,
        ]);
    }

    public function create(): View
    {
        return view('admin.opportunities.form', [
            'opportunity' => new Opportunity([
                'status' => 'draft',
                'currency' => 'NGN',
            ]),
            'canReview' => request()->user()?->isStaffReviewer() === true,
            'canPublish' => request()->user()?->isPublicationVerifier() === true,
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveOpportunityRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data = $this->withDeadline($data);

        $opportunity = Opportunity::query()->create([
            ...$data,
            'slug' => $this->listings->uniqueSlug($data['title']),
            'submitted_by' => $request->user()->id,
            'status' => 'draft',
            'verification_status' => null,
            'view_count' => 0,
        ]);

        return Redirect::route('admin.opportunities.edit', $opportunity)
            ->with('status', 'Draft saved. Review it, then send it for verification.');
    }

    public function edit(Opportunity $opportunity): View
    {
        return view('admin.opportunities.form', [
            'opportunity' => $opportunity,
            'canReview' => request()->user()?->isStaffReviewer() === true,
            'canPublish' => request()->user()?->isPublicationVerifier() === true,
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveOpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        if (in_array($opportunity->status, ['reviewed', 'published'], true)) {
            return Redirect::back()->withErrors([
                'title' => 'Withdraw the listing before editing it, so the published evidence stays accurate.',
            ]);
        }

        $data = $this->withDeadline($request->validated());
        $opportunity->forceFill($data)->save();

        return Redirect::route('admin.opportunities.edit', $opportunity)
            ->with('status', 'Listing updated.');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $opportunity->delete();

        return Redirect::route('admin.opportunities.index')
            ->with('status', 'Listing deleted. It no longer appears in the public feed.');
    }

    public function review(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $validated = $request->validate([
            'evidence_reference' => ['nullable', 'string', 'max:2048'],
            'rationale' => ['nullable', 'string', 'max:1000'],
        ]);

        if (trim((string) ($validated['evidence_reference'] ?? '')) === ''
            && trim((string) ($validated['rationale'] ?? '')) === '') {
            return Redirect::back()->withErrors([
                'rationale' => 'Record the source you checked, or a short rationale, before approving.',
            ]);
        }

        try {
            $this->listings->submitForReview(
                $opportunity,
                $request->user(),
                (string) ($validated['evidence_reference'] ?? ''),
                (string) ($validated['rationale'] ?? ''),
            );
        } catch (Throwable $exception) {
            return Redirect::back()->withErrors(['review' => $exception->getMessage()]);
        }

        return Redirect::route('admin.opportunities.edit', $opportunity)
            ->with('status', 'Staff approval recorded. A separate verifier can now publish it.');
    }

    public function publish(Request $request, Opportunity $opportunity): RedirectResponse
    {
        try {
            $this->listings->publish($opportunity, $request->user());
        } catch (Throwable $exception) {
            return Redirect::back()->withErrors(['publish' => $exception->getMessage()]);
        }

        return Redirect::route('admin.opportunities.edit', $opportunity)
            ->with('status', 'Published. The listing is now live in the public feed.');
    }

    public function withdraw(Request $request, Opportunity $opportunity): RedirectResponse
    {
        try {
            $this->listings->withdraw($opportunity);
        } catch (Throwable $exception) {
            return Redirect::back()->withErrors(['publish' => $exception->getMessage()]);
        }

        return Redirect::route('admin.opportunities.edit', $opportunity)
            ->with('status', 'Listing withdrawn from the public feed.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDeadline(array $data): array
    {
        $days = $data['duration_days'] ?? null;
        unset($data['duration_days']);

        if (blank($data['deadline_at'] ?? null) && $days !== null) {
            $data['deadline_at'] = now()->addDays((int) $days)->endOfDay();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'locations' => Location::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']),
        ];
    }
}
