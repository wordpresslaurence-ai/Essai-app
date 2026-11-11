<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-4 mt-4">
        <div style="min-width:0">
            <div class="lb-eyebrow">Invitations</div>
            <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
            @if ($seance->date_debut)
                <div class="lb-muted" style="font-size:13px;margin-top:4px">
                    📅 {{ $seance->date_debut->translatedFormat('l j F Y') }}{{ $seance->date_debut->format('H:i') !== '00:00' ? ' · '.$seance->date_debut->format('H:i') : '' }}
                </div>
            @endif
        </div>
        <a href="{{ route('reglages.modeles') }}" class="lb-btn lb-btn-ghost" wire:navigate>Modèles de messages</a>
    </div>

    {{-- Avertissements utiles avant de commencer --}}
    @if ($modeles->isEmpty())
        <div class="lb-alert mt-4" style="background:var(--gold-tint);color:var(--ink)">
            Tu n'as pas encore de modèle de relance.
            <a href="{{ route('reglages.modeles') }}" class="lb-accent" style="font-weight:600" wire:navigate>Créer mes modèles →</a>
        </div>
    @endif
    @if (blank($seance->lien_inscription))
        <p class="lb-muted" style="font-size:12.5px;margin-top:10px">
            Cette session n'a pas de lien d'inscription : la variable <code>{lien}</code> apparaîtra comme <code>[lien]</code>.
            <a href="{{ route('sessions.modifier', $seance) }}" class="lb-accent" style="font-weight:600" wire:navigate>L'ajouter</a>
        </p>
    @endif

    {{-- Chiffres de la campagne --}}
    <div class="lb-card mt-5 flex flex-wrap gap-x-8 gap-y-3" style="padding:16px 20px">
        @foreach ([
            'a_inviter' => 'à inviter',
            'invites' => 'invité(s)',
            'a_relancer' => 'à relancer',
            'repondu' => 'ont répondu',
            'inscrits' => 'inscrit(s)',
        ] as $cle => $libelle)
            <div>
                <span style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $compteurs[$cle] }}</span>
                <span class="lb-muted"> {{ $libelle }}</span>
            </div>
        @endforeach
    </div>

    {{-- La campagne --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mt-7">
        <h3 class="lb-h3" style="font-size:18px">La campagne <span class="lb-muted" style="font-size:13px;font-weight:400">· {{ $total }} contact(s)</span></h3>
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'tous' => 'Tous',
                'a_inviter' => 'À inviter',
                'invites' => 'Invités',
                'a_relancer' => 'À relancer',
                'repondu' => 'Ont répondu',
            ] as $cle => $libelle)
                <button type="button" wire:click="$set('filtre', '{{ $cle }}')" class="lb-pill"
                        style="cursor:pointer;border:0;{{ $filtre === $cle ? 'color:var(--accent)' : '' }}">{{ $libelle }}</button>
            @endforeach
        </div>
    </div>

    <div class="lb-people mt-3">
        @forelse ($invitations as $inv)
            @php
                $c = $inv->contact;
                $estInscrit = in_array($inv->contact_id, $inscrits, true);
                [$libelleStatut, $fond, $texte] = \App\Models\Invitation::STATUTS[$inv->statut] ?? [$inv->statut, 'var(--surface)', 'var(--ink)'];
            @endphp
            <div wire:key="inv-{{ $inv->id }}">
                <div class="lb-prow" style="flex-wrap:wrap;row-gap:10px">
                    @if ($estInscrit)
                        <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Inscrit</span>
                    @else
                        <span class="lb-tag" style="background:{{ $fond }};color:{{ $texte }}">{{ $libelleStatut }}</span>
                    @endif

                    <a href="{{ route('contacts.fiche', $c) }}" wire:navigate style="min-width:10rem;flex:1;text-decoration:none;color:inherit">
                        <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                        <div class="mt lb-truncate">
                            {{ $c->email ?: 'pas d’e-mail' }}@if (filled($c->adresse_ville)) · {{ $c->adresse_ville }}@endif
                            @if ($inv->relancee_le) · relancé le {{ $inv->relancee_le->format('d/m') }}
                            @elseif ($inv->invitee_le) · invité le {{ $inv->invitee_le->format('d/m') }}@endif
                        </div>
                    </a>

                    @if (! $estInscrit && $inv->estARelancer())
                        <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">à relancer</span>
                    @endif

                    <div class="flex flex-wrap items-center gap-2" style="margin-left:auto">
                        <button type="button" wire:click="preparer({{ $inv->id }})" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px"
                                wire:loading.attr="disabled" wire:target="preparer({{ $inv->id }})">
                            <span wire:loading.remove wire:target="preparer({{ $inv->id }})">{{ $inv->statut === \App\Models\Invitation::STATUT_A_INVITER ? '✍️ Rédiger un mail de prospection' : 'Relancer' }}</span>
                            <span wire:loading wire:target="preparer({{ $inv->id }})">Rédaction…</span>
                        </button>
                        <select aria-label="Statut de l'invitation" class="lb-field" style="width:auto;padding:6px 10px;font-size:12.5px"
                                wire:change="changerStatut({{ $inv->id }}, $event.target.value)">
                            @foreach (\App\Models\Invitation::STATUTS as $cle => $infos)
                                <option value="{{ $cle }}" @selected($inv->statut === $cle)>{{ $infos[0] }}</option>
                            @endforeach
                        </select>
                        @if (! $estInscrit)
                            <button type="button" wire:click="inscrire({{ $inv->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Inscrire</button>
                        @endif
                        <button type="button" wire:click="retirer({{ $inv->id }})" wire:confirm="Retirer ce contact de la campagne ?"
                                class="lb-btn lb-btn-ghost" style="padding:7px 11px;font-size:12.5px" aria-label="Retirer de la campagne">✕</button>
                    </div>
                </div>

                {{-- Brouillon du message pour ce contact --}}
                @if ($ouverteId === $inv->id)
                    @php $whatsapp = $c->numeroWhatsapp(); @endphp
                    <div class="lb-card" style="padding:18px;margin:4px 0 14px" x-data="{ copie: false }">
                        @php $estEmail = $mode === 'prospection' || $modeleOuvert?->estEmail(); @endphp
                        @if ($mode === 'relance' && $modeles->isEmpty())
                            <p class="lb-muted" style="font-size:13.5px">
                                Crée d'abord ton modèle de relance (un modèle dont le nom commence par « Relance »).
                                <a href="{{ route('reglages.modeles') }}" class="lb-accent" style="font-weight:600" wire:navigate>Créer mes modèles →</a>
                            </p>
                        @else
                            @if ($mode === 'prospection')
                                <label for="angle" class="lb-label" style="font-weight:500;color:var(--text-muted)">Angle (facultatif)</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input id="angle" type="text" wire:model="angle" class="lb-field" style="flex:1;min-width:14rem"
                                           placeholder="ex. j'ai vu qu'elle propose du yoga nidra">
                                    <button type="button" wire:click="redigerProspection" class="lb-btn lb-btn-primary" style="white-space:nowrap"
                                            wire:loading.attr="disabled" wire:target="redigerProspection">
                                        <span wire:loading.remove wire:target="redigerProspection">✍️ {{ filled($brouillonCorps) ? 'Rédiger à nouveau' : 'Rédiger un mail de prospection' }}</span>
                                        <span wire:loading wire:target="redigerProspection">Rédaction…</span>
                                    </button>
                                </div>
                                @if ($brouillonErreur)
                                    <div class="lb-alert lb-alert-danger mt-3">{{ $brouillonErreur }}</div>
                                @endif
                            @endif

                            <div class="grid gap-4 sm:grid-cols-2 {{ $mode === 'prospection' ? 'mt-4' : '' }}">
                                @if ($mode === 'relance')
                                    <div>
                                        <label for="modeleId" class="lb-label">Modèle de relance</label>
                                        <select id="modeleId" wire:model.live="modeleId" class="lb-field">
                                            @foreach ($modeles as $m)
                                                <option value="{{ $m->id }}">{{ $m->nom }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($estEmail)
                                    <div class="{{ $mode === 'prospection' ? 'sm:col-span-2' : '' }}">
                                        <label for="brouillonObjet" class="lb-label">Objet</label>
                                        <input id="brouillonObjet" type="text" x-ref="objet" wire:model="brouillonObjet" class="lb-field">
                                    </div>
                                @endif
                            </div>

                            <div class="mt-4">
                                <label for="brouillonCorps" class="lb-label">Message pour {{ $c->nomComplet() }}</label>
                                <textarea id="brouillonCorps" rows="12" x-ref="corps" wire:model="brouillonCorps" class="lb-field"></textarea>
                                @if (str_contains($brouillonCorps.$brouillonObjet, '['))
                                    <p style="font-size:12.5px;margin-top:6px;color:var(--terra);font-weight:600">
                                        Il reste des repères entre crochets à compléter avant d'envoyer.
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 mt-4">
                                <button type="button" class="lb-btn lb-btn-ghost"
                                        x-on:click="navigator.clipboard.writeText($refs.corps.value).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                                    <span x-show="!copie">Copier le message</span><span x-show="copie" x-cloak>Copié ✓</span>
                                </button>
                                @if ($estEmail && filled($c->email))
                                    <button type="button" class="lb-btn lb-btn-ghost"
                                            x-on:click="window.location.href = 'mailto:' + @js($c->email) + '?subject=' + encodeURIComponent($refs.objet ? $refs.objet.value : '') + '&body=' + encodeURIComponent($refs.corps.value)">
                                        Ouvrir ma messagerie
                                    </button>
                                @endif
                                @if ($whatsapp)
                                    <button type="button" class="lb-btn lb-btn-ghost"
                                            x-on:click="window.open('https://wa.me/' + @js($whatsapp) + '?text=' + encodeURIComponent($refs.corps.value), '_blank')">
                                        Ouvrir WhatsApp
                                    </button>
                                @endif
                                <span style="flex:1"></span>
                                <button type="button" wire:click="fermer" class="lb-btn lb-btn-ghost">Fermer</button>
                                <button type="button" wire:click="marquerEnvoyee" class="lb-btn lb-btn-primary">C'est envoyé</button>
                            </div>
                            <p class="lb-muted" style="font-size:12px;margin-top:8px">
                                {{ $mode === 'prospection' ? 'L’assistant rédige à partir de l’offre de la catégorie de la session.' : '' }} Le CRM n'envoie rien : tu envoies toi-même, puis « C'est envoyé » note la date et garde le texte sur la fiche du contact.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">
                {{ $total === 0 ? 'Personne dans la campagne pour l’instant. Ajoute des contacts ci-dessous.' : 'Aucun contact dans ce filtre.' }}
            </div>
        @endforelse
    </div>

    {{-- Ajouter des contacts --}}
    <h3 class="lb-h3 mt-8" style="font-size:18px">Ajouter des contacts</h3>
    <p class="lb-muted" style="font-size:12.5px">Les contacts archivés, « ne plus contacter » ou déjà dans la campagne ne sont pas proposés.</p>

    <div class="lb-card mt-3" style="padding:16px">
        <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
            <input type="search" wire:model.live.debounce.300ms="recherche" class="lb-field" placeholder="Rechercher…" aria-label="Rechercher un contact">
            <select wire:model.live="type" class="lb-field" aria-label="Type de contact">
                <option value="">Personnes et entreprises</option>
                <option value="{{ \App\Models\Contact::TYPE_PERSONNE }}">Personnes</option>
                <option value="{{ \App\Models\Contact::TYPE_ENTREPRISE }}">Entreprises</option>
            </select>
            <select wire:model.live="etiquette" class="lb-field" aria-label="Étiquette">
                <option value="">Toutes les étiquettes</option>
                @foreach ($etiquettes as $et)
                    <option value="{{ $et->id }}">{{ $et->nom }}</option>
                @endforeach
            </select>
            <select wire:model.live="pole" class="lb-field" aria-label="Pôle">
                <option value="">Tous les pôles</option>
                @foreach ($poles as $p)
                    <option value="{{ $p->id }}">{{ $p->nom }}</option>
                @endforeach
            </select>
            @if ($pole !== '')
                <select wire:model.live="categorie" class="lb-field" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach ($poles->firstWhere('id', (int) $pole)?->categories ?? [] as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                    @endforeach
                </select>
            @endif
            <select wire:model.live="pays" class="lb-field" aria-label="Pays">
                <option value="">Tous les pays</option>
                <option value="BE">Belgique</option>
                <option value="FR">France</option>
            </select>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
            <button type="button" wire:click="basculerTout" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Tout cocher / décocher</button>
            <span class="lb-muted" style="font-size:12.5px">
                {{ $nbCandidats }} contact(s) correspondent.
                @if ($nbCandidats > $candidats->count())
                    Les {{ $candidats->count() }} premiers sont affichés : affine les filtres.
                @endif
            </span>
        </div>

        <div class="lb-people mt-3">
            @forelse ($candidats as $cand)
                <label class="lb-prow" wire:key="cand-{{ $cand->id }}" style="cursor:pointer">
                    <input type="checkbox" wire:model.live="selection" value="{{ $cand->id }}" style="width:18px;height:18px;accent-color:var(--accent);cursor:pointer">
                    <div style="min-width:0;flex:1">
                        <div class="nm lb-truncate">{{ $cand->nomComplet() }}</div>
                        <div class="mt lb-truncate">
                            {{ $cand->email ?: 'pas d’e-mail' }}@if (filled($cand->adresse_ville)) · {{ $cand->adresse_ville }}@endif
                            @if ($cand->entreprise) · {{ $cand->entreprise->nom }}@endif
                        </div>
                    </div>
                </label>
            @empty
                <div class="lb-placeholder">Aucun contact à proposer avec ces filtres.</div>
            @endforelse
        </div>

        <div class="flex justify-end mt-4">
            <button type="button" wire:click="ajouterSelection" class="lb-btn lb-btn-primary" @disabled(count($selection) === 0)>
                Ajouter {{ count($selection) }} contact(s) à la campagne
            </button>
        </div>
    </div>
</div>
