@php
    use App\Models\Project;
    
    $user = auth()->user();
    $favoriteProjects = $user->getFavoriteItems(Project::class)->get();
    $urlNova = url('/resources/projects');
@endphp

<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            background-color: #f2f2f2;
            font-family: Arial, sans-serif;
        }

        h1 {
            color: #343434ad;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        table {
            background-color: #fff;
            border-collapse: collapse;
            margin-bottom: 10px;
            width: 100%;
        }

        th {
            background-color: #2FBDA5;
            color: #fff;
            font-size: 14px;
            font-weight: bold;
            padding: 5px;
            text-align: left;
        }

        td {
            border: 1px solid #ddd;
            padding: 5px;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        a {
            color: #333;
            text-decoration: none;
        }

        a:hover {
            color: #666;
        }
    </style>
</head>

<body>
    <div style="padding:10px;">
        <h1>Favorite Projects</h1>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>SAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($favoriteProjects as $project)
                    <tr onclick="window.location='{{ $urlNova . '/' . $project->id }}';" style="cursor: pointer;">
                        <td>{{ $project->id }}</td>
                        <td>{{ $project->name }}</td>
                        <td>{{ $project->wip() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>

</html>
