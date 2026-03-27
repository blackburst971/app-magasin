<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:12px;color:#1f2937}
h1{color:#1d4ed8;font-size:18px;margin-bottom:5px}
.meta{color:#6b7280;font-size:11px;margin-bottom:20px}
table{width:100%;border-collapse:collapse;margin-top:15px}
th{background:#1d4ed8;color:#fff;padding:8px;text-align:left;font-size:11px}
td{padding:7px 8px;border-bottom:1px solid #e5e7eb;font-size:11px}
tr:nth-child(even) td{background:#f9fafb}
.footer{margin-top:30px;font-size:10px;color:#9ca3af;text-align:center}
</style>
</head>
<body>
<h1>Rapport — {{ ucfirst($type) }}</h1>
<div class="meta">Généré le {{ now()->format('d/m/Y à H:i') }} — {{ config('app.name') }}</div>
@if($type === 'assets')
<table>
  <thead><tr><th>Nom</th><th>Tag</th><th>Catégorie</th><th>Statut</th><th>Emplacement</th><th>Modèle</th></tr></thead>
  <tbody>
    @foreach($data as $asset)
    <tr>
      <td>{{ $asset->name }}</td>
      <td>{{ $asset->asset_tag }}</td>
      <td>{{ $asset->category?->name ?? '-' }}</td>
      <td>{{ $asset->status_label }}</td>
      <td>{{ $asset->location ?? '-' }}</td>
      <td>{{ $asset->model ?? '-' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@elseif($type === 'reservations')
<table>
  <thead><tr><th>ID</th><th>Titre</th><th>Utilisateur</th><th>Début</th><th>Fin</th><th>Statut</th></tr></thead>
  <tbody>
    @foreach($data as $r)
    <tr>
      <td>#{{ $r->id }}</td>
      <td>{{ $r->title }}</td>
      <td>{{ $r->user->name }}</td>
      <td>{{ $r->start_date->format('d/m/Y') }}</td>
      <td>{{ $r->end_date->format('d/m/Y') }}</td>
      <td>{{ $r->status_label }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else
<table>
  <thead><tr><th>Équipement</th><th>Tag</th><th>Action</th><th>Utilisateur</th><th>Notes</th><th>Date</th></tr></thead>
  <tbody>
    @foreach($data as $m)
    <tr>
      <td>{{ $m->asset->name ?? 'N/A' }}</td>
      <td>{{ $m->asset->asset_tag ?? 'N/A' }}</td>
      <td>{{ $m->action_label }}</td>
      <td>{{ $m->user->name ?? 'Système' }}</td>
      <td>{{ $m->notes ?? '-' }}</td>
      <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif
<div class="footer">{{ config('app.name') }} — Total: {{ count($data) }} enregistrements</div>
</body>
</html>
