<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta Information -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="shortcut icon" href="{{ asset('/vendor/laravel-package/favicon.ico') }}" />

    <meta name="robots" content="noindex, nofollow">

    <title>LaravelPackage{{ config('app.name') ? ' - ' . config('app.name') : '' }}</title>

    <!-- Style sheets-->
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="{{ asset(mix('app.css', 'vendor/laravel-package')) }}" rel="stylesheet" type="text/css">
</head>
<body>

<div id="laravel-package" v-cloak>
    <h1>Laravel Package{{ config('app.name') ? ' - ' . config('app.name') : '' }}</h1>
</div>

<!-- Global LaravelPackage Object -->
<script>
    window.LaravelPackage = @json($scriptVariables);
</script>

<script src="{{ asset(mix('app.js', 'vendor/laravel-package')) }}"></script>

</body>
</html>
