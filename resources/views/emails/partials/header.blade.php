@php
    use App\Models\Setting;
    use Illuminate\Support\Facades\Storage;

    $_logoLycee  = Setting::get('logo_lycee');
    $_logoBts    = Setting::get('logo_bts');
    $_schoolName = Setting::get('school_name', config('app.name'));

    // Résout le chemin absolu d'un logo stocké sur le disque public
    $resolveLogoPath = function (?string $path) : ?string {
        if (!$path) return null;
        // URL distante — on ne peut pas l'embarquer en CID, on la retourne telle quelle
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $abs = Storage::disk('public')->path($path);
        return file_exists($abs) ? $abs : null;
    };

    $_logoLyceePath = $resolveLogoPath($_logoLycee);
    $_logoBtsPath   = $resolveLogoPath($_logoBts);
@endphp
@if($_logoLyceePath || $_logoBtsPath)
<div style="background:#ffffff;padding:14px 24px;border-bottom:1px solid #e5e7eb;text-align:center;line-height:0;">
    @if($_logoLyceePath)
    @php
        $_logoLyceeSrc = str_starts_with($_logoLyceePath, 'http')
            ? $_logoLyceePath
            : $message->embed($_logoLyceePath);
    @endphp
    <img src="{{ $_logoLyceeSrc }}"
         style="display:inline-block;height:55px;width:auto;vertical-align:middle;object-fit:contain;"
         alt="{{ $_schoolName }}" />
    @endif
    @if($_logoLyceePath && $_logoBtsPath)
    <span style="display:inline-block;width:1px;height:45px;background:#e5e7eb;vertical-align:middle;margin:0 16px;"></span>
    @endif
    @if($_logoBtsPath)
    @php
        $_logoBtsSrc = str_starts_with($_logoBtsPath, 'http')
            ? $_logoBtsPath
            : $message->embed($_logoBtsPath);
    @endphp
    <img src="{{ $_logoBtsSrc }}"
         style="display:inline-block;height:55px;width:auto;vertical-align:middle;object-fit:contain;"
         alt="BTS" />
    @endif
</div>
@endif
