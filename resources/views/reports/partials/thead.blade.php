<!-- resources/views/reports/partials/thead.blade.php -->
<thead>
    <tr class="bg-gray-100">
        @foreach ($elements as $element)
        <th class="px-5 py-3 border-b-2 border-gray-200 text-gray-600 text-left text-sm uppercase font-semibold">{{ __(ucfirst($element)) }}</th>
        @endforeach
    </tr>
</thead>