<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin') · OyaFind</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        :root {
            --navy: #101c3d;
            --navy-soft: #1b2b55;
            --green: #0d9478;
            --green-dark: #08745f;
            --lime: #c6ff00;
            --ink: #101c3d;
            --muted: #64748b;
            --line: #e2e8f0;
            --surface: #ffffff;
            --warm: #eef3fa;
            --danger: #b42318;
            --danger-soft: #fef3f2;
            --amber: #a15c07;
            --amber-soft: #fffaeb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f6f8fc;
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
            line-height: 1.5;
        }
        a { color: var(--green-dark); }
        .adm-top {
            background: var(--navy);
            color: #fff;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 24px;
            min-height: 60px;
            flex-wrap: wrap;
        }
        .adm-brand { font-weight: 700; letter-spacing: -.01em; font-size: 1.05rem; display: flex; align-items: center; gap: 10px; }
        .adm-brand span { background: var(--lime); color: var(--navy); font-size: .68rem; font-weight: 800; letter-spacing: .08em; padding: 3px 7px; border-radius: 999px; text-transform: uppercase; }
        .adm-nav { display: flex; gap: 6px; margin-left: auto; align-items: center; flex-wrap: wrap; }
        .adm-nav a, .adm-nav button {
            color: rgba(255,255,255,.82);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: .9rem;
            background: none;
            border: 0;
            cursor: pointer;
            font-family: inherit;
        }
        .adm-nav a:hover, .adm-nav button:hover { background: rgba(255,255,255,.1); color: #fff; }
        .adm-nav a.is-active { background: rgba(255,255,255,.14); color: #fff; }
        .adm-wrap { max-width: 1180px; margin: 0 auto; padding: 28px 24px 64px; }
        .adm-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
        h1 { font-size: 1.55rem; margin: 0 0 4px; letter-spacing: -.02em; }
        h2 { font-size: 1.1rem; margin: 0 0 14px; }
        .adm-sub { color: var(--muted); margin: 0; font-size: .93rem; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            border: 1px solid transparent; border-radius: 9px; padding: 9px 15px;
            font-size: .9rem; font-weight: 600; text-decoration: none; cursor: pointer;
            font-family: inherit; line-height: 1.2;
        }
        .btn-primary { background: var(--green); color: #fff; }
        .btn-primary:hover { background: var(--green-dark); }
        .btn-ghost { background: #fff; border-color: var(--line); color: var(--ink); }
        .btn-ghost:hover { background: var(--warm); }
        .btn-danger { background: var(--danger-soft); color: var(--danger); border-color: #fecdca; }
        .btn-danger:hover { background: #fee4e2; }
        .btn-sm { padding: 6px 10px; font-size: .82rem; border-radius: 7px; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .card-pad { padding: 20px; }
        .flash { border-radius: 10px; padding: 12px 15px; margin-bottom: 18px; font-size: .92rem; }
        .flash-ok { background: #ecfdf3; color: #05603a; border: 1px solid #abefc6; }
        .flash-error { background: var(--danger-soft); color: var(--danger); border: 1px solid #fecdca; }
        .flash-error ul { margin: 6px 0 0; padding-left: 18px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; }
        .stat b { display: block; font-size: 1.5rem; letter-spacing: -.02em; }
        .stat span { color: var(--muted); font-size: .82rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; }
        .tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
        .tabs a { padding: 7px 12px; border-radius: 999px; text-decoration: none; color: var(--muted); font-size: .87rem; font-weight: 600; border: 1px solid transparent; }
        .tabs a.is-active { background: var(--navy); color: #fff; }
        .tabs a:not(.is-active):hover { background: #fff; border-color: var(--line); color: var(--ink); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: .74rem; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); padding: 12px 14px; border-bottom: 1px solid var(--line); }
        td { padding: 13px 14px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        tr:last-child td { border-bottom: 0; }
        td .t { font-weight: 600; display: block; margin-bottom: 2px; }
        td .m { color: var(--muted); font-size: .84rem; }
        .badge { display: inline-block; font-size: .74rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; letter-spacing: .02em; }
        .badge-draft { background: var(--warm); color: var(--navy-soft); }
        .badge-reviewed { background: var(--amber-soft); color: var(--amber); }
        .badge-published { background: #ecfdf3; color: #05603a; }
        .row-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
        .empty { padding: 56px 24px; text-align: center; color: var(--muted); }
        form.inline { display: inline; }
        .grid-form { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 22px; align-items: start; }
        @media (max-width: 900px) { .grid-form { grid-template-columns: 1fr; } .stats { grid-template-columns: repeat(2, 1fr); } }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-weight: 600; font-size: .88rem; margin-bottom: 5px; }
        .field .hint { color: var(--muted); font-size: .8rem; margin: 5px 0 0; }
        input[type=text], input[type=url], input[type=number], input[type=datetime-local], select, textarea {
            width: 100%; border: 1px solid #cfd8e5; border-radius: 9px; padding: 9px 11px;
            font-family: inherit; font-size: .93rem; color: var(--ink); background: #fff;
        }
        input:focus, select:focus, textarea:focus { outline: 2px solid rgba(13,148,120,.28); border-color: var(--green); }
        textarea { min-height: 110px; resize: vertical; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .err { color: var(--danger); font-size: .82rem; margin-top: 4px; }
        .workflow { position: sticky; top: 20px; }
        .workflow .step { display: flex; gap: 10px; padding: 11px 0; border-bottom: 1px solid #eef2f7; font-size: .88rem; }
        .workflow .step:last-child { border-bottom: 0; }
        .workflow .dot { width: 9px; height: 9px; border-radius: 50%; background: #cbd5e1; margin-top: 6px; flex: none; }
        .workflow .step.done .dot { background: var(--green); }
        .workflow .step.ready .dot { background: var(--lime); box-shadow: 0 0 0 3px rgba(198,255,0,.3); }
        .workflow .step small { color: var(--muted); display: block; }
        .workflow form { margin-top: 10px; }
        .pager { display: flex; gap: 10px; align-items: center; justify-content: space-between; padding: 14px; }
        .pager a { font-size: .88rem; }
        .searchbar { display: flex; gap: 8px; margin-bottom: 14px; }
        .searchbar input { max-width: 320px; }
        .note { background: var(--amber-soft); color: #7a4206; border: 1px solid #fedf89; border-radius: 10px; padding: 12px 14px; font-size: .86rem; margin-bottom: 18px; }
        details summary { cursor: pointer; font-weight: 600; font-size: .88rem; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    </style>
</head>
<body>
    <header class="adm-top">
        <div class="adm-brand">OyaFind <span>Admin</span></div>
        <nav class="adm-nav">
            <a href="{{ route('admin.opportunities.index') }}" @class(['is-active' => request()->routeIs('admin.opportunities.*')])>Listings</a>
            <a href="{{ route('admin.opportunities.create') }}">New listing</a>
            <a href="/" target="_blank" rel="noopener">View site</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        </nav>
    </header>

    <main class="adm-wrap">
        @if (session('status'))
            <div class="flash flash-ok" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash-error" role="alert">
                <strong>Please check the form.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
