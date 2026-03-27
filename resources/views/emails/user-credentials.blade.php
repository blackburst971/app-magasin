<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.header{background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.badge{display:inline-block;background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold;margin-bottom:20px}
.creds-box{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:20px;margin:20px 0}
.creds-box table{width:100%;border-collapse:collapse}
.creds-box td{padding:8px 0;font-size:15px}
.creds-box td:first-child{color:#374151;font-weight:bold;width:35%}
.creds-box .value{font-family:monospace;background:#fff;border:1px solid #e2e8f0;border-radius:4px;padding:4px 10px;font-size:15px;letter-spacing:.05em}
.btn{display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;margin-top:20px}
.warning{background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:12px 16px;margin:20px 0;font-size:13px;color:#713f12}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>Bienvenue sur {{ config('app.name') }}</h1></div>
  <div class="content">
    <span class="badge">Nouveaux identifiants</span>
    <p>Bonjour <strong>{{ $user->name }}</strong>,</p>
    <p>Un compte a été créé pour vous sur <strong>{{ config('app.name') }}</strong>.
       Voici vos identifiants de connexion :</p>

    <div class="creds-box">
      <table>
        <tr>
          <td>Adresse URL</td>
          <td><span class="value">{{ config('app.url') }}</span></td>
        </tr>
        <tr>
          <td>Email</td>
          <td><span class="value">{{ $user->email }}</span></td>
        </tr>
        <tr>
          <td>Mot de passe</td>
          <td><span class="value">{{ $plainPassword }}</span></td>
        </tr>
      </table>
    </div>

    <a href="{{ config('app.url') }}/login" class="btn">Se connecter</a>

    <div class="warning">
      ⚠️ <strong>Important :</strong> Pour des raisons de sécurité, nous vous recommandons de modifier votre mot de passe dès votre première connexion, depuis votre profil.
    </div>

    <p style="font-size:13px;color:#6b7280;margin-top:20px;">
      Si vous n'êtes pas à l'origine de cette demande ou si vous avez reçu cet email par erreur,
      veuillez contacter l'administrateur.
    </p>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
