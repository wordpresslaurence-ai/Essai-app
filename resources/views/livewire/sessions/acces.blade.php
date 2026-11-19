<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif
    @if ($erreur)
        <div class="lb-alert lb-alert-danger mt-4">{{ $erreur }}</div>
    @endif

    <div class="mt-4">
        <div class="lb-eyebrow">Accès Telegram</div>
        <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px;max-width:54rem">
            Chaque abonné reçoit son propre lien vers le canal, valable une seule fois. Le nombre de pratiques faites
            compte les clics sur « ✓ Fait » sous tes publications marquées <code>#pratique</code>
            (<a href="{{ route('sessions.contenu', $seance) }}" class="lb-accent" style="font-weight:600" wire:navigate>calendrier de contenu</a>). À la fin du trimestre,
            l'accès de ceux qui ne se sont pas réinscrits est retiré à la première ouverture du CRM.
        </p>
    </div>

    {{-- Réglages du trimestre --}}
    <div class="lb-card mt-4 flex flex-wrap items-center justify-between gap-3" style="padding:16px 20px">
        <div class="flex flex-wrap gap-x-8 gap-y-2" style="font-size:13.5px">
            <div><span class="lb-muted">Canal :</span>
                @if ($canal)
                    <b>{{ $canal->nom() }}</b>@if (! $canal->actif) <span style="color:var(--terra)">(le robot n'y est plus administrateur)</span>@endif
                @else
                    <b style="color:var(--terra)">aucun</b>
                @endif
            </div>
            <div><span class="lb-muted">Fin de l'accès :</span> <b>{{ $seance->fin_acces?->translatedFormat('j F Y') ?? 'non fixée' }}</b></div>
            <div><span class="lb-muted">Entrée jusqu'au :</span> <b>{{ $seance->limite_entree?->translatedFormat('j F Y') ?? 'non fixée' }}</b></div>
        </div>
        <a href="{{ route('sessions.modifier', $seance) }}" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px" wire:navigate>Modifier</a>
    </div>

    @if (! $canal)
        <div class="lb-alert mt-4" style="background:var(--gold-tint);color:var(--ink)">
            Choisis le canal de ce trimestre dans <a href="{{ route('sessions.modifier', $seance) }}" class="lb-accent" style="font-weight:600" wire:navigate>la session</a>.
            Si ton canal n'apparaît pas dans la liste, va dans
            <a href="{{ route('reglages.telegram') }}" class="lb-accent" style="font-weight:600" wire:navigate>Réglages → Telegram</a> pour le détecter.
        </div>
    @elseif (! $seance->fin_acces)
        <div class="lb-alert mt-4" style="background:var(--gold-tint);color:var(--ink)">
            Indique la date de fin de l'accès dans la session : sans elle, ni le renouvellement ni le retrait ne se feront.
        </div>
    @endif

    {{-- Chiffres et actions --}}
    <div class="lb-card mt-4 flex flex-wrap items-center justify-between gap-3" style="padding:16px 20px">
        <div class="flex flex-wrap gap-x-8 gap-y-2">
            @foreach (['payes' => 'payé(s)', 'liens' => 'lien(s) créé(s)', 'rejoints' => 'dans le canal', 'retires' => 'accès retiré(s)'] as $cle => $libelle)
                <div>
                    <span style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $compteurs[$cle] }}</span>
                    <span class="lb-muted"> {{ $libelle }}</span>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canal && $seance->fin_acces)
                <button type="button" wire:click="ouvrirRenouvellement" class="lb-btn lb-btn-ghost">Proposer le renouvellement</button>
            @endif
            @if ($canal)
                <button type="button" wire:click="creerLiensPayes" class="lb-btn lb-btn-primary" wire:loading.attr="disabled" wire:target="creerLiensPayes">
                    <span wire:loading.remove wire:target="creerLiensPayes">Créer les liens des abonnés payés</span>
                    <span wire:loading wire:target="creerLiensPayes">Création…</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Renouvellement --}}
    @if ($renouvellement)
        <div class="lb-card mt-4" style="padding:20px">
            <div class="flex items-center justify-between gap-3">
                <h3 class="lb-h3" style="font-size:18px">Renouvellement</h3>
                <button type="button" wire:click="fermerRenouvellement" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Fermer</button>
            </div>
            <p class="lb-muted" style="font-size:12.5px;margin-top:4px">
                @if ($prochaine)
                    Trimestre suivant proposé : <b>{{ $prochaine->titre }}</b>@if ($prochaine->date_debut), à partir du {{ $prochaine->date_debut->translatedFormat('j F Y') }}@endif.
                    Les abonnés déjà inscrits à ce trimestre ne sont pas listés.
                @else
                    Aucun trimestre suivant n'est encore créé sur ce canal : le message affichera <code>[prochaine_date]</code>.
                    Duplique cette session pour le créer.
                @endif
            </p>
            <label for="messageRenouvellement" class="lb-label" style="margin-top:10px">Message</label>
            <textarea id="messageRenouvellement" rows="6" wire:model.live.debounce.500ms="messageRenouvellement" class="lb-field"></textarea>

            <div class="lb-people mt-3">
                @forelse ($lignesRenouvellement as $l)
                    @php $c = $l['inscription']->contact; @endphp
                    <div class="lb-prow" wire:key="ren-{{ $l['inscription']->id }}" style="flex-wrap:wrap;row-gap:8px">
                        @if ($l['fait'])
                            <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Ouvert ✓</span>
                        @else
                            <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">À envoyer</span>
                        @endif
                        <div style="min-width:10rem;flex:1"><div class="nm lb-truncate">{{ $c->nomComplet() }}</div></div>
                        <div class="flex flex-wrap items-center gap-2" x-data="{ copie: false }">
                            <button type="button" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px"
                                    x-on:click="navigator.clipboard.writeText(@js($l['texte'])).then(() => { copie = true; setTimeout(() => copie = false, 2000) }); $wire.marquerRenouvellement({{ $l['inscription']->id }})">
                                <span x-show="!copie">Copier</span><span x-show="copie" x-cloak>Copié ✓</span>
                            </button>
                            @if ($l['mail'])
                                <a href="{{ $l['mail'] }}" wire:click="marquerRenouvellement({{ $l['inscription']->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px;text-decoration:none">Ouvrir ma messagerie</a>
                            @endif
                            @if ($l['whatsapp'])
                                <a href="{{ $l['whatsapp'] }}" target="_blank" rel="noopener" wire:click="marquerRenouvellement({{ $l['inscription']->id }})" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px;text-decoration:none">Ouvrir WhatsApp</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="lb-placeholder">Tout le monde est déjà réinscrit au trimestre suivant.</div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Abonnés --}}
    <div class="lb-people mt-4">
        @forelse ($inscriptions as $ins)
            @php
                $c = $ins->contact;
                $paiement = $ins->etatPaiement();
                $acces = $ins->etatAcces();
            @endphp
            <div wire:key="acc-{{ $ins->id }}">
                <div class="lb-prow" style="flex-wrap:wrap;row-gap:10px">
                    @if ($paiement === 'solde')
                        <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Payé</span>
                    @elseif ($paiement === 'partiel')
                        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">Acompte</span>
                    @elseif ($paiement === 'impaye')
                        <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">À payer</span>
                    @else
                        <span class="lb-tag" style="background:var(--surface)">Sans montant</span>
                    @endif

                    <a href="{{ route('contacts.fiche', $c) }}" wire:navigate style="min-width:10rem;flex:1;text-decoration:none;color:inherit">
                        <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                        <div class="mt lb-truncate">
                            @switch($acces)
                                @case('retire') Accès retiré le {{ $ins->acces_retire_le->format('d/m/Y') }} @break
                                @case('rejoint') Dans le canal depuis le {{ $ins->acces_rejoint_le->format('d/m/Y') }}
                                    @php $b = $bilans[$ins->id]; @endphp
                                    @if ($b['publiees'] > 0) · <b style="color:var(--ink)">{{ $b['faites'] }}/{{ $b['publiees'] }} pratiques</b>@if ($b['derniere']), dernière le {{ $b['derniere']->format('d/m') }}@endif @endif
                                    @break
                                @case('envoye') Lien envoyé le {{ $ins->acces_envoye_le->format('d/m/Y') }}, pas encore utilisé @break
                                @case('lien_cree') Lien créé, pas encore envoyé @break
                                @default Pas encore de lien
                            @endswitch
                        </div>
                    </a>

                    <div class="flex flex-wrap items-center gap-2" style="margin-left:auto">
                        @if ($canal && $acces !== 'rejoint')
                            <button type="button" wire:click="preparerBienvenue({{ $ins->id }})"
                                    class="lb-btn {{ $paiement === 'impaye' ? 'lb-btn-ghost' : 'lb-btn-primary' }}" style="padding:7px 13px;font-size:12.5px"
                                    @if ($paiement === 'impaye') title="Le paiement n'est pas encore noté" @endif>
                                {{ match ($acces) { 'sans_lien', 'retire' => ($paiement === 'impaye' ? 'Donner l’accès quand même' : 'Donner l’accès'), default => 'Renvoyer le lien' } }}
                            </button>
                        @endif
                        @if (in_array($acces, ['lien_cree', 'envoye', 'rejoint'], true))
                            <button type="button" wire:click="retirerAcces({{ $ins->id }})" wire:confirm="Retirer l'accès au canal pour {{ $c->nomComplet() }} ?"
                                    class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Retirer l'accès</button>
                        @endif
                    </div>
                </div>

                @if ($ouverteId === $ins->id)
                    <div class="lb-card" style="padding:18px;margin:4px 0 14px" x-data="{ copie: false }">
                        <label for="corps" class="lb-label">Message de bienvenue pour {{ $c->nomComplet() }}</label>
                        <input type="hidden" x-ref="objet" value="{{ $seance->titre }}">
                        <textarea id="corps" rows="9" x-ref="corps" wire:model="corps" class="lb-field"></textarea>
                        @if (str_contains($corps, '['))
                            <p style="font-size:12.5px;margin-top:6px;color:var(--terra);font-weight:600">Il reste des repères entre crochets à compléter avant d'envoyer.</p>
                        @endif
                        <p class="lb-muted" style="font-size:12px;margin-top:6px">
                            Le texte vient de ton modèle « Accès — bienvenue dans le canal »
                            (<a href="{{ route('reglages.modeles') }}" class="lb-accent" style="font-weight:600" wire:navigate>le modifier</a>).
                        </p>
                        <div class="flex flex-wrap items-center gap-2 mt-4">
                            @include('livewire.sessions._envoi')
                            @if ($c->telegramRelie())
                                <button type="button" wire:click="envoyerParRobot" class="lb-btn lb-btn-ghost">Envoyer par le robot</button>
                            @endif
                            <span style="flex:1"></span>
                            <button type="button" wire:click="fermer" class="lb-btn lb-btn-ghost">Fermer</button>
                            <button type="button" wire:click="marquerEnvoye" class="lb-btn lb-btn-primary">C'est envoyé</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">Aucun abonné confirmé pour ce trimestre.</div>
        @endforelse
    </div>
</div>
