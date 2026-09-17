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
  .name { font-weight: 600; color: #111; }
  .tag { font-family: "Courier New", monospace; color: #6b7280; font-size: 8px; }
  .serial { font-family: "Courier New", monospace; font-size: 8px; color: #9ca3af; }
  .category { background: #f3e8ff; color: #6b21a8; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; display: inline-block; }
  .status { padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; display: inline-block; }
  .status-available { background: #d1fae5; color: #065f46; }
  .status-reserved { background: #dbeafe; color: #1e40af; }
  .status-checked_out { background: #fed7aa; color: #92400e; }
  .status-maintenance { background: #fef3c7; color: #92400e; }
  .status-retired { background: #e5e7eb; color: #6b7280; }
  .footer { margin-top: 12px; font-size: 8px; color: #9ca3af; text-align: right; }
</style>
</head>
<body>

<div class="header">
  <h1>{{ $title }}</h1>
  <p class="meta">
    Généré le {{ $date }} &mdash; {{ $assets->count() }} équipement(s)
  </p>
</div>

<table>
  <thead>
    <tr>
      <th class="num">#</th>
      @foreach($columns as $col)
        <th>{{ $columnLabels[$col] ?? $col }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($assets as $i => $asset)
    <tr>
      <td class="num">{{ $i + 1 }}</td>
      @foreach($columns as $col)
        @if($col === 'name')
          <td class="name">{{ $asset->name }}</td>
        @elseif($col === 'asset_tag')
          <td class="tag">{{ $asset->asset_tag }}</td>
        @elseif($col === 'serial')
          <td class="serial">{{ $asset->serial ?? '—' }}</td>
        @elseif($col === 'category')
          <td>
            @if($asset->category)
              <span class="category">{{ $asset->category->name }}</span>
            @else
              <span style="color:#d1d5db">—</span>
            @endif
          </td>
        @elseif($col === 'model')
          <td>{{ $asset->equipmentModel?->name ?? $asset->model ?? '—' }}</td>
        @elseif($col === 'manufacturer')
          <td>{{ $asset->manufacturerModel?->name ?? $asset->manufacturer ?? '—' }}</td>
        @elseif($col === 'location')
          <td>{{ $asset->location ?? '—' }}</td>
        @elseif($col === 'status')
          <td>
            <span class="status status-{{ $asset->status }}">
              {{ $asset->status_label }}
            </span>
          </td>
        @elseif($col === 'checked_out_to')
          <td>{{ $asset->checkedOutTo?->name ?? '—' }}</td>
        @else
          <td>—</td>
        @endif
      @endforeach
    </tr>
    @endforeach
  </tbody>
</table>

<div class="footer">
  Application de gestion du matériel &mdash; BTS MAV Pointe-Noire
</div>

</body>
</html>
