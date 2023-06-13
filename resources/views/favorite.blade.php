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
    <div style="padding:10px;">
        <h1 style="margin-bottom: 10px; font-weight: bold;">Favorite Projects</h1>

        <ul>
            @foreach ($favoriteProjects as $project)
                <li onmouseover="this.style.color = 'green'; this.style.fontWeight = 'bold';"
                    onmouseout="this.style.color = 'lightseagreen';  this.style.fontWeight = 'normal';"
                    style="margin-bottom:5px; color:lightseagreen; font-size:14px; transition: all .2s ease-out;">
                    <a href="{{ $urlNova . '/' . $project->id }}">
                        {{ $project->name }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

</body>

</html>
