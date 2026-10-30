<div>
    <div class="lb-eyebrow">Assistant</div>
    <h1 class="lb-h1">Mails récents</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">Tous les e-mails synchronisés, du plus récent au plus ancien.</p>

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
