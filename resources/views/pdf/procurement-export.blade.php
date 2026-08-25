<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste des Commandes</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #111; padding: 16px; }
  .header { margin-bottom: 12px; border-bottom: 2px solid #2563eb; padding-bottom: 8px; display: flex; justify-content: space-between; align-items: flex-end; }
  .header h1 { font-size: 17px; color: #1d4ed8; font-weight: bold; }
  .header .meta { font-size: 8px; color: #6b7280; margin-top: 2px; }
  .header .stats { font-size: 9px; color: #374151; text-align: right; }
  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  thead tr { background: #1e3a5f; color: white; }
  thead th { padding: 5px 6px; text-align: left; font-size: 8px; font-weight: bold; white-space: nowrap; }
  tbody td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
  tbody tr:nth-child(even) td { background: #f8fafc; }
  .num { width: 20px; text-align: center; color: #9ca3af; font-size: 8px; }
  .qty { text-align: center; font-weight: bold; }
  .price { text-align: right; font-family: "Courier New", monospace; font-size: 8px; }
  .total-row td { background: #eff6ff !important; font-weight: bold; border-top: 2px solid #2563eb; }
  .badge { display: inline; padding: 1px 5px; border-radius: 3px; font-size: 7.5px; font-weight: bold; }
  .badge-besoin      { background: #fef9c3; color: #854d0e; }
  .badge-en_commande { background: #dbeafe; color: #1e40af; }
  .badge-commande    { background: #dcfce7; color: #166534; }
  .badge-livre       { background: #d1fae5; color: #065f46; }
  .badge-annule      { background: #fee2e2; color: #991b1b; }
  .prio-haute  { color: #dc2626; font-weight: bold; }
  .prio-basse  { color: #9ca3af; }
  .url { color: #2563eb; font-size: 7.5px; word-break: break-all; }
  .footer { margin-top: 10px; font-size: 7.5px; color: #9ca3af; text-align: right; }
  .type-badge { display: inline; padding: 1px 4px; border-radius: 2px; font-size: 7.5px; }
  .type-materiel    { background: #ede9fe; color: #5b21b6; }
  .type-consommable { background: #fce7f3; color: #9d174d; }
  .type-logiciel    { background: #ecfdf5; color: #064e3b; }
  .type-autre       { background: #f3f4f6; color: #374151; }
</style>
</head>
<body>

<div class="header">
  <div>
    <h1>Liste des Besoins / Commandes</h1>
    <p class="meta">Généré le {{ now()->format('d/m/Y à H:i') }} &mdash; {{ $items->count() }} article(s)</p>
  </div>
  <div class="stats">
    @php $totalStr = $total > 0 ? number_format($total, 2, ',', ' ') . ' €' : '—' @endphp
    <strong>Total estimé : {{ $totalStr }}</strong>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th class="num">#</th>
      <th style="min-width:130px">Désignation</th>
      <th style="width:70px">Type</th>
      <th class="qty" style="width:35px">Qté</th>
      <th style="width:40px">Unité</th>
      <th style="min-width:80px">Référence</th>
      <th style="min-width:80px">Fabricant</th>
      <th style="min-width:80px">Fournisseur</th>
      <th class="price" style="width:65px">P.U. (€)</th>
      <th class="price" style="width:65px">Total (€)</th>
      <th style="width:65px">Statut</th>
      <th style="width:45px">Priorité</th>
      <th>Notes / Lien</th>
      @foreach($formFields as $ff)
      <th style="min-width:70px">{{ $ff->label }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($items as $i => $item)
    <tr>
      <td class="num">{{ $i + 1 }}</td>
      <td><strong>{{ $item->designation }}</strong></td>
      <td>
        <span class="type-badge type-{{ $item->type }}">
          {{ \App\Models\ProcurementItem::typeLabel($item->type) }}
        </span>
      </td>
      <td class="qty">{{ $item->quantity }}</td>
      <td>{{ $item->unit }}</td>
      <td>{{ $item->reference ?? '—' }}</td>
      <td>{{ $item->manufacturer ?? '—' }}</td>
      <td>{{ $item->supplier ?? '—' }}</td>
      <td class="price">{{ $item->unit_price !== null ? number_format($item->unit_price, 2, ',', ' ') : '—' }}</td>
      <td class="price">{{ $item->total_price !== null ? number_format($item->total_price, 2, ',', ' ') : '—' }}</td>
      <td><span class="badge badge-{{ $item->status }}">{{ \App\Models\ProcurementItem::statusLabel($item->status) }}</span></td>
      <td class="{{ $item->priority === 'haute' ? 'prio-haute' : ($item->priority === 'basse' ? 'prio-basse' : '') }}">
        {{ \App\Models\ProcurementItem::priorityLabel($item->priority) }}
      </td>
      <td style="max-width:120px;font-size:8px">
        {{ $item->notes ?? '' }}
        @if($item->product_url)
          @if($item->notes)<br>@endif
          <span class="url">{{ $item->product_url }}</span>
        @endif
      </td>
      @foreach($formFields as $ff)
      <td style="font-size:8px">
        @php
          $val = $item->extra_fields[$ff->label] ?? null;
          if (is_array($val)) $val = implode(', ', $val);
          elseif ($val === true || $val === 'true' || $val === 1) $val = 'Oui';
          elseif ($val === false || $val === 'false' || $val === 0) $val = '';
        @endphp
        {{ $val ?? '' }}
      </td>
      @endforeach
    </tr>
    @endforeach
    @if($total > 0)
    <tr class="total-row">
      <td colspan="{{ 9 + $formFields->count() }}" style="text-align:right;padding-right:6px">TOTAL ESTIMÉ</td>
      <td class="price">{{ number_format($total, 2, ',', ' ') }}</td>
      <td colspan="3"></td>
    </tr>
    @endif
  </tbody>
</table>

<div class="footer">Application de gestion du matériel &mdash; Document à usage interne</div>
</body>
</html>
