{{-- Boutons d'envoi communs : copier, messagerie, WhatsApp. Attend $c (contact) et des x-ref « objet » et « corps ». --}}
@php $whatsapp = $c->numeroWhatsapp(); @endphp
<button type="button" class="lb-btn lb-btn-ghost"
        x-on:click="navigator.clipboard.writeText($refs.corps.value).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
    <span x-show="!copie">Copier le message</span><span x-show="copie" x-cloak>Copié ✓</span>
</button>
@if (filled($c->email))
    <button type="button" class="lb-btn lb-btn-ghost"
            x-on:click="window.location.href = 'mailto:' + @js($c->email) + '?subject=' + encodeURIComponent($refs.objet.value) + '&body=' + encodeURIComponent($refs.corps.value)">
        Ouvrir ma messagerie
    </button>
@endif
@if ($whatsapp)
    <button type="button" class="lb-btn lb-btn-ghost"
            x-on:click="window.open('https://wa.me/' + @js($whatsapp) + '?text=' + encodeURIComponent($refs.corps.value), '_blank')">
        Ouvrir WhatsApp
    </button>
@endif
