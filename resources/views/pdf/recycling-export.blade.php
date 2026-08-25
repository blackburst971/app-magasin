<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste Décheterie</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111; padding: 16px; }
  .header { margin-bottom: 14px; border-bottom: 2px solid #dc2626; padding-bottom: 10px; }
  .header h1 { font-size: 18px; color: #dc2626; font-weight: bold; }
  .header .meta { font-size: 9px; color: #6b7280; margin-top: 3px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  thead tr { background: #374151; color: white; }
  thead th { padding: 6px 7px; text-align: left; font-size: 9px; font-weight: bold; white-space: nowrap; }
  tbody td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: top; word-break: break-word; }
  tbody tr:nth-child(even) td { background: #f9fafb; }
  .num { width: 22px; text-align: center; color: #6b7280; font-size: 9px; }
  .strong { font-weight: bold; }
  .mono { font-family: "Courier New", monospace; font-size: 9px; }
  .qty { text-align: center; font-weight: bold; font-size: 11px; }
  .footer { margin-top: 12px; font-size: 8px; color: #9ca3af; text-align: right; }
  .badge { display: inline; background: #fef2f2; color: #dc2626; padding: 1px 5px; border-radius: 3px; font-size: 8px; font-weight: bold; }
  .photo-cell { width: 80px; text-align: center; }
  .photo-cell img { width: 70px; height: 55px; object-fit: cover; border-radius: 3px; border: 1px solid #e5e7eb; }
  .no-photo { color: #9ca3af; font-size: 8px; }
</style>
</head>
<body>

<div class="header">
  <h1>&#9851; Liste Décheterie</h1>
  <p class="meta">Généré le {{ now()->format('d/m/Y à H:i') }} &mdash; {{ count($items) }} équipement(s) sélectionné(s)</p>
</div>

<table>
  <thead>
    <tr>
      <th class="num">#</th>
      <th class="photo-cell">Photo</th>
      <th style="min-width:140px">Désignation</th>
      <th style="min-width:90px">Référence</th>
      <th style="min-width:110px">N° de série</th>
      <th style="min-width:80px">Marque</th>
      <th style="min-width:80px">Modèle</th>
      <th class="qty" style="width:40px">Qté</th>
      <th>Notes</th>
      @foreach($extraKeys as $key)
        <th>{{ $key }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($items as $i => $item)
    <tr>
      <td class="num">{{ $i + 1 }}</td>
      <td class="photo-cell">
        @if(!empty($photoData[$item->id]))
          <img src="{{ $photoData[$item->id] }}" alt="photo">
        @else
          <span class="no-photo">—</span>
        @endif
      </td>
      <td>
        <span class="strong">{{ $item->designation }}</span>
        @if($item->asset_id)
          <br><span class="badge">Inventaire</span>
        @endif
      </td>
      <td>{{ $item->reference ?? '—' }}</td>
      <td class="mono">{{ $item->serial_number ?? '—' }}</td>
      <td>{{ $item->brand ?? '—' }}</td>
      <td>{{ $item->model_name ?? '—' }}</td>
      <td class="qty">{{ $item->quantity }}</td>
      <td style="max-width:120px;font-size:9px">{{ $item->notes ?? '' }}</td>
      @foreach($extraKeys as $key)
        <td>{{ ($item->extra_fields ?? [])[$key] ?? '' }}</td>
      @endforeach
    </tr>
    @endforeach
  </tbody>
</table>

<div class="footer">
  Application de gestion du matériel &mdash; Document à usage interne
</div>

</body>
</html>
