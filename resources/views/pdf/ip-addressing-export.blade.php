<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; background: #fff; }
    .header { background: #4f46e5; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
    .header h1 { font-size: 15px; font-weight: bold; letter-spacing: 0.02em; }
    .header .meta { font-size: 8px; opacity: .8; text-align: right; }
    .stats-row { display: flex; gap: 16px; padding: 8px 20px; background: #f8fafc; border-bottom: 1px solid #e5e7eb; }
    .stat { font-size: 8px; }
    .stat strong { font-size: 10px; color: #4f46e5; }
    table { width: 100%; border-collapse: collapse; margin-top: 0; }
    thead tr { background: #f1f5f9; }
    thead th { padding: 7px 8px; text-align: left; font-size: 7.5px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
    tbody tr:nth-child(even) { background: #f9fafb; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
    .badge { display: inline-block; padding: 1px 5px; border-radius: 10px; font-size: 7px; font-weight: bold; }
    .badge-windows { background: #dbeafe; color: #1d4ed8; }
    .badge-macos   { background: #f3f4f6; color: #374151; }
    .badge-linux   { background: #fed7aa; color: #9a3412; }
    .badge-other   { background: #f3f4f6; color: #6b7280; }
    .ip-block { margin-bottom: 2px; }
    .ip { font-family: monospace; font-size: 9px; font-weight: bold; color: #1d4ed8; }
    .ip-desc { font-size: 7.5px; color: #6b7280; font-style: italic; margin-left: 4px; }
    .ip-empty { color: #d1d5db; font-style: italic; font-size: 8px; }
    .license-list { font-size: 7.5px; color: #6b7280; }
    .no-data { text-align: center; padding: 30px; color: #9ca3af; font-style: italic; }
    .footer { margin-top: 12px; border-top: 1px solid #e5e7eb; padding: 6px 20px; display: flex; justify-content: space-between; font-size: 7px; color: #9ca3af; }
    .multi-ip td { border-bottom: none; background: transparent !important; }
    .multi-ip-last td { border-bottom: 1px solid #e5e7eb; }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <h1>📡 Tableau d'adressage IP</h1>
      <div style="font-size:8px;margin-top:2px;opacity:.8">Postes informatiques — vue complète</div>
    </div>
    <div class="meta">
      Généré le {{ now()->format('d/m/Y à H:i') }}<br>
      {{ $computers->count() }} poste(s)
    </div>
  </div>

  @php
    $withIp   = $computers->filter(fn($c) => !empty($c['ip_addresses']))->count();
    $withoutIp = $computers->count() - $withIp;
    $totalIps  = $computers->sum(fn($c) => count($c['ip_addresses'] ?? []));
  @endphp

  <div class="stats-row">
    <div class="stat">Total postes : <strong>{{ $computers->count() }}</strong></div>
    <div class="stat">Avec IP : <strong style="color:#16a34a">{{ $withIp }}</strong></div>
    <div class="stat">Sans IP : <strong style="color:#dc2626">{{ $withoutIp }}</strong></div>
    <div class="stat">Adresses IP total : <strong>{{ $totalIps }}</strong></div>
  </div>

  @if($computers->isEmpty())
    <div class="no-data">Aucun poste enregistré.</div>
  @else
  <table>
    <thead>
      <tr>
        <th>Nom du poste</th>
        <th>Tag</th>
        <th>Type</th>
        <th>Système d'exploitation</th>
        <th>Adresses IP</th>
        <th>Processeur</th>
        <th>RAM</th>
        <th>Licences installées</th>
      </tr>
    </thead>
    <tbody>
      @foreach($computers as $c)
        @php
          $osBadge = match(true) {
            str_starts_with($c['os'] ?? '', 'windows') => 'badge-windows',
            ($c['os'] ?? '') === 'macos'  => 'badge-macos',
            ($c['os'] ?? '') === 'linux'  => 'badge-linux',
            default => 'badge-other',
          };
          $ips = $c['ip_addresses'] ?? [];
        @endphp
        <tr>
          <td><strong>{{ $c['name'] }}</strong></td>
          <td style="font-family:monospace;color:#6b7280;font-size:8px">{{ $c['asset_tag'] ?? '—' }}</td>
          <td>{{ $c['type'] }}</td>
          <td>
            <span class="badge {{ $osBadge }}">{{ $c['os'] }}</span>
            @if(!empty($c['os_version']))
              <span style="color:#9ca3af;font-size:7.5px"> {{ $c['os_version'] }}</span>
            @endif
          </td>
          <td>
            @if(empty($ips))
              <span class="ip-empty">non définie</span>
            @else
              @foreach($ips as $entry)
                <div class="ip-block">
                  <span class="ip">{{ $entry['ip'] }}</span>
                  @if(!empty($entry['description']))
                    <span class="ip-desc">— {{ $entry['description'] }}</span>
                  @endif
                </div>
              @endforeach
            @endif
          </td>
          <td style="max-width:120px;overflow:hidden;font-size:8px">{{ $c['processor'] ?? '—' }}</td>
          <td>{{ $c['ram'] ?? '—' }}</td>
          <td class="license-list">{{ $c['licenses'] ?: '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="footer">
    <span>Document confidentiel — usage interne</span>
    <span>Tableau d'adressage IP — {{ now()->format('d/m/Y') }}</span>
  </div>
</body>
</html>
