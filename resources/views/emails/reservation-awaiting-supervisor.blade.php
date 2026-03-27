<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.header{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.badge{display:inline-block;background:#ede9fe;color:#5b21b6;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold;margin-bottom:20px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;color:#374151;width:35%}
.btn{display:inline-block;background:#7c3aed;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;margin-top:20px}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
.alert{background:#fffbeb;border:1px solid #fde68a;padding:15px;border-radius:6px;margin-bottom:20px;font-size:13px;color:#92400e}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>✍️ Signature requise</h1></div>
  <div class="content">
    <span class="badge">En attente de votre validation</span>
    <p>Bonjour {{ $supervisor->name }},</p>
    <p>Un élève dont vous êtes responsable a soumis une demande de réservation de matériel qui nécessite votre validation.</p>
    <div class="alert">
      <strong>Action requise :</strong> Connectez-vous sur l'application et accédez à votre espace "Mon groupe" pour signer et valider (ou refuser) cette demande.
    </div>
    <table class="info-table">
      <tr><td>Demandeur</td><td>{{ $reservation->user->name }}</td></tr>
      <tr><td>Email</td><td>{{ $reservation->user->email }}</td></tr>
      <tr><td>Titre</td><td>{{ $reservation->title }}</td></tr>
      <tr><td>Début</td><td>{{ $reservation->start_date->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Fin</td><td>{{ $reservation->end_date->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Équipements</td><td>{{ $reservation->assets->count() }} équipement(s)</td></tr>
      @if($reservation->notes)
      <tr><td>Notes</td><td>{{ $reservation->notes }}</td></tr>
      @endif
    </table>
    @if($reservation->assets->count() > 0)
    <p><strong>Matériels demandés :</strong></p>
    <ul>@foreach($reservation->assets as $asset)<li>{{ $asset->name }} ({{ $asset->asset_tag }})</li>@endforeach</ul>
    @endif
    <a href="{{ url('/supervisor/group') }}" class="btn">Accéder à mon espace groupe →</a>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
