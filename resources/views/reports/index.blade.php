<!-- resources/views/reports/index.blade.php -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Story and Status</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

    <div class="max-w-71xl mx-auto p-4 sm:p-6 lg:p-8">
        <h1 class="text-3xl font-bold text-center mb-6">Report per {{ $year }}</h1>

        @if(isset($error))
        <p class="text-red-500 text-center">{{ $error }}</p>
        @else
        <!-- Include la tabella per tipo -->
        @include('reports.story-type')

        <!-- Include la tabella per stato -->
        @include('reports.story-status')
        @include('reports.story-user') <!-- Tabella per Sviluppatori -->
        @endif
    </div>

</body>

</html>