@extends('admin.layout')

@section('title', 'Listings')

@section('content')
    <div class="adm-head">
        <div>
            <h1>Listings</h1>
            <p class="adm-sub">Create, review and publish the opportunities shown on OyaFind.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.opportunities.create') }}">+ New listing</a>
    </div>

    @unless ($canPublish)
        <div class="note">
            Publishing requires an allowlisted verifier account that is different from the staff reviewer.
            Sign in with the verifier account to publish reviewed listings.
        </div>
    @endunless

    <div class="stats">
        <div class="stat"><b>{{ $counts['all'] }}</b><span>All listings</span></div>
        <div class="stat"><b>{{ $counts['draft'] }}</b><span>Drafts</span></div>
        <div class="stat"><b>{{ $counts['reviewed'] }}</b><span>Awaiting publish</span></div>
        <div class="stat"><b>{{ $counts['published'] }}</b><span>Live</span></div>
    </div>

    <div class="tabs">
        <a href="{{ route('admin.opportunities.index') }}" @class(['is-active' => ! $status])>All</a>
        <a href="{{ route('admin.opportunities.index', ['status' => 'draft']) }}" @class(['is-active' => $status === 'draft'])>Drafts</a>
        <a href="{{ route('admin.opportunities.index', ['status' => 'reviewed']) }}" @class(['is-active' => $status === 'reviewed'])>Awaiting publish</a>
        <a href="{{ route('admin.opportunities.index', ['status' => 'published']) }}" @class(['is-active' => $status === 'published'])>Live</a>
    </div>

    <form class="searchbar" method="GET" action="{{ route('admin.opportunities.index') }}">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <label class="sr-only" for="q">Search listings</label>
        <input id="q" type="text" name="q" value="{{ $search }}" placeholder="Search title or organisation">
        <button class="btn btn-ghost" type="submit">Search</button>
    </form>

    <div class="card">
        @if ($opportunities->isEmpty())
            <div class="empty">
                <p>No listings here yet.</p>
                <a class="btn btn-primary" href="{{ route('admin.opportunities.create') }}">Create the first listing</a>
            </div>
        @else
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($opportunities as $opportunity)
                            <tr>
                                <td>
                                    <span class="t">{{ $opportunity->title }}</span>
                                    <span class="m">{{ $opportunity->organisation_name }}</span>
                                </td>
                                <td>{{ $opportunity->category?->name ?? '—' }}</td>
                                <td>{{ $opportunity->location?->name ?? 'Nationwide' }}</td>
                                <td>
                                    <span class="badge badge-{{ $opportunity->status }}">{{ ucfirst($opportunity->status ?? 'draft') }}</span>
                                </td>
                                <td>
                                    {{ $opportunity->deadline_at?->format('j M Y') ?? 'No deadline' }}
                                    @if ($opportunity->deadline_at && $opportunity->deadline_at->isPast())
                                        <span class="m" style="color:var(--danger)">Expired</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-actions">
                                        @if ($opportunity->status === 'published')
                                            <a class="btn btn-ghost btn-sm" href="/opportunities/{{ $opportunity->slug }}" target="_blank" rel="noopener">View</a>
                                        @endif
                                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.opportunities.edit', $opportunity) }}">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pager">
                <span class="m">Page {{ $opportunities->currentPage() }} of {{ max(1, $opportunities->lastPage()) }}</span>
                <span>
                    @if ($opportunities->previousPageUrl())
                        <a class="btn btn-ghost btn-sm" href="{{ $opportunities->previousPageUrl() }}">Previous</a>
                    @endif
                    @if ($opportunities->nextPageUrl())
                        <a class="btn btn-ghost btn-sm" href="{{ $opportunities->nextPageUrl() }}">Next</a>
                    @endif
                </span>
            </div>
        @endif
    </div>
@endsection
