<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden}
.header{background:linear-gradient(135deg,#1d4ed8,#7c3aed);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;width:35%}
.asset-table{width:100%;border-collapse:collapse;margin:20px 0}
.asset-table th{background:#f3f4f6;padding:10px;text-align:left;font-size:13px}
.asset-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:13px}
.warning{background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:15px;margin:20px 0;font-size:14px}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>Récapitulatif de retrait</h1></div>
  <div class="content">
    <p>Bonjour {{ $reservation->user->name }},</p>
    <p>Voici le récapitulatif du matériel que vous avez emprunté.</p>
    <table class="info-table">
      <tr><td>Réservation</td><td>{{ $reservation->title }}</td></tr>
      <tr><td>Date de retrait</td><td>{{ $reservation->checkout_at?->format('d/m/Y H:i') }}</td></tr>
      <tr><td>À retourner avant</td><td>{{ $reservation->end_date->format('d/m/Y H:i') }}</td></tr>
    </table>
    <p><strong>Liste du matériel emprunté :</strong></p>
    <table class="asset-table">
      <thead><tr><th>Équipement</th><th>Tag</th><th>Catégorie</th></tr></thead>
      <tbody>
        @foreach($reservation->assets as $asset)
        <tr>
          <td>{{ $asset->name }}</td>
          <td>{{ $asset->asset_tag }}</td>
          <td>{{ $asset->category?->name ?? 'N/A' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="warning">
      Rappel : Veuillez retourner tout le matériel avant le {{ $reservation->end_date->format('d/m/Y à H:i') }}.
      Tout matériel non retourné pourra entraîner des restrictions d'emprunt.
    </div>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
