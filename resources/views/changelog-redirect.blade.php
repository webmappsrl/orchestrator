<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url={{ $redirectUrl }}">
    <title>{{ __('Redirecting...') }}</title>
</head>
<body>
    <script>
        window.location.href = '{{ $redirectUrl }}';
    </script>
    <p>{{ __('Reindirizzamento...') }}</p>
</body>
</html>

