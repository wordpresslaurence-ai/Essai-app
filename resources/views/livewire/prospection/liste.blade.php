<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="lb-eyebrow">Assistant de prospection</div>
            <h1 class="lb-h1">Mes recherches</h1>
        </div>
        <a href="{{ route('prospection.creer') }}" class="lb-btn lb-btn-primary" wire:navigate>+ Nouvelle recherche</a>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    <p class="lb-muted mt-3" style="font-size:13px;max-width:52rem">
        Tu prépares ici une recherche de prospects. Le CRM génère un <strong>brief</strong> à coller dans
        ChatGPT ; ChatGPT fait la recherche et te rend un tableau / CSV ; tu reviendras ensuite trier et
        importer le résultat dans le CRM.
    </p>

    <div class="lb-people mt-5">
        @forelse ($recherches as $r)
            <div class="lb-prow" wire:key="rech-{{ $r->id }}">
                <div style="min-width:0;flex:1">
                    <div class="nm lb-truncate">{{ $r->nom }}</div>
                    <div class="mt lb-truncate">
                        @if ($r->categorie) {{ $r->categorie->libelleComplet() }} · @endif
                        {{ $r->nombre }} souhaité(s)
                        @if ($r->libellesSources()) · {{ implode(', ', $r->libellesSources()) }} @endif
                    </div>
                </div>
                <div style="margin-left:auto" class="flex items-center gap-2">
                    <a href="{{ route('prospection.modifier', $r) }}" class="lb-accent" style="font-size:12.5px;font-weight:600;text-decoration:none" wire:navigate>Ouvrir le brief</a>
                    <button type="button" wire:click="supprimer({{ $r->id }})" wire:confirm="Supprimer cette recherche ?"
                            class="lb-iconbtn" style="width:30px;height:30px;color:var(--terra)" aria-label="Supprimer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                    </button>
                </div>
            </div>
        @empty
            <div class="lb-placeholder">Aucune recherche pour l'instant. Commence par en créer une.</div>
        @endforelse
    </div>
</div>
