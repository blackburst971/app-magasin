@php
    use App\Models\Setting;
    use Illuminate\Support\Facades\Storage;

    $_logoLycee    = Setting::get('logo_lycee');
    $_logoBts      = Setting::get('logo_bts');
    $_logoLyceeUrl = $_logoLycee ? Storage::disk('public')->url($_logoLycee) : null;
    $_logoBtsUrl   = $_logoBts   ? Storage::disk('public')->url($_logoBts)   : null;
    $_schoolName   = Setting::get('school_name', config('app.name'));
@endphp
@if($_logoLyceeUrl || $_logoBtsUrl)
<div style="background:#ffffff;padding:14px 24px;border-bottom:1px solid #e5e7eb;text-align:center;line-height:0;">
    @if($_logoLyceeUrl)
    <img src="{{ $_logoLyceeUrl }}"
         style="display:inline-block;height:55px;width:auto;vertical-align:middle;object-fit:contain;"
         alt="{{ $_schoolName }}" />
    @endif
    @if($_logoLyceeUrl && $_logoBtsUrl)
    <span style="display:inline-block;width:1px;height:45px;background:#e5e7eb;vertical-align:middle;margin:0 16px;"></span>
    @endif
    @if($_logoBtsUrl)
    <img src="{{ $_logoBtsUrl }}"
         style="display:inline-block;height:55px;width:auto;vertical-align:middle;object-fit:contain;"
         alt="BTS" />
    @endif
</div>
@endif
