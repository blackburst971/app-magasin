<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden}
.header{background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;width:35%}
.reason{background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:15px;margin:20px 0}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>Réservation annulée par l'administration</h1></div>
  <div class="content">
    <p>Bonjour {{ $reservation->user->name }},</p>
    <p>Votre réservation a été annulée par l'administrateur.</p>
    <table class="info-table">
      <tr><td>Réservation</td><td>{{ $reservation->title }}</td></tr>
      <tr><td>Période</td><td>{{ $reservation->start_date->format('d/m/Y H:i') }} → {{ $reservation->end_date->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Équipements</td><td>{{ $reservation->assets->count() }} équipement(s)</td></tr>
    </table>
    @if($reservation->admin_notes)
    <div class="reason"><strong>Motif de l'annulation :</strong> {{ $reservation->admin_notes }}</div>
    @endif
    <p>Si vous avez des questions, n'hésitez pas à contacter l'administration.</p>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
