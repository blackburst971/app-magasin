<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance en cours</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #4c1d95 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 1.5rem;
            padding: 3rem 2.5rem;
            text-align: center;
            max-width: 420px;
            width: 90%;
        }
        .icon {
            width: 72px; height: 72px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        p  { font-size: 0.95rem; opacity: 0.8; line-height: 1.6; }
        .retry {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔧</div>
        <h1>Maintenance en cours</h1>
        <p>L'application est temporairement indisponible pour une mise à jour.<br>Elle sera de retour dans quelques instants.</p>
        <p class="retry">Réessayez dans {{ $retryAfter ?? 60 }} secondes.</p>
    </div>
</body>
</html>
