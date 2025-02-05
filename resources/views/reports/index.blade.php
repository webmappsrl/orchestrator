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
        @if(isset($year))
        <h1 class="text-3xl font-bold text-center mb-6">Report per {{ $year }}</h1>
        @endif

        @if(isset($error))
        <p class="text-red-500 text-center">{{ $error }}</p>
        @else
        @include('reports.tab1-type')
        @include('reports.tab2-status')
        @include('reports.tab3-dev-status')
        @include('reports.tab4-status-dev')
        @include('reports.tab5-customer-status')
        @include('reports.tab6-status-customer')
        @include('reports.tab7-tag-customer')
        @include('reports.tab8-customer-tag')
        @include('reports.tab9-tag-type')
        @include('reports.tab10-dev-type')
        @endif
    </div>

</body>

</html>