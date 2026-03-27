require 'socket'

HTML = <<~HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>App Magasin Lycee - Configuration</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f8fafc;color:#1e293b}
    .header{background:linear-gradient(135deg,#1d4ed8,#7c3aed);color:#fff;padding:2rem;text-align:center}
    .header h1{font-size:2rem;font-weight:700}
    .header p{opacity:.85;margin-top:.5rem;font-size:1.1rem}
    .badge{display:inline-block;background:rgba(255,255,255,.2);border-radius:9999px;padding:.25rem .75rem;font-size:.8rem;margin-top:.75rem}
    .container{max-width:900px;margin:2rem auto;padding:0 1.5rem}
    .alert{background:#fef3c7;border:1px solid #fcd34d;border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.5rem}
    .alert h3{font-weight:600;color:#92400e;margin-bottom:.25rem}
    .alert p{color:#78350f;font-size:.9rem}
    .card{background:#fff;border-radius:1rem;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:1.5rem;margin-bottom:1.5rem}
    .card h2{font-size:1.1rem;font-weight:700;color:#1e40af;margin-bottom:1rem}
    .step{display:flex;gap:1rem;margin-bottom:1rem}
    .step-num{background:#2563eb;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;margin-top:2px}
    .step-content h4{font-weight:600;margin-bottom:.25rem}
    .step-content p{color:#64748b;font-size:.9rem;margin-bottom:.5rem}
    pre{background:#0f172a;color:#e2e8f0;border-radius:.5rem;padding:.75rem 1rem;font-size:.85rem;overflow-x:auto;margin-top:.5rem}
    .features{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-top:.5rem}
    .feature{background:#f0f9ff;border:1px solid #bae6fd;border-radius:.75rem;padding:1rem}
    .feature-icon{font-size:1.5rem;margin-bottom:.5rem}
    .feature h4{font-weight:600;font-size:.9rem;color:#0c4a6e}
    .feature p{font-size:.8rem;color:#0369a1;margin-top:.25rem}
    .creds{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.5rem}
    .cred-box{background:#f0fdf4;border:1px solid #86efac;border-radius:.5rem;padding:.75rem}
    .cred-box h4{color:#166534;font-weight:600;font-size:.85rem;margin-bottom:.5rem}
    .cred-row{display:flex;justify-content:space-between;font-size:.85rem;margin:.2rem 0}
    .cred-label{color:#64748b}
    .cred-val{font-weight:600;font-family:monospace;color:#166534}
    .stack-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;margin-top:.5rem}
    .stack-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:.75rem;text-align:center}
    .stack-item .tech{font-weight:600;font-size:.9rem}
    .stack-item .desc{font-size:.75rem;color:#64748b;margin-top:.2rem}
    footer{text-align:center;padding:2rem;color:#94a3b8;font-size:.85rem}
  </style>
</head>
<body>
<div class="header">
  <h1>App Magasin Lycee</h1>
  <p>Application de gestion des emprunts de materiel</p>
  <span class="badge">Installation requise</span>
</div>
<div class="container">
  <div class="alert">
    <h3>Installation en attente</h3>
    <p>Le serveur de previsualisation est actif. L'application Laravel necessite PHP, Composer et Node.js. Suivez les etapes ci-dessous.</p>
  </div>
  <div class="card">
    <h2>Fonctionnalites de l'application</h2>
    <div class="features">
      <div class="feature"><div class="feature-icon">&#128197;</div><h4>Reservations</h4><p>Calendrier interactif + validation admin</p></div>
      <div class="feature"><div class="feature-icon">&#128230;</div><h4>Inventaire</h4><p>Import Snipe-IT, QR codes, tracking</p></div>
      <div class="feature"><div class="feature-icon">&#128101;</div><h4>Roles</h4><p>Admin / Etudiant / Personnel</p></div>
      <div class="feature"><div class="feature-icon">&#128231;</div><h4>Emails</h4><p>Notifications Gmail automatiques</p></div>
      <div class="feature"><div class="feature-icon">&#128247;</div><h4>Scan QR</h4><p>Retour materiel par camera</p></div>
      <div class="feature"><div class="feature-icon">&#128221;</div><h4>Formulaires</h4><p>Feuilles de sortie personnalisables</p></div>
    </div>
  </div>
  <div class="card">
    <h2>Installation (1 commande)</h2>
    <div class="step">
      <div class="step-num">1</div>
      <div class="step-content">
        <h4>Ouvrir un Terminal dans le dossier du projet</h4>
        <p>Clic droit sur le dossier -> "Nouveau terminal au dossier" ou via VS Code.</p>
      </div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div class="step-content">
        <h4>Lancer le script d'installation automatique</h4>
        <p>Installe Homebrew, PHP 8.2, Composer, Node.js, MySQL, configure et demarre l'app.</p>
        <pre>chmod +x setup.sh &amp;&amp; ./setup.sh</pre>
      </div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-content">
        <h4>Configurer Gmail (optionnel)</h4>
        <p>Editez le fichier .env avec votre email et mot de passe d'application Google.</p>
        <pre>MAIL_USERNAME=votre-email@gmail.com
MAIL_PASSWORD=xxxx-xxxx-xxxx-xxxx</pre>
      </div>
    </div>
    <div class="step">
      <div class="step-num">4</div>
      <div class="step-content">
        <h4>Acceder a l'application</h4>
        <pre>http://localhost:8000</pre>
      </div>
    </div>
  </div>
  <div class="card">
    <h2>Comptes par defaut</h2>
    <div class="creds">
      <div class="cred-box">
        <h4>Administrateur</h4>
        <div class="cred-row"><span class="cred-label">Email</span><span class="cred-val">admin@lycee.fr</span></div>
        <div class="cred-row"><span class="cred-label">Mot de passe</span><span class="cred-val">Admin@1234</span></div>
      </div>
      <div class="cred-box">
        <h4>Utilisateur demo</h4>
        <div class="cred-row"><span class="cred-label">Email</span><span class="cred-val">user@lycee.fr</span></div>
        <div class="cred-row"><span class="cred-label">Mot de passe</span><span class="cred-val">password</span></div>
      </div>
    </div>
  </div>
  <div class="card">
    <h2>Stack technique</h2>
    <div class="stack-grid">
      <div class="stack-item"><div class="tech">Laravel 11</div><div class="desc">Backend PHP</div></div>
      <div class="stack-item"><div class="tech">Vue 3</div><div class="desc">Frontend JS</div></div>
      <div class="stack-item"><div class="tech">Inertia.js</div><div class="desc">SPA sans API</div></div>
      <div class="stack-item"><div class="tech">Tailwind CSS</div><div class="desc">Design</div></div>
      <div class="stack-item"><div class="tech">MySQL</div><div class="desc">Base de donnees</div></div>
      <div class="stack-item"><div class="tech">FullCalendar</div><div class="desc">Calendrier</div></div>
      <div class="stack-item"><div class="tech">Spatie Roles</div><div class="desc">Permissions</div></div>
      <div class="stack-item"><div class="tech">Gmail SMTP</div><div class="desc">Notifications</div></div>
    </div>
  </div>
</div>
<footer>App Magasin Lycee - Genere avec Claude Code</footer>
</body>
</html>
HTML

server = TCPServer.new('0.0.0.0', 8000)
loop do
  client = server.accept
  request = client.gets
  response = HTML
  client.print "HTTP/1.1 200 OK\r\n"
  client.print "Content-Type: text/html; charset=utf-8\r\n"
  client.print "Content-Length: #{response.bytesize}\r\n"
  client.print "Connection: close\r\n"
  client.print "\r\n"
  client.print response
  client.close
end
