'<html>

<head>
    <style>
        /* Stili per l'intestazione */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding-bottom: 40px;
            width: 100%;
            top: 0;
            border: 1px solid black;

        }

        .logo {
            width: 80px;
            height: 80px;
            margin-right: 20px;
            border: 1px solid red;
            justify-self: flex-end;
        }

        .logo img {
            width: 100%;
            height: auto;
        }

        /* Stili per il piè di pagina */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 50px;
            background-color: #f0f0f0;
            padding: 10px;
            text-align: center;
        }

        /* Stili per la pagina del preventivo */
        .page {
            margin-top: 70px;
            margin-bottom: 70px;
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <img src="./images/logo.svg" alt="webmapp logo">
        </div>
    </header>

    <div class="footer">
        <h1>Piè di pagina del preventivo</h1>
    </div>

    <div class="page">
        <h2>Pagina 1</h2>
        <p>Contenuto della pagina 1</p>
    </div>

    <div class="page">
        <h2>Pagina 2</h2>
        <p>Contenuto della pagina 2</p>
    </div>

    <div class="page">
        <h2>Pagina 3</h2>
        <p>Contenuto della pagina 3</p>
    </div>
</body>

</html>';
