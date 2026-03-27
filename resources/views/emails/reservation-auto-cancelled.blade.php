<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden}
.header{background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.header p{margin:8px 0 0;font-size:14px;opacity:.85}
.content{padding:30px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;width:35%}
.alert{background:#faf5ff;border:1px solid #d8b4fe;border-radius:6px;padding:15px;margin:20px 0;font-size:13px;color:#6b21a8}
.cta{display:inline-block;margin-top:16px;padding:10px 24px;background:#7c3aed;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;font-weight:bold}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header">
    <h1>⏱️ Réservation annulée automatiquement</h1>
    <p>Le délai de retrait a expiré</p>
  </div>
  <div class="content">
    <p>Bonjour {{ $reservation->user->name }},</p>
    <p>Votre réservation a été <strong>annulée automatiquement</strong> car le retrait du matériel n'a pas été effectué dans les <strong>{{ $hours }} heure(s)</strong> suivant l'approbation.</p>
    <table class="info-table">
      <tr><td>Réservation</td><td>{{ $reservation->title }}</td></tr>
      <tr><td>Période prévue</td><td>{{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}</td></tr>
      <tr><td>Approbation accordée le</td><td>{{ $reservation->approved_at?->format('d/m/Y à H:i') ?? '—' }}</td></tr>
    </table>
    <div class="alert">
      ℹ️ <strong>Que faire ?</strong> Si vous avez encore besoin de ce matériel, vous pouvez effectuer une nouvelle demande de réservation depuis l'application.
    </div>
    <p>Si vous pensez qu'il s'agit d'une erreur, contactez un administrateur.</p>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
