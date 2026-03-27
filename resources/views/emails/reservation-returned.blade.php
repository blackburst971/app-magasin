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
.success-box{background:#f0fdf4;border:1px solid #bbf7d0;padding:15px;border-radius:6px;margin-bottom:20px;font-size:13px;color:#166534}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>✅ Retour confirmé</h1></div>
  <div class="content">
    <span class="badge">Retour enregistré</span>
    <p>Bonjour {{ $reservation->user->name }},</p>
    <div class="success-box">
      Le retour de l'ensemble du matériel de votre réservation a été confirmé par l'administrateur. Merci pour votre soin !
    </div>
    <table class="info-table">
      <tr><td>Réservation</td><td>{{ $reservation->title }}</td></tr>
      <tr><td>Période</td><td>{{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}</td></tr>
      <tr><td>Retour le</td><td>{{ $reservation->return_at?->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Équipements</td><td>{{ $reservation->assets->count() }} équipement(s)</td></tr>
    </table>
    @if($reservation->assets->count() > 0)
    <p><strong>Matériels retournés :</strong></p>
    <ul>@foreach($reservation->assets as $asset)<li>{{ $asset->name }} ({{ $asset->asset_tag }})</li>@endforeach</ul>
    @endif
    <a href="{{ url('/reservations/' . $reservation->id) }}" class="btn">Voir la réservation</a>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
