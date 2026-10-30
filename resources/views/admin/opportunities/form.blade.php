@extends('admin.layout')

@section('title', $opportunity->exists ? 'Edit listing' : 'New listing')

@php($editable = ! $opportunity->exists || $opportunity->status === 'draft')

@section('content')
    <div class="adm-head">
        <div>
            <h1>{{ $opportunity->exists ? 'Edit listing' : 'New listing' }}</h1>
            <p class="adm-sub">
                @if ($opportunity->exists)
                    <span class="badge badge-{{ $opportunity->status }}">{{ ucfirst($opportunity->status) }}</span>
                    @if ($opportunity->status === 'published')
                        · Live at <a href="/opportunities/{{ $opportunity->slug }}" target="_blank" rel="noopener">/opportunities/{{ $opportunity->slug }}</a>
                    @endif
                @else
                    Save a draft first. Nothing is public until it is reviewed and published.
                @endif
            </p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.opportunities.index') }}">← All listings</a>
    </div>

    <div class="grid-form">
        <div class="card card-pad">
            @unless ($editable)
                <div class="note">
                    This listing is {{ $opportunity->status }}. Withdraw it from the public feed before editing, so the published evidence stays accurate.
                </div>
            @endunless

            <form method="POST"
                  action="{{ $opportunity->exists ? route('admin.opportunities.update', $opportunity) : route('admin.opportunities.store') }}">
                @csrf
                @if ($opportunity->exists)
                    @method('PUT')
                @endif

                <div class="field">
                    <label for="title">Title</label>
                    <input id="title" type="text" name="title" maxlength="200" required
                           value="{{ old('title', $opportunity->title) }}" @disabled(! $editable)>
                    @error('title')<p class="err">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="summary">Short summary</label>
                    <input id="summary" type="text" name="summary" maxlength="300" required
                           value="{{ old('summary', $opportunity->summary) }}" @disabled(! $editable)>
                    <p class="hint">One line shown on cards and in search results. Maximum 300 characters.</p>
                    @error('summary')<p class="err">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required @disabled(! $editable)>{{ old('description', $opportunity->description) }}</textarea>
                    <p class="hint">Write your own short description and link to the source. Do not copy full contact details.</p>
                    @error('description')<p class="err">{{ $message }}</p>@enderror
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required @disabled(! $editable)>
                            <option value="">Choose a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $opportunity->category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<p class="err">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="location_id">Location</label>
                        <select id="location_id" name="location_id" @disabled(! $editable)>
                            <option value="">Nationwide / not stated</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) old('location_id', $opportunity->location_id) === $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('location_id')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="field">
                    <label for="organisation_name">Organisation</label>
                    <input id="organisation_name" type="text" name="organisation_name" maxlength="200" required
                           value="{{ old('organisation_name', $opportunity->organisation_name) }}" @disabled(! $editable)>
                    @error('organisation_name')<p class="err">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="source_url">Source link</label>
                    <input id="source_url" type="url" name="source_url" maxlength="2048"
                           value="{{ old('source_url', $opportunity->source_url) }}" @disabled(! $editable)>
                    <p class="hint">Where readers can confirm the listing. Required before publishing.</p>
                    @error('source_url')<p class="err">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="application_url">Application link <span class="hint" style="display:inline">(optional)</span></label>
                    <input id="application_url" type="url" name="application_url" maxlength="2048"
                           value="{{ old('application_url', $opportunity->application_url) }}" @disabled(! $editable)>
                    @error('application_url')<p class="err">{{ $message }}</p>@enderror
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="deadline_at">Deadline</label>
                        <input id="deadline_at" type="datetime-local" name="deadline_at"
                               value="{{ old('deadline_at', $opportunity->deadline_at?->format('Y-m-d\TH:i')) }}" @disabled(! $editable)>
                        @error('deadline_at')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="duration_days">Or set a duration</label>
                        <input id="duration_days" type="number" name="duration_days" min="1" max="365"
                               placeholder="e.g. 30 days" value="{{ old('duration_days') }}" @disabled(! $editable)>
                        <p class="hint">Used only when no deadline is given.</p>
                        @error('duration_days')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="work_mode">Work mode</label>
                        <select id="work_mode" name="work_mode" @disabled(! $editable)>
                            <option value="">Not stated</option>
                            @foreach (['onsite' => 'On-site', 'hybrid' => 'Hybrid', 'remote' => 'Remote'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('work_mode', $opportunity->work_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="opportunity_type">Type</label>
                        <input id="opportunity_type" type="text" name="opportunity_type" maxlength="60"
                               placeholder="Full-time, fellowship…" value="{{ old('opportunity_type', $opportunity->opportunity_type) }}" @disabled(! $editable)>
                    </div>
                </div>

                <div class="field">
                    <label for="eligibility">Eligibility</label>
                    <textarea id="eligibility" name="eligibility" style="min-height:80px" @disabled(! $editable)>{{ old('eligibility', $opportunity->eligibility) }}</textarea>
                </div>

                <div class="field">
                    <label for="application_instructions">How to apply</label>
                    <textarea id="application_instructions" name="application_instructions" style="min-height:80px" @disabled(! $editable)>{{ old('application_instructions', $opportunity->application_instructions) }}</textarea>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="amount_min">Amount from</label>
                        <input id="amount_min" type="number" step="0.01" name="amount_min" value="{{ old('amount_min', $opportunity->amount_min) }}" @disabled(! $editable)>
                    </div>
                    <div class="field">
                        <label for="amount_max">Amount to</label>
                        <input id="amount_max" type="number" step="0.01" name="amount_max" value="{{ old('amount_max', $opportunity->amount_max) }}" @disabled(! $editable)>
                    </div>
                </div>

                <div class="field" style="max-width:120px">
                    <label for="currency">Currency</label>
                    <input id="currency" type="text" name="currency" maxlength="3" value="{{ old('currency', $opportunity->currency ?? 'NGN') }}" @disabled(! $editable)>
                </div>

                @if ($editable)
                    <button class="btn btn-primary" type="submit">{{ $opportunity->exists ? 'Save changes' : 'Save draft' }}</button>
                @endif
            </form>
        </div>

        <aside class="card card-pad workflow">
            <h2>Workflow</h2>

            <div class="step {{ $opportunity->status !== null ? 'done' : 'ready' }}">
                <span class="dot"></span>
                <div>
                    <strong>1. Draft</strong>
                    <small>Written and saved by staff.</small>
                </div>
            </div>

            <div class="step {{ in_array($opportunity->status, ['reviewed', 'published'], true) ? 'done' : ($opportunity->status === 'draft' ? 'ready' : '') }}">
                <span class="dot"></span>
                <div>
                    <strong>2. Staff approval</strong>
                    <small>Record what you checked against the source.</small>
                </div>
            </div>

            <div class="step {{ $opportunity->status === 'published' ? 'done' : ($opportunity->status === 'reviewed' ? 'ready' : '') }}">
                <span class="dot"></span>
                <div>
                    <strong>3. Publish</strong>
                    <small>A separate allowlisted verifier confirms and publishes.</small>
                </div>
            </div>

            @if ($opportunity->exists && $opportunity->status === 'draft')
                @if ($canReview)
                    <form method="POST" action="{{ route('admin.opportunities.review', $opportunity) }}">
                        @csrf
                        <div class="field">
                            <label for="evidence_reference">Source checked</label>
                            <input id="evidence_reference" type="text" name="evidence_reference" maxlength="2048"
                                   value="{{ old('evidence_reference', $opportunity->source_url) }}" placeholder="https://…">
                        </div>
                        <div class="field">
                            <label for="rationale">Rationale</label>
                            <input id="rationale" type="text" name="rationale" maxlength="1000"
                                   placeholder="Title, organisation and deadline match the source">
                        </div>
                        <button class="btn btn-primary" type="submit">Approve for publishing</button>
                    </form>
                @else
                    <p class="hint">Your account is not on the staff reviewer allowlist, so it cannot approve listings.</p>
                @endif
            @endif

            @if ($opportunity->exists && $opportunity->status === 'reviewed')
                @if ($canPublish)
                    <form method="POST" action="{{ route('admin.opportunities.publish', $opportunity) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Publish listing</button>
                    </form>
                @else
                    <p class="hint">Sign in with the verifier account to publish this listing.</p>
                @endif
            @endif

            @if ($opportunity->exists && $opportunity->status === 'published')
                <form method="POST" action="{{ route('admin.opportunities.withdraw', $opportunity) }}">
                    @csrf
                    <button class="btn btn-ghost" type="submit">Withdraw from public feed</button>
                </form>
            @endif

            @if ($opportunity->exists)
                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
                <p class="hint">
                    Last updated {{ $opportunity->updated_at?->diffForHumans() ?? 'just now' }}.<br>
                    Slug: {{ $opportunity->slug }}
                </p>

                <form method="POST" action="{{ route('admin.opportunities.destroy', $opportunity) }}"
                      onsubmit="return confirm('Delete this listing? It will disappear from the public feed.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm" type="submit">Delete listing</button>
                </form>
            @endif
        </aside>
    </div>
@endsection
