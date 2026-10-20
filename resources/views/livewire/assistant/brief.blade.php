<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="lb-h1">Brief du matin</h1>
            <p class="lb-muted" style="font-size:13px;margin-top:2px">Ce qu'il faut suivre aujourd'hui.</p>
        </div>
        <button type="button" wire:click="synchroniser" class="lb-btn lb-btn-primary"
                wire:loading.attr="disabled" wire:target="synchroniser">
            <span wire:loading.remove wire:target="synchroniser">Synchroniser mes mails</span>
            <span wire:loading wire:target="synchroniser">Synchronisation…</span>
        </button>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif
    @if (session('erreur'))
        <div class="lb-alert lb-alert-danger mt-5">{{ session('erreur') }}</div>
    @endif

    {{-- Réponses dues (prioritaire) --}}
    <div class="flex items-center justify-between mt-6">
        <h3 class="lb-h3" style="font-size:19px">Tu dois une réponse</h3>
        <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">{{ $reponsesDues->count() }}</span>
    </div>
    <div class="lb-people mt-3">
        @forelse ($reponsesDues as $ligne)
            <div class="lb-prow" wire:key="rd-{{ $ligne['contact']->id }}">
                <span class="lb-tag" style="background:var(--lav-tint);color:var(--lav)">Reçu</span>
                <a href="{{ route('contacts.fiche', $ligne['contact']) }}" wire:navigate style="min-width:0;flex:1;text-decoration:none;color:inherit">
                    <div class="nm lb-truncate">{{ $ligne['contact']->nomComplet() }}</div>
                    <div class="mt lb-truncate">{{ $ligne['dernier']->objet ?: '(sans objet)' }}</div>
                </a>
                <div style="margin-left:auto;display:flex;align-items:center;gap:10px;flex:none">
                    <span class="lb-muted" style="font-size:12px;white-space:nowrap">{{ $ligne['dernier']->date_email?->translatedFormat('d/m/Y') }}</span>
                    @if (in_array($ligne['contact']->id, $dejaRepondre))
                        <span class="lb-muted" style="font-size:12px;white-space:nowrap" title="Une tâche « Répondre » existe déjà">✓ Activité</span>
                    @else
                        <button type="button" wire:click="creerActivite({{ $ligne['contact']->id }}, 'repondre')"
                                class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px;white-space:nowrap">+ Activité</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="lb-placeholder">Aucune réponse en attente. 🎉</div>
        @endforelse
    </div>

    {{-- À relancer --}}
    <div class="flex items-center justify-between mt-6">
        <h3 class="lb-h3" style="font-size:19px">À relancer</h3>
        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">{{ $aRelancer->count() }}</span>
    </div>
    <div class="lb-people mt-3">
        @forelse ($aRelancer as $ligne)
            <div class="lb-prow" wire:key="ar-{{ $ligne['contact']->id }}">
                <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Envoyé</span>
                <a href="{{ route('contacts.fiche', $ligne['contact']) }}" wire:navigate style="min-width:0;flex:1;text-decoration:none;color:inherit">
                    <div class="nm lb-truncate">{{ $ligne['contact']->nomComplet() }}</div>
                    <div class="mt lb-truncate">{{ $ligne['dernier']->objet ?: '(sans objet)' }}</div>
                </a>
                <div style="margin-left:auto;display:flex;align-items:center;gap:10px;flex:none">
                    <span class="lb-muted" style="font-size:12px;white-space:nowrap">{{ $ligne['dernier']->date_email?->translatedFormat('d/m/Y') }}</span>
                    @if (in_array($ligne['contact']->id, $dejaRelancer))
                        <span class="lb-muted" style="font-size:12px;white-space:nowrap" title="Une tâche « Relancer » existe déjà">✓ Activité</span>
                    @else
                        <button type="button" wire:click="creerActivite({{ $ligne['contact']->id }}, 'relancer')"
                                class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px;white-space:nowrap">+ Activité</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="lb-placeholder">Rien à relancer pour l'instant.</div>
        @endforelse
    </div>

    {{-- Suggestions du jour (IA, à la demande) --}}
    <div class="flex items-center justify-between mt-6">
        <h3 class="lb-h3" style="font-size:19px">Suggestions du jour</h3>
        <button type="button" wire:click="genererSuggestions" class="lb-btn lb-btn-ghost"
                style="padding:7px 13px;font-size:12.5px"
                wire:loading.attr="disabled" wire:target="genererSuggestions">
            <span wire:loading.remove wire:target="genererSuggestions">{{ $suggestionsChargees ? 'Actualiser' : 'Obtenir les suggestions' }}</span>
            <span wire:loading wire:target="genererSuggestions">L'assistant réfléchit…</span>
        </button>
    </div>

    @if ($suggestionsErreur)
        <div class="lb-alert lb-alert-danger mt-3">{{ $suggestionsErreur }}</div>
    @endif

    @if ($suggestionsChargees)
        <div class="lb-card-sm mt-3">
            @forelse ($suggestions as $s)
                <div style="display:flex;align-items:flex-start;gap:10px;{{ ! $loop->first ? 'margin-top:10px' : '' }}">
                    <span style="flex:none;font-size:15px;line-height:1.4">💡</span>
                    <span style="color:var(--text)">{{ $s }}</span>
                </div>
            @empty
                <p class="lb-muted" style="font-size:13px">Aucune suggestion particulière aujourd'hui.</p>
            @endforelse
        </div>
    @else
        <div class="lb-placeholder mt-3">Clique sur « Obtenir les suggestions » pour un conseil personnalisé (un appel à l'assistant).</div>
    @endif
</div>
