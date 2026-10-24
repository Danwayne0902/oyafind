<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F7F9FC" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#101C3D" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('favicon-dark.svg') }}" type="image/svg+xml" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="{{ asset('icons/favicon-light.png') }}" type="image/png" sizes="32x32" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('icons/favicon-dark.png') }}" type="image/png" sizes="32x32" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" href="{{ asset('icons/oyafind-apple-touch.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="description" content="Explore public job, learning and business opportunities on OyaFind. Browse without an account.">
    <title>OyaFind — Real opportunities. Closer to you.</title>
    @vite('resources/js/app.js')
</head>
<body>
    <div id="app"></div>
</body>
</html>
