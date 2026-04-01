@php
    use App\Models\Setting;
    use Illuminate\Support\Facades\Storage;

    $_logoLycee  = Setting::get('logo_lycee');
    $_logoBts    = Setting::get('logo_bts');
    $_schoolName = Setting::get('school_name', config('app.name'));

    // Fonction locale : retourne un data URI base64 ou null
    $inlineImg = function (?string $path) : ?string {
        if (!$path) return null;

        // Déjà une URL distante (http/https) → on garde telle quelle
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Fichier dans le disque public
        if (!Storage::disk('public')->exists($path)) return null;

        $content  = Storage::disk('public')->get($path);
        $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime     = match($ext) {
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    };

    $_logoLyceeSrc = $inlineImg($_logoLycee);
    $_logoBtsSrc   = $inlineImg($_logoBts);
@endphp
@if($_logoLyceeSrc || $_logoBtsSrc)
<div style="background:#ffffff;padding:14px 24px;border-bottom:1px solid #e5e7eb;text-align:center;line-height:0;">
    @if($_logoLyceeSrc)
    <img src="{{ $_logoLyceeSrc }}"
         style="display:inline-block;height:55px;width:auto;vertical-align:middle;object-fit:contain;"
         alt="{{ $_schoolName }}" />
    @endif
    @if($_logoLyceeSrc && $_logoBtsSrc)
    <span style="display:inline-block;width:1px;height:45px;background:#e5e7eb;vertical-align:middle;margin:0 16px;"></span>
    @endif
    @if($_logoBtsSrc)
    <img src="{{ $_logoBtsSrc }}"
         style="display:inline-block;height:55px;width:auto;vertical-align:middle;object-fit:contain;"
         alt="BTS" />
    @endif
</div>
@endif
