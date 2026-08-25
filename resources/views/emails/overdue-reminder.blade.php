<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.container{max-width:600px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.header{background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:22px}
.content{padding:30px}
.badge{display:inline-block;background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold;margin-bottom:20px}
.info-table{width:100%;border-collapse:collapse;margin:20px 0}
.info-table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:14px}
.info-table td:first-child{font-weight:bold;color:#374151;width:35%}
.alert{background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:16px;margin:20px 0;color:#92400e;font-size:14px}
.btn{display:inline-block;background:#d97706;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;margin-top:20px}
.footer{background:#f9fafb;padding:20px;text-align:center;font-size:12px;color:#6b7280}
</style></head>
<body>
<div class="container">
  @include('emails.partials.header')
  <div class="header"><h1>⏰ Rappel : matériel non retourné</h1></div>
  <div class="content">
    <span class="badge">Retour en retard</span>
    <p>Bonjour {{ $reservation->user->name }},</p>
    <p>Nous vous rappelons que votre réservation <strong>« {{ $reservation->title }} »</strong> a dépassé la date de retour prévue.</p>

    <div class="alert">
      Merci de retourner le matériel dès que possible au magasin.
    </div>

    <table class="info-table">
      <tr><td>Réservation</td><td>{{ $reservation->title }}</td></tr>
      <tr><td>Date de retour prévue</td><td>{{ $reservation->end_date->format('d/m/Y H:i') }}</td></tr>
      <tr><td>Équipement(s)</td><td>{{ $reservation->assets->pluck('name')->join(', ') }}</td></tr>
    </table>

    <a href="{{ url('/reservations/' . $reservation->id) }}" class="btn">Voir la réservation</a>
  </div>
  <div class="footer">
    <p>Cet email a été envoyé automatiquement par le système de gestion du magasin.</p>
  </div>
</div>
</body>
</html>
