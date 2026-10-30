<div>
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <div class="lb-eyebrow">Assistant</div>
            <h1 class="lb-h1">Mails récents</h1>
            <p class="lb-muted" style="font-size:13px;margin-top:2px">Tous les e-mails synchronisés, du plus récent au plus ancien.</p>
        </div>
        @if ($nbAnciens > 0)
            <button type="button" class="lb-btn lb-btn-ghost" style="flex:none;color:var(--terra,#C0604F)"
                    wire:click="supprimerAnciens"
                    wire:confirm="Supprimer définitivement les {{ $nbAnciens }} mail(s) de plus de 12 mois ? Les contacts et les mails récents ne sont pas touchés.">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                Nettoyer (&gt; 12 mois) · {{ $nbAnciens }}
            </button>
        @endif
    </div>

    @if ($derniersSupprimes !== null)
        <div class="lb-tag mt-3" style="background:var(--mint-tint);color:var(--mint-deep)">
            {{ $derniersSupprimes }} mail(s) de plus de 12 mois supprimé(s). Seuls les 12 derniers mois sont conservés.
        </div>
    @endif

    {{-- Filtres --}}
    <div class="flex flex-wrap items-center gap-2 mt-5">
        @foreach (['' => 'Tous', 'entrant' => 'Reçus', 'sortant' => 'Envoyés'] as $cle => $lib)
            <button type="button" wire:click="$set('sens', '{{ $cle }}')"
                    aria-pressed="{{ $sens === $cle ? 'true' : 'false' }}"
                    class="lb-pill" style="cursor:pointer;border:0;{{ $sens === $cle ? 'background:var(--accent);color:var(--btn-text,#fff);font-weight:700' : '' }}">
                {{ $lib }}
            </button>
        @endforeach
        <input type="search" wire:model.live.debounce.400ms="recherche" placeholder="Rechercher (objet, contact)…"
               class="lb-field" style="margin-left:auto;max-width:18rem">
    </div>

    <div class="lb-people mt-5">
        @forelse ($mails as $mail)
            <a href="{{ $mail->contact ? route('contacts.fiche', $mail->contact) : '#' }}"
               class="lb-prow" wire:navigate wire:key="mail-{{ $mail->id }}" style="text-decoration:none;align-items:flex-start">
                <span class="lb-tag" style="flex:none;font-size:10.5px;{{ $mail->estEntrant() ? 'background:var(--mint-tint);color:var(--mint-deep)' : 'background:var(--lav-tint,#efeafe);color:var(--lav)' }}">
                    {{ $mail->estEntrant() ? '↘ Reçu' : '↗ Envoyé' }}
                </span>
                <div style="min-width:0;flex:1">
                    <div class="nm lb-truncate">{{ $mail->contact?->nomComplet() ?? $mail->de }}</div>
                    <div class="lb-truncate" style="font-size:13px;color:var(--ink);margin-top:1px">{{ $mail->objet ?: '(sans objet)' }}</div>
                    @if ($mail->extrait)
                        <div class="mt lb-truncate" style="margin-top:2px">{{ $mail->extrait }}</div>
                    @endif
                </div>
                <span class="lb-muted" style="flex:none;font-size:12px;white-space:nowrap">{{ $mail->date_email?->translatedFormat('d/m/Y') }}</span>
            </a>
        @empty
            <div class="lb-placeholder">Aucun mail {{ $sens === 'entrant' ? 'reçu' : ($sens === 'sortant' ? 'envoyé' : 'synchronisé') }} pour l'instant. Lance une synchro depuis l'Assistant.</div>
        @endforelse
    </div>

    <div class="mt-5" style="overflow-x:auto">{{ $mails->links() }}</div>
</div>
