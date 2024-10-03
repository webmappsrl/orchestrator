<!-- report/partials/table.blade.php -->
<div id="{{ $id }}" class="tab-content">
    <h3 class="text-xl font-bold text-center mb-4">{{ $title }}</h3>
    <table class="min-w-full leading-normal table-auto border-collapse border border-gray-400">
        @include('reports.partials.thead', ['elements' => $thead])
        <tbody>
            @foreach($tbody as $data)
            <tr class="bg-white border-b">
                <td class="px-5 py-4 text-sm font-bold">{{ $data['developerName'] }}</td>
                @foreach (App\Enums\StoryStatus::values() as $status)
                <td class="px-5 py-4 text-sm">{{ $data[$status . '_total'] ?? 0 }}</td>
                @endforeach
                <td class="px-5 py-4 text-sm font-bold">{{ $data['total'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>