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
        @if (!empty($detailSync['imports']) || !empty($detailSync['prospects']))
            <div class="lb-card mt-3" style="padding:16px 18px">
                @if (!empty($detailSync['imports']))
                    <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Mails rattachés</div>
                    <div class="lb-people mt-2">
                        @foreach ($detailSync['imports'] as $m)
                            <div class="lb-prow" style="padding:9px 12px">
                                <span class="lb-tag" style="font-size:10.5px;{{ $m['direction'] === 'sortant' ? 'background:var(--lav-tint,#efeafe);color:var(--lav)' : 'background:var(--mint-tint);color:var(--mint-deep)' }}">{{ $m['direction'] === 'sortant' ? '↗ Envoyé' : '↘ Reçu' }}</span>
                                <div style="min-width:0;flex:1">
                                    <div class="nm lb-truncate">{{ $m['nom'] }}</div>
                                    <div class="mt lb-truncate">{{ $m['objet'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if (!empty($detailSync['prospects']))
                    <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-top:12px">Prospects à créer</div>
                    <div class="lb-people mt-2">
                        @foreach ($detailSync['prospects'] as $p)
                            <div class="lb-prow" style="padding:9px 12px">
                                <div style="min-width:0;flex:1">
                                    <div class="nm lb-truncate">{{ $p['adresse'] }}</div>
                                    <div class="mt lb-truncate">{{ $p['objet'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endif
    @if (session('erreur'))
        <div class="lb-alert lb-alert-danger mt-5">{{ session('erreur') }}</div>
    @endif

    {{-- Filtre par pôle (brique 009) --}}
    @if ($poles->isNotEmpty())
        <div class="flex items-center gap-2 mt-5">
            <label for="poleFiltre" class="lb-muted" style="font-size:12.5px">Pôle</label>
            <select id="poleFiltre" wire:model.live="poleFiltre" class="lb-field lb-select" style="width:auto;padding:7px 32px 7px 12px;font-size:13px">
                <option value="">Tous les pôles</option>
                @foreach ($poles as $pole)
                    <option value="{{ $pole->id }}">{{ $pole->nom }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Réponses dues (prioritaire) --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mt-6">
        <h3 class="lb-h3" style="font-size:19px">Tu dois une réponse</h3>
        <div class="flex items-center gap-2">
            <label class="lb-muted" style="font-size:12px" title="Échéance des tâches « Répondre » créées ici">Échéance
                <input type="date" wire:model="echeanceReponse" class="lb-field" style="padding:5px 8px;font-size:12px;width:auto;display:inline-block;margin-left:4px">
            </label>
            <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">{{ $reponsesDues->count() }}</span>
        </div>
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
    <div class="flex flex-wrap items-center justify-between gap-2 mt-6">
        <h3 class="lb-h3" style="font-size:19px">À relancer</h3>
        <div class="flex items-center gap-2">
            <label class="lb-muted" style="font-size:12px" title="Échéance des tâches « Relancer » créées ici">Échéance
                <input type="date" wire:model="echeanceRelance" class="lb-field" style="padding:5px 8px;font-size:12px;width:auto;display:inline-block;margin-left:4px">
            </label>
            <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">{{ $aRelancer->count() }}</span>
        </div>
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

    {{-- Opportunités qui stagnent (règle : étape inchangée depuis 21 j) --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mt-6">
        <h3 class="lb-h3" style="font-size:19px">Opportunités qui stagnent</h3>
        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">{{ $stagnantes->count() }}</span>
    </div>
    <div class="lb-people mt-3">
        @forelse ($stagnantes as $opp)
            <div class="lb-prow" wire:key="stag-{{ $opp->id }}">
                <span class="lb-dot" style="width:12px;height:12px;background:var(--{{ \App\Models\Opportunite::couleurEtape($opp->etape) }})"></span>
                <div style="min-width:0;flex:1">
                    <div class="nm lb-truncate">{{ $opp->titre }}</div>
                    <div class="mt lb-truncate">
                        {{ $opp->libelleEtape() }}@if ($opp->contact) · {{ $opp->contact->nomComplet() }}@endif
                        · <span style="color:var(--terra)">{{ $opp->joursDepuisChangement() }} j sans bouger</span>
                    </div>
                </div>
                <div style="margin-left:auto;flex:none">
                    @if (in_array($opp->id, $dejaOpp))
                        <span class="lb-muted" style="font-size:12px;white-space:nowrap" title="Une relance existe déjà">✓ Relance</span>
                    @else
                        <button type="button" wire:click="creerTacheOpportunite({{ $opp->id }})"
                                class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px;white-space:nowrap">+ Relance</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="lb-placeholder">Aucune opportunité à l'arrêt. 👌</div>
        @endforelse
    </div>

    {{-- Indécis à réveiller (règles : leads chauds sans suite + session passée) --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mt-6">
        <h3 class="lb-h3" style="font-size:19px">À réveiller</h3>
        <span class="lb-tag" style="background:var(--lav-tint);color:var(--lav)">{{ $indecisChauds->count() + $indecisSessions->count() }}</span>
    </div>
    <div class="lb-people mt-3">
        @forelse ($indecisChauds as $c)
            <div class="lb-prow" wire:key="ic-{{ $c->id }}">
                <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">Lead chaud</span>
                <a href="{{ route('contacts.fiche', $c) }}" wire:navigate style="min-width:0;flex:1;text-decoration:none;color:inherit">
                    <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                    <div class="mt lb-truncate">Intéressé·e mais sans suite depuis un moment</div>
                </a>
                <div style="margin-left:auto;flex:none">
                    @if (in_array($c->id, $dejaRelancer))
                        <span class="lb-muted" style="font-size:12px;white-space:nowrap">✓ Activité</span>
                    @else
                        <button type="button" wire:click="creerActivite({{ $c->id }}, 'relancer')"
                                class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px;white-space:nowrap">+ Relance</button>
                    @endif
                </div>
            </div>
        @empty
        @endforelse

        @foreach ($indecisSessions as $c)
            <div class="lb-prow" wire:key="is-{{ $c->id }}">
                <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Session passée</span>
                <a href="{{ route('contacts.fiche', $c) }}" wire:navigate style="min-width:0;flex:1;text-decoration:none;color:inherit">
                    <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                    <div class="mt lb-truncate">Intéressé·e à une session passée, sans place à venir</div>
                </a>
                <div style="margin-left:auto;flex:none">
                    @if (in_array($c->id, $dejaRelancer))
                        <span class="lb-muted" style="font-size:12px;white-space:nowrap">✓ Activité</span>
                    @else
                        <button type="button" wire:click="creerActivite({{ $c->id }}, 'relancer')"
                                class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px;white-space:nowrap">+ Relance</button>
                    @endif
                </div>
            </div>
        @endforeach

        @if ($indecisChauds->isEmpty() && $indecisSessions->isEmpty())
            <div class="lb-placeholder">Personne à réveiller pour l'instant. 🌿</div>
        @endif
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
