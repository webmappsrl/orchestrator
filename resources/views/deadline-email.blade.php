<!-- resources/views/email_template.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <title>Email for {{ $customer->name }}
    </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f2f2f2;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }


        p {
            margin: 0 0 15px;
        }

        .task-list {
            list-style: decimal;
            padding-left: 30px;
        }

        .task-list li {
            margin: 0 0 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Oggetto: Attività svolte {{ $deadline->title }}
            entro il {{ $deadline->due_date->format('Y-m-d') }}
        </h1>
        <p></p>

        <p>Gentile {{ $customer->name }},</p>

        <p>Desideriamo informarla sull'attuale stato di avanzamento delle attività che abbiamo svolto.</p>

        @if ($doneStories->count() > 0)
            <h2>Attività concluse:</h2>
            <ul class="task-list">
                @foreach ($doneStories as $story)
                    <li>({{ $story->name }}) {{ $story->customer_request }}</li>
                @endforeach
            </ul>
        @endif

        @if ($progressStories->count() > 0)
            <h2>Attività in corso:</h2>
            <ul class="task-list">
                @foreach ($progressStories as $story)
                    <li>({{ $story->name }}) {{ $story->customer_request }}</li>
                @endforeach
            </ul>
        @endif

        @if ($storiesToStart->count() > 0)
            <h2>Attività da iniziare:</h2>
            <ul class="task-list">
                @foreach ($storiesToStart as $story)
                    <li>({{ $story->name }}) {{ $story->customer_request }}</li>
                @endforeach
            </ul>
        @endif

        <p>Il nostro team di sviluppo sta lavorando diligentemente per completare le attività in corso e avviare quelle
            da iniziare nel minor tempo possibile. Ci impegniamo a offrirle un prodotto di alta qualità che soddisfi le
            sue esigenze.</p>

        <p>Restiamo a disposizione per ulteriori informazioni o domande. Apprezziamo la sua fiducia nel nostro lavoro.
        </p>

        <p>Cordiali saluti,</p>

        <p>Il team di sviluppo Webmapp s.r.l.</p>
    </div>
</body>

</html>
