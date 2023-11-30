<!DOCTYPE html>
<html>

<head>
    <title>New Story Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .header,
        .footer {
            background-color: #f5f5f5;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>New Story Created</h1>
        </div>
        <div class="content">
            <p>A new story has been created by {{ $creator->name }}.</p>
            <p>Story Title: {{ $story->name }}</p>
            <div class="content">
                <p>Story Content:</p>
                {!! $story->customer_request !!}
                <p>Link: <a href="{{ url('/resources/customer-stories/' . $story->id) }}">See the story</a></p>
            </div>
            <div class="footer">
                <p>Orchestrator©</p>
            </div>
        </div>
</body>

</html>
