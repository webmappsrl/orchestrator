@php
    use App\Models\Project;
    
    $user = auth()->user();
    $favoriteProjects = $user->getFavoriteItems(Project::class)->get();
    $epics = $user->epics()->get();
    $urlNova = url('/resources/projects');
@endphp

<!DOCTYPE html>
<html>

<body>
    <h1 style="margin-bottom: 10px; font-weight: bold;">Favorite Projects</h1>

    <ul>
        @foreach ($favoriteProjects as $project)
            <li style="margin-bottom:5px; color:lightseagreen">
                <a href="{{ $urlNova . '/' . $project->id }}">
                    {{ $project->name }}
                </a>
            </li>
        @endforeach
    </ul>
</body>

</html>
