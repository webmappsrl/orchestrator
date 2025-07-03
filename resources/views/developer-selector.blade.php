<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Selettore Developer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .developer-selector-container {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .selector-title {
            color: #2FBDA5;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            text-align: center;
        }

        .selector-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .selector-label {
            font-weight: bold;
            color: #333;
        }

        .developer-select {
            padding: 8px 12px;
            border: 2px solid #2FBDA5;
            border-radius: 4px;
            font-size: 14px;
            background-color: #fff;
            color: #333;
            cursor: pointer;
            min-width: 200px;
        }

        .developer-select:focus {
            outline: none;
            border-color: #24a085;
            box-shadow: 0 0 5px rgba(47, 189, 165, 0.3);
        }





        @media (max-width: 768px) {
            .selector-wrapper {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .developer-select {
                min-width: auto;
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="developer-selector-container">
        <div class="selector-title">🔍 Visualizza Dashboard Developer</div>
        
        <form method="POST" action="/set-dashboard-developer" class="selector-wrapper">
            @csrf
            <label class="selector-label" for="developer-select">Visualizza dashboard di:</label>
            
            <select name="developer_id" id="developer-select" class="developer-select" onchange="this.form.submit()">
                <option value="" {{ !session('selected_developer_id') ? 'selected' : '' }}>
                    {{ $currentUser->name }} (La mia dashboard)
                </option>
                @foreach($developers as $developer)
                    @if($developer->id !== $currentUser->id)
                        <option value="{{ $developer->id }}" 
                            {{ session('selected_developer_id') == $developer->id ? 'selected' : '' }}>
                            {{ $developer->name }}
                        </option>
                    @endif
                @endforeach
            </select>
        </form>


    </div>

    
</body>

</html> 