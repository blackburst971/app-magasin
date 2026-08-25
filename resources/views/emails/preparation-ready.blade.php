<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden}
.header{background:linear-gradient(135deg,#2563eb,#0891b2);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;width:35%}
.asset-table{width:100%;border-collapse:collapse;margin:20px 0}
.asset-table th{background:#f3f4f6;padding:10px;text-align:left;font-size:13px}
.asset-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:13px}
.notice{background:#dbeafe;border:1px solid #93c5fd;border-radius:6px;padding:15px;margin:20px 0;font-size:14px}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>Matériel en cours de préparation</h1></div>
  <div class="content">
    <p>Bonjour {{ $preparation->user->name }},</p>
    <p>Le matériel suivant a été mis en préparation pour vous. Il sera prêt à être retiré aux dates indiquées.</p>
    <table class="info-table">
      <tr><td>Objet</td><td>{{ $preparation->title }}</td></tr>
      <tr><td>Retrait prévu</td><td>{{ $preparation->planned_start_at->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Retour prévu</td><td>{{ $preparation->planned_end_at->format('d/m/Y H:i') }}</td></tr>
      @if($preparation->notes)
      <tr><td>Notes</td><td>{{ $preparation->notes }}</td></tr>
      @endif
    </table>
    <p><strong>Liste du matériel préparé :</strong></p>
    <table class="asset-table">
      <thead><tr><th>Équipement</th><th>Tag</th><th>Catégorie</th></tr></thead>
      <tbody>
        @foreach($preparation->assets as $asset)
        <tr>
          <td>{{ $asset->name }}</td>
          <td>{{ $asset->asset_tag }}</td>
          <td>{{ $asset->category?->name ?? 'N/A' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="notice">
      Ce matériel est mis de côté pour vous et ne peut pas être réservé par quelqu'un d'autre. Présentez-vous au magasin aux dates indiquées pour effectuer le retrait.
    </div>
  </div>
  <div class="footer">{{ config('app.name') }} — Gestion des emprunts de matériel</div>
</div>
</body></html>
