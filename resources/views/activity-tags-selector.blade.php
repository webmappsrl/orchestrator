<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Selettore Activity Tags</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .activity-selector-container {
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

        .selector-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .selector-label {
            font-weight: bold;
            color: #333;
            white-space: nowrap;
        }

        .date-input,
        .tag-input {
            padding: 8px 12px;
            border: 2px solid #2FBDA5;
            border-radius: 4px;
            font-size: 14px;
            background-color: #fff;
            color: #333;
            cursor: pointer;
            min-width: 150px;
        }

        .tag-input {
            cursor: text;
            min-width: 200px;
        }

        .date-input:focus,
        .tag-input:focus {
            outline: none;
            border-color: #24a085;
            box-shadow: 0 0 5px rgba(47, 189, 165, 0.3);
        }

        .submit-button {
            padding: 8px 20px;
            background-color: #2FBDA5;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-button:hover {
            background-color: #24a085;
        }

        @media (max-width: 768px) {
            .selector-wrapper {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .selector-group {
                flex-direction: column;
                align-items: stretch;
            }

            .date-input,
            .tag-input {
                min-width: auto;
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="activity-selector-container">
        <div class="selector-title">📊 Activity Tags Dashboard</div>
        
        <form method="POST" action="/set-activity-tags-filters" class="selector-wrapper">
            @csrf
            <div class="selector-group">
                <label class="selector-label" for="start-date">Da:</label>
                <input type="date" name="start_date" id="start-date" class="date-input" 
                       value="{{ $startDate }}" required>
            </div>

            <div class="selector-group">
                <label class="selector-label" for="end-date">A:</label>
                <input type="date" name="end_date" id="end-date" class="date-input" 
                       value="{{ $endDate }}" required>
            </div>

            <div class="selector-group">
                <label class="selector-label" for="tag-filter">Tag:</label>
                <input type="text" name="tag_filter" id="tag-filter" class="tag-input" 
                       value="{{ $selectedTagFilter }}" placeholder="Filtra per nome tag...">
            </div>

            <button type="submit" class="submit-button">Aggiorna</button>
        </form>
    </div>
</body>

</html>

