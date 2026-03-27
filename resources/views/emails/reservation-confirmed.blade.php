<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.header{background:linear-gradient(135deg,#059669,#10b981);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.badge{display:inline-block;background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold;margin-bottom:20px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;color:#374151;width:35%}
.btn{display:inline-block;background:#059669;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;margin-top:20px}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>Réservation validée</h1></div>
  <div class="content">
    <span class="badge">Approuvée</span>
    <p>Bonjour {{ $reservation->user->name }},</p>
    <p>Votre demande de réservation a été <strong>validée</strong>. Vous pouvez venir récupérer le matériel aux dates prévues.</p>
    <table class="info-table">
      <tr><td>Réservation</td><td>{{ $reservation->title }}</td></tr>
      <tr><td>Début</td><td>{{ $reservation->start_date->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Fin</td><td>{{ $reservation->end_date->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Validée par</td><td>{{ $reservation->approvedBy?->name ?? 'Admin' }}</td></tr>
      @if($reservation->admin_notes)<tr><td>Notes admin</td><td>{{ $reservation->admin_notes }}</td></tr>@endif
    </table>
    <p><strong>Matériels réservés :</strong></p>
    <ul>@foreach($reservation->assets as $asset)<li>{{ $asset->name }} ({{ $asset->asset_tag }})</li>@endforeach</ul>
    @if($autoCancelEnabled)
    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:14px;margin:20px 0;font-size:13px;color:#713f12;">
      ⏱️ <strong>Important :</strong> Vous disposez de <strong>{{ $autoCancelHours }} heure(s)</strong> à compter de maintenant pour venir retirer le matériel.
      Passé ce délai, votre réservation sera <strong>annulée automatiquement</strong> et les équipements remis à disposition.
    </div>
    @endif
    <a href="{{ url('/reservations/' . $reservation->id) }}" class="btn">Voir ma réservation</a>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
