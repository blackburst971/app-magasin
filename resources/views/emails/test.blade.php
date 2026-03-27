<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.header{background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.badge{display:inline-block;background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold;margin-bottom:20px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;color:#374151;width:40%}
.success-box{background:#d1fae5;border:1px solid #6ee7b7;border-radius:6px;padding:14px;margin:20px 0;font-size:14px;color:#065f46}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>Test de configuration email</h1></div>
  <div class="content">
    <span class="badge">Test</span>
    <div class="success-box">
      ✅ <strong>Félicitations !</strong> Votre configuration email fonctionne correctement.
      Cet email a été envoyé depuis <strong>{{ config('app.name') }}</strong>.
    </div>
    <p>Si vous recevez cet email, cela signifie que votre fichier <code>.env</code> est correctement configuré pour l'envoi de mails.</p>
    <table class="info-table">
      <tr><td>Application</td><td>{{ config('app.name') }}</td></tr>
      <tr><td>URL</td><td>{{ config('app.url') }}</td></tr>
      <tr><td>Pilote mail</td><td>{{ config('mail.default') }}</td></tr>
      <tr><td>Expéditeur</td><td>{{ config('mail.from.address') }}</td></tr>
      <tr><td>Envoyé le</td><td>{{ $sentAt }}</td></tr>
    </table>
    <p style="font-size:13px;color:#6b7280;margin-top:20px;">
      Ce message est un email de test généré depuis la page Paramètres de l'application.
      Aucune action n'est requise.
    </p>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
