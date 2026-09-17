@php
$columnLabels = [
  'name'           => 'Nom',
  'asset_tag'      => 'Tag',
  'serial'         => 'N° Série',
  'category'       => 'Catégorie',
  'model'          => 'Modèle',
  'manufacturer'   => 'Fabricant',
  'location'       => 'Emplacement',
  'status'         => 'Statut',
  'checked_out_to' => 'Affecté à',
];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111; padding: 16px; }
  .header { margin-bottom: 12px; border-bottom: 2px solid #2563eb; padding-bottom: 8px; }
  .header h1 { font-size: 18px; color: #1d4ed8; font-weight: bold; margin-bottom: 4px; }
  .header .meta { font-size: 9px; color: #6b7280; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  thead tr { background: #1e3a5f; color: white; }
  thead th { padding: 6px 8px; text-align: left; font-size: 9px; font-weight: bold; white-space: nowrap; }
  tbody td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; font-size: 9px; }
  tbody tr:nth-child(even) td { background: #f8fafc; }
  .num { width: 25px; text-align: center; color: #9ca3af; font-size: 8px; font-weight: bold; }
  .qty { width: 50px; text-align: center; font-weight: bold; color: #1d4ed8; background: #dbeafe; border-radius: 3px; }
  .category { background: #f3e8ff; color: #6b21a8; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; display: inline-block; }
  .model { font-weight: 600; color: #111; }
  .manufacturer { color: #6b7280; font-size: 8px; }
  .tag { font-family: 'Courier New', monospace; font-size: 8px; color: #374151; word-wrap: break-word; }
  .footer { margin-top: 12px; font-size: 8px; color: #9ca3af; text-align: right; }
  .summary { margin-top: 8px; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 9px; }
  .summary strong { color: #1d4ed8; }
  .status { padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; display: inline-block; }
</style>
</head>
<body>

<div class="header">
  <h1>{{ $title }}</h1>
  <p class="meta">
    Généré le {{ $date }} &mdash; {{ $groups->count() }} modèle(s) différent(s)
  </p>
</div>

<table>
  <thead>
    <tr>
      <th class="num">#</th>
      @foreach($columns as $col)
        <th>{{ $columnLabels[$col] ?? $col }}</th>
      @endforeach
      <th class="qty">Quantité</th>
    </tr>
  </thead>
  <tbody>
    @php $totalQuantity = 0; @endphp
    @foreach($groups as $i => $group)
    @php $totalQuantity += $group['quantity']; @endphp
    <tr>
      <td class="num">{{ $i + 1 }}</td>
      @foreach($columns as $col)
        @if($col === 'name')
          <td class="tag">{{ implode(', ', $group['names'] ?? []) }}</td>
        @elseif($col === 'asset_tag')
          <td class="tag">{{ implode(', ', $group['asset_tags'] ?? []) }}</td>
        @elseif($col === 'category')
          <td>
            @if($group['category'] ?? null)
              <span class="category">{{ $group['category']->name }}</span>
            @else
              <span style="color:#d1d5db">—</span>
            @endif
          </td>
        @elseif($col === 'model')
          <td class="model">{{ $group['model'] ?? '—' }}</td>
        @elseif($col === 'manufacturer')
          <td class="manufacturer">{{ $group['manufacturer'] ?? '—' }}</td>
        @elseif($col === 'location')
          <td class="tag">{{ implode(', ', $group['locations'] ?? []) }}</td>
        @elseif($col === 'status')
          <td>
            @foreach(($group['statuses'] ?? []) as $status)
              <span class="status">{{ $status }}</span>@if(!$loop->last), @endif
            @endforeach
          </td>
        @else
          <td>—</td>
        @endif
      @endforeach
      <td class="qty">{{ $group['quantity'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="summary">
  <strong>Total:</strong> {{ $groups->count() }} modèle(s) différent(s) &mdash; <strong>{{ $totalQuantity }}</strong> équipement(s) au total
</div>

<div class="footer">
  Application de gestion du matériel &mdash; BTS MAV Pointe-Noire
</div>

</body>
</html>
