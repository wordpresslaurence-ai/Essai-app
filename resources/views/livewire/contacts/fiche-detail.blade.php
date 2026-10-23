<div x-data="{ confirmerSuppression: false }">
    <a href="{{ route('contacts.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour au carnet</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif
    @if (session('erreur'))
        <div class="lb-alert lb-alert-danger mt-4">{{ session('erreur') }}</div>
    @endif

    @php
        $oppEnCours = $contact->opportunites->whereNotIn('etape', [\App\Models\Opportunite::ETAPE_GAGNE, \App\Models\Opportunite::ETAPE_PERDU]);
        $montantEnCours = $oppEnCours->sum('montant');
        $activitesAFaire = $contact->activites->whereNull('terminee_at')->count();
    @endphp

    {{-- Titre --}}
    <div class="mt-3">
        <div class="lb-eyebrow">Fiche contact</div>
        <h1 class="lb-h1">{{ $contact->nomComplet() }}</h1>
    </div>

    {{-- Chiffres clés --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <div class="lb-card" style="padding:16px 18px">
            <div class="k" style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);font-weight:600">Opportunités</div>
            <div class="serif" style="font-size:30px;font-weight:600;color:var(--ink);line-height:1;margin-top:6px">{{ $oppEnCours->count() }}</div>
            <div class="lb-muted" style="font-size:12px;margin-top:4px">en cours</div>
        </div>
        <div class="lb-card" style="padding:16px 18px">
            <div class="k" style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);font-weight:600">Pipeline</div>
            <div class="serif" style="font-size:30px;font-weight:600;color:var(--accent);line-height:1;margin-top:6px;font-variant-numeric:tabular-nums">{{ number_format($montantEnCours, 0, ',', ' ') }} €</div>
            <div class="lb-muted" style="font-size:12px;margin-top:4px">en cours</div>
        </div>
        <div class="lb-card" style="padding:16px 18px">
            <div class="k" style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);font-weight:600">Activités</div>
            <div class="serif" style="font-size:30px;font-weight:600;color:var(--ink);line-height:1;margin-top:6px">{{ $activitesAFaire }}</div>
            <div class="lb-muted" style="font-size:12px;margin-top:4px">à faire</div>
        </div>
        <div class="lb-card" style="padding:16px 18px">
            <div class="k" style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);font-weight:600">E-mails</div>
            <div class="serif" style="font-size:30px;font-weight:600;color:var(--ink);line-height:1;margin-top:6px">{{ $contact->emails->count() }}</div>
            <div class="lb-muted" style="font-size:12px;margin-top:4px">échangé(s)</div>
        </div>
    </div>

    {{-- Corps : 2 colonnes --}}
    <div class="grid gap-5 lg:grid-cols-3 mt-5">

        {{-- ===================== Colonne principale ===================== --}}
        <div class="lg:col-span-2" style="display:flex;flex-direction:column;gap:20px">

            {{-- Activités (remontées en haut) --}}
            <div class="lb-card" style="padding:20px">
                <div class="flex items-center justify-between">
                    <h3 class="lb-h3" style="font-size:19px">Activités</h3>
                    <a href="{{ route('activites.creer') }}" class="lb-accent" style="font-size:12.5px;font-weight:600;text-decoration:none" wire:navigate>+ Ajouter</a>
                </div>
                <div class="lb-people mt-3">
                    @forelse ($contact->activites as $a)
                        <a href="{{ route('activites.modifier', $a) }}" class="lb-prow" wire:navigate wire:key="ac-{{ $a->id }}" style="text-decoration:none">
                            <span class="lb-dot" style="width:10px;height:10px;background:var(--{{ \App\Models\Activite::couleurType($a->type) }})"></span>
                            <div style="min-width:0">
                                <div class="nm lb-truncate" style="{{ $a->estTerminee() ? 'text-decoration:line-through;color:var(--text-muted)' : '' }}">{{ $a->titre }}</div>
                                <div class="mt">{{ $a->libelleType() }}@if ($a->echeance) · {{ $a->echeance->translatedFormat('d/m/Y') }}@endif</div>
                            </div>
                            @if ($a->estTerminee())<span class="lb-tag lb-b-firm" style="margin-left:auto">✓</span>@elseif ($a->enRetard())<span class="lb-tag" style="margin-left:auto;background:var(--terra-tint);color:var(--terra)">En retard</span>@endif
                        </a>
                    @empty
                        <div class="lb-placeholder">Aucune activité.</div>
                    @endforelse
                </div>
            </div>

            {{-- Profil yoga & prospection --}}
            @php
                $aProfilYoga = filled($contact->discipline) || $contact->libelleExperienceYoga()
                    || filled($contact->fait_observe) || filled($contact->angle_contact);
            @endphp
            @if ($aProfilYoga)
                <div class="lb-card" style="padding:20px">
                    <h3 class="lb-h3" style="font-size:19px">Profil yoga &amp; prospection</h3>
                    <div class="lb-kv" style="grid-template-columns:1fr;gap:14px;margin-top:12px">
                        @if (filled($contact->discipline))
                            <div><div class="k">Discipline enseignée</div><div class="val">{{ $contact->discipline }}</div></div>
                        @endif
                        @if ($contact->libelleExperienceYoga())
                            <div><div class="k">Expérience du yoga</div><div class="val">{{ $contact->libelleExperienceYoga() }}</div></div>
                        @endif
                        @if (filled($contact->fait_observe))
                            <div>
                                <div class="k">Fait observé</div>
                                <div class="val" style="white-space:pre-line;display:block;margin-top:6px">{{ $contact->fait_observe }}</div>
                            </div>
                        @endif
                        @if (filled($contact->angle_contact))
                            <div>
                                <div class="k">Angle de premier contact</div>
                                <div class="val" style="white-space:pre-line;display:block;margin-top:6px">{{ $contact->angle_contact }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Opportunités --}}
            <div class="lb-card" style="padding:20px">
                <div class="flex items-center justify-between">
                    <h3 class="lb-h3" style="font-size:19px">Opportunités</h3>
                    <a href="{{ route('pipeline.creer') }}" class="lb-accent" style="font-size:12.5px;font-weight:600;text-decoration:none" wire:navigate>+ Ajouter</a>
                </div>
                <div class="lb-people mt-3">
                    @forelse ($contact->opportunites as $op)
                        <a href="{{ route('pipeline.modifier', $op) }}" class="lb-prow" wire:navigate wire:key="op-{{ $op->id }}" style="text-decoration:none">
                            <span class="lb-dot" style="width:10px;height:10px;background:var(--{{ \App\Models\Opportunite::couleurEtape($op->etape) }})"></span>
                            <div style="min-width:0"><div class="nm lb-truncate">{{ $op->titre }}</div><div class="mt">{{ $op->libelleEtape() }}</div></div>
                            @if ($op->montant)<span class="serif" style="margin-left:auto;font-weight:600;color:var(--accent)">{{ number_format($op->montant, 0, ',', ' ') }} €</span>@endif
                        </a>
                    @empty
                        <div class="lb-placeholder">Aucune opportunité.</div>
                    @endforelse
                </div>
            </div>

            {{-- Personnes rattachées (entreprise) --}}
            @if ($contact->estEntreprise())
                <div class="lb-card" style="padding:20px">
                    <h3 class="lb-h3" style="font-size:19px">Personnes de cette entreprise</h3>
                    <div class="lb-people mt-3">
                        @forelse ($contact->personnes as $p)
                            <a href="{{ route('contacts.fiche', $p) }}" class="lb-prow" wire:navigate wire:key="p-{{ $p->id }}" style="text-decoration:none">
                                <span class="lb-av lb-av-44 lb-av-{{ $p->couleurAvatar() }}">{{ $p->initiales() }}</span>
                                <div style="min-width:0"><div class="nm lb-truncate">{{ $p->nomComplet() }}</div><div class="mt lb-truncate">{{ $p->fonction }}</div></div>
                            </a>
                        @empty
                            <div class="lb-placeholder">Aucune personne rattachée.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- Inscriptions aux sessions --}}
            @if ($contact->inscriptions->isNotEmpty())
                <div class="lb-card" style="padding:20px">
                    <h3 class="lb-h3" style="font-size:19px">Inscriptions</h3>
                    <div class="lb-people mt-3">
                        @foreach ($contact->inscriptions as $ins)
                            @if ($ins->seance)
                                <a href="{{ route('sessions.fiche', $ins->seance) }}" class="lb-prow" wire:navigate wire:key="ins-{{ $ins->id }}" style="text-decoration:none">
                                    <span class="lb-tag" style="{{ $ins->estEnAttente() ? 'background:var(--gold-tint);color:var(--accent)' : 'background:var(--mint-tint);color:var(--mint-deep)' }}">{{ $ins->estEnAttente() ? 'En attente' : 'Confirmé' }}</span>
                                    <div style="min-width:0">
                                        <div class="nm lb-truncate">{{ $ins->seance->titre }}</div>
                                        @if ($ins->seance->date_debut)<div class="mt">{{ $ins->seance->date_debut->translatedFormat('d/m/Y') }}</div>@endif
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Emails --}}
            <div class="lb-card" style="padding:20px">
                <div class="flex items-center justify-between">
                    <h3 class="lb-h3" style="font-size:19px">E-mails</h3>
                    @if ($contact->emails->isNotEmpty())
                        <div class="flex items-center gap-3">
                            <span class="lb-muted" style="font-size:12px">{{ $contact->emails->count() }} message(s)</span>
                            <button type="button" wire:click="viderEmails"
                                    wire:confirm="Vider tout l'historique e-mails de ce contact ? (Un e-mail encore présent dans ta boîte pourra revenir à la prochaine synchro.)"
                                    class="lb-btn lb-btn-ghost" style="padding:5px 11px;font-size:12px">Vider l'historique</button>
                        </div>
                    @endif
                </div>
                <div class="lb-people mt-3">
                    @forelse ($contact->emails as $mail)
                        <div class="lb-prow" wire:key="mail-{{ $mail->id }}">
                            @if ($mail->estEntrant())
                                <span class="lb-tag" style="background:var(--lav-tint);color:var(--lav)">Reçu</span>
                            @else
                                <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Envoyé</span>
                            @endif
                            <div style="min-width:0">
                                <div class="nm lb-truncate">{{ $mail->objet ?: '(sans objet)' }}</div>
                                <div class="mt lb-truncate">{{ $mail->extrait }}</div>
                            </div>
                            <span class="lb-muted" style="margin-left:auto;font-size:12px;white-space:nowrap">{{ $mail->date_email?->translatedFormat('d/m/Y') }}</span>
                            <button type="button" wire:click="supprimerEmail({{ $mail->id }})"
                                    wire:confirm="Supprimer cet e-mail de la fiche ?"
                                    class="lb-iconbtn" style="width:30px;height:30px;color:var(--terra);flex:none;margin-left:8px" title="Supprimer" aria-label="Supprimer cet e-mail">×</button>
                        </div>
                    @empty
                        <div class="lb-placeholder">Aucun e-mail synchronisé.</div>
                    @endforelse
                </div>
            </div>

            {{-- Analyse & relances (assistant IA) --}}
            <div class="lb-card" style="padding:20px">
                <div class="flex items-center justify-between">
                    <h3 class="lb-h3" style="font-size:19px">Analyse &amp; relances</h3>
                    <button type="button" wire:click="analyser" class="lb-btn lb-btn-ghost"
                            style="padding:7px 13px;font-size:12.5px"
                            wire:loading.attr="disabled" wire:target="analyser">
                        <span wire:loading.remove wire:target="analyser">Analyser</span>
                        <span wire:loading wire:target="analyser">Analyse en cours…</span>
                    </button>
                </div>

                @if ($analyseErreur)
                    <div class="lb-alert lb-alert-danger mt-3">{{ $analyseErreur }}</div>
                @endif

                @if ($derniereAnalyse)
                    <div class="lb-inset mt-3" style="padding:16px" x-data="{ copie: false, copieHtml: false }">
                        <div class="lb-kv"><div class="k">Résumé</div><div class="v">{{ $derniereAnalyse->resume }}</div></div>
                        @if ($derniereAnalyse->verdict)
                            <div class="lb-kv" style="margin-top:8px"><div class="k">À relancer ?</div><div class="v">
                                {{ $derniereAnalyse->verdict }}@if ($derniereAnalyse->delai_conseille) <span class="lb-muted">· {{ $derniereAnalyse->delai_conseille }}</span>@endif
                                @php $echeanceRelance = $this->echeanceRelanceProposee(); @endphp
                                <div style="margin-top:8px">
                                    <button type="button" wire:click="creerTacheRelance" class="lb-btn lb-btn-ghost" style="padding:4px 10px;font-size:12px">
                                        + Créer la tâche de relance
                                        @if ($echeanceRelance)<span class="lb-muted">· pour le {{ $echeanceRelance->translatedFormat('d/m/Y') }}</span>@endif
                                    </button>
                                </div>
                            </div></div>
                        @endif

                        @if ($derniereAnalyse->libelleIntention())
                            <div class="lb-kv" style="margin-top:8px"><div class="k">Intention</div><div class="v"><span class="lb-tag" style="background:var(--lav-tint);color:var(--lav)">{{ $derniereAnalyse->libelleIntention() }}</span></div></div>
                        @endif

                        @php $ic = $derniereAnalyse->infos_cles ?? []; @endphp
                        @if (filled($ic['budget'] ?? null) || filled($ic['delai'] ?? null) || filled($ic['decisions'] ?? null) || ! empty($ic['dates_rdv'] ?? []))
                            <div class="lb-kv" style="margin-top:8px"><div class="k">Infos clés</div><div class="v">
                                @if (filled($ic['budget'] ?? null))<div>💰 Budget : {{ $ic['budget'] }}</div>@endif
                                @if (filled($ic['delai'] ?? null))<div>⏱️ Délai : {{ $ic['delai'] }}</div>@endif
                                @if (! empty($ic['dates_rdv'] ?? []))<div>📅 Dates : {{ is_array($ic['dates_rdv']) ? implode(', ', $ic['dates_rdv']) : $ic['dates_rdv'] }}</div>@endif
                                @if (filled($ic['decisions'] ?? null))<div>✅ Décisions : {{ $ic['decisions'] }}</div>@endif
                            </div></div>
                        @endif

                        @if ($derniereAnalyse->etape_conseillee)
                            @php $ec = $derniereAnalyse->etape_conseillee; $ecLabel = \App\Models\Opportunite::libellesEtapes()[$ec] ?? $ec; @endphp
                            <div class="lb-kv" style="margin-top:8px"><div class="k">Étape conseillée</div>
                                <div class="v" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <span>{{ $ecLabel }}</span>
                                    @if ($oppEnCours->isNotEmpty())
                                        <button type="button" wire:click="avancerPipeline" class="lb-btn lb-btn-ghost" style="padding:4px 10px;font-size:12px">Faire avancer</button>
                                    @else
                                        <button type="button" wire:click="creerOpportunite" class="lb-btn lb-btn-ghost" style="padding:4px 10px;font-size:12px">Créer l'opportunité</button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($derniereAnalyse->temperature_conseillee)
                            @php $tc = $derniereAnalyse->temperature_conseillee; $tcLabel = \App\Models\Contact::TEMPERATURES[$tc][0] ?? $tc; @endphp
                            <div class="lb-kv" style="margin-top:8px"><div class="k">Température conseillée</div>
                                <div class="v" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <span>{{ $tcLabel }}</span>
                                    @if ($contact->temperature !== $tc)
                                        <button type="button" wire:click="appliquerTemperature" class="lb-btn lb-btn-ghost" style="padding:4px 10px;font-size:12px">Appliquer</button>
                                    @else
                                        <span class="lb-muted" style="font-size:12px">✓ déjà appliquée</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if (! empty($derniereAnalyse->promesses))
                            <div style="margin-top:12px">
                                <div class="lb-label">Promesses à tenir</div>
                                @foreach ($derniereAnalyse->promesses as $i => $pr)
                                    <div class="flex items-center gap-2" style="margin-top:6px;justify-content:space-between" wire:key="prom-{{ $i }}">
                                        <span style="min-width:0;flex:1">{{ $pr['texte'] ?? '' }}@if (filled($pr['echeance'] ?? null)) <span class="lb-muted" style="font-size:12px">· {{ $pr['echeance'] }}</span>@endif</span>
                                        <button type="button" wire:click="creerRappel({{ $i }})" class="lb-btn lb-btn-ghost" style="padding:4px 10px;font-size:12px;flex:none">+ Rappel</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @php $rdv = $derniereAnalyse->rdv ?? []; @endphp
                        @if (filled($rdv['date'] ?? null) || ($rdv['demande'] ?? false))
                            <div class="lb-kv" style="margin-top:12px"><div class="k">Rendez-vous</div>
                                <div class="v" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <span>@if (filled($rdv['date'] ?? null)){{ $rdv['date'] }}@else Demande de rendez-vous @endif@if (filled($rdv['details'] ?? null)) <span class="lb-muted">· {{ $rdv['details'] }}</span>@endif</span>
                                    <button type="button" wire:click="creerTacheProposerRdv" class="lb-btn lb-btn-ghost" style="padding:4px 10px;font-size:12px">Tâche : proposer un RDV</button>
                                    @if (filled($rdv['date'] ?? null))
                                        <button type="button" wire:click="creerRdv" class="lb-btn lb-btn-ghost" style="padding:4px 10px;font-size:12px">Créer le rendez-vous</button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($derniereAnalyse->brouillon)
                            <div style="margin-top:12px">
                                <div class="lb-label" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                    <span>Brouillon proposé</span>
                                    <span style="display:inline-flex;gap:14px">
                                        <button type="button" class="lb-accent" style="font-size:12px;font-weight:600;background:none;border:0;cursor:pointer"
                                                x-on:click="navigator.clipboard.writeText($refs.brouillon.value); copie = true; setTimeout(() => copie = false, 2000)">
                                            <span x-show="!copie">Copier</span>
                                            <span x-show="copie" x-cloak>Copié ✓</span>
                                        </button>
                                        <button type="button" class="lb-accent" style="font-size:12px;font-weight:600;background:none;border:0;cursor:pointer"
                                                x-on:click="copierAvecLiens($refs.brouillon.value); copieHtml = true; setTimeout(() => copieHtml = false, 2000)">
                                            <span x-show="!copieHtml">🔗 Copier avec liens cliquables</span>
                                            <span x-show="copieHtml" x-cloak>Copié ✓</span>
                                        </button>
                                    </span>
                                </div>
                                <textarea x-ref="brouillon" readonly rows="16" class="lb-field" style="margin-top:6px;min-height:240px;max-height:60vh;overflow-y:auto;resize:vertical;line-height:1.5">{{ $derniereAnalyse->brouillon }}</textarea>
                                <p class="lb-muted" style="font-size:11.5px;margin-top:4px">« Liens cliquables » copie le mail mis en forme : colle-le dans ta boîte mail et les libellés deviennent des liens (l'adresse longue est masquée).</p>
                            </div>
                        @endif

                        <p class="lb-muted" style="font-size:11.5px;margin-top:10px">
                            Analyse du {{ $derniereAnalyse->faite_le?->translatedFormat('d/m/Y à H:i') }} · tu copies, tu envoies depuis ta boîte mail.
                        </p>
                    </div>
                @else
                    <div class="lb-placeholder mt-3">Pas encore d'analyse. Clique sur « Analyser » pour obtenir un conseil de relance et un brouillon.</div>
                @endif
            </div>

            {{-- Prospection (assistant IA) --}}
            <div class="lb-card" style="padding:20px" x-data="{ copie: false, copieHtml: false }">
                <h3 class="lb-h3" style="font-size:19px">Prospection</h3>
                <label for="angleProspection" class="lb-label" style="font-weight:500;color:var(--text-muted);margin-top:10px">Angle (facultatif)</label>
                <div class="flex flex-wrap items-end gap-3" style="margin-top:4px">
                    <input id="angleProspection" type="text" wire:model="angleProspection" class="lb-field" style="flex:1;min-width:14rem"
                           placeholder="ex. enseigne le yin à Namur, croisée au salon…">
                    <button type="button" wire:click="redigerProspection" class="lb-btn lb-btn-primary"
                            style="white-space:nowrap" wire:loading.attr="disabled" wire:target="redigerProspection">
                        <span wire:loading.remove wire:target="redigerProspection">✍️ Rédiger un mail de prospection</span>
                        <span wire:loading wire:target="redigerProspection">Rédaction…</span>
                    </button>
                </div>

                @if ($prospectionErreur)
                    <div class="lb-alert lb-alert-danger mt-3">{{ $prospectionErreur }}</div>
                @endif

                @if ($prospectionCorps !== null)
                    <div style="margin-top:14px">
                        <label class="lb-label" style="font-weight:500;color:var(--text-muted)">Objet</label>
                        <input type="text" wire:model="prospectionObjet" x-ref="objet" class="lb-field" style="margin-top:4px">

                        <div class="lb-label" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px">
                            <span>Mail proposé</span>
                            <span style="display:inline-flex;gap:14px">
                                <button type="button" class="lb-accent" style="font-size:12px;font-weight:600;background:none;border:0;cursor:pointer"
                                        x-on:click="navigator.clipboard.writeText('Objet : ' + $refs.objet.value + '\n\n' + $refs.corps.value); copie = true; setTimeout(() => copie = false, 2000)">
                                    <span x-show="!copie">Copier</span>
                                    <span x-show="copie" x-cloak>Copié ✓</span>
                                </button>
                                <button type="button" class="lb-accent" style="font-size:12px;font-weight:600;background:none;border:0;cursor:pointer"
                                        x-on:click="copierAvecLiens('Objet : ' + $refs.objet.value + '\n\n' + $refs.corps.value); copieHtml = true; setTimeout(() => copieHtml = false, 2000)">
                                    <span x-show="!copieHtml">🔗 Copier avec liens cliquables</span>
                                    <span x-show="copieHtml" x-cloak>Copié ✓</span>
                                </button>
                            </span>
                        </div>
                        <textarea wire:model="prospectionCorps" x-ref="corps" rows="9" class="lb-field" style="margin-top:6px"></textarea>

                        <p class="lb-muted" style="font-size:11.5px;margin-top:10px">
                            Relis, ajuste, puis copie — tu l'envoies depuis ta boîte mail. « Liens cliquables » colle le mail mis en forme (libellés cliquables, URL longue masquée). Une tâche « à envoyer » a été ajoutée à ce contact pour garder la trace.
                        </p>
                    </div>
                @else
                    <p class="lb-muted" style="font-size:12.5px;margin-top:10px">
                        L'assistant rédige un brouillon à partir de ton <a href="{{ route('reglages.assistant') }}" wire:navigate style="color:var(--accent)">pitch de prospection</a>. Rien n'est envoyé automatiquement.
                    </p>
                @endif
            </div>
        </div>

        {{-- ===================== Colonne latérale ===================== --}}
        <div style="display:flex;flex-direction:column;gap:16px">

            {{-- Carte profil --}}
            <div class="lb-card" style="padding:22px;text-align:center">
                <span class="lb-av lb-av-{{ $contact->couleurAvatar() }} {{ $contact->estEntreprise() ? 'sq' : '' }}" style="width:76px;height:76px;font-size:26px;margin:0 auto">{{ $contact->initiales() }}</span>
                <h2 class="serif" style="font-size:24px;font-weight:600;color:var(--ink);margin-top:12px">{{ $contact->nomComplet() }}</h2>
                @if ($contact->estPersonne() && ($contact->fonction || $contact->entreprise))
                    <div class="lb-muted" style="font-size:13px;margin-top:2px">
                        {{ collect([$contact->fonction, $contact->entreprise?->nom])->filter()->join(' · ') }}
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-center gap-2 mt-3">
                    <span class="lb-badge {{ $contact->estEntreprise() ? 'lb-b-firm' : 'lb-b-person' }}">
                        <span class="lb-dot" style="background:{{ $contact->estEntreprise() ? 'var(--mint)' : 'var(--lav)' }}"></span>
                        {{ $contact->estEntreprise() ? 'Entreprise' : 'Personne' }}
                    </span>
                    @if ($contact->estArchive())
                        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">Archivé</span>
                    @endif
                    @if ($info = $contact->temperatureInfo())
                        <span class="lb-badge lb-av-{{ $info[1] === 'muted' ? 'gold' : $info[1] }}">{{ $contact->temperature === 'chaud' ? '🔥 ' : '' }}{{ $info[0] }}</span>
                    @endif
                    @foreach ($contact->etiquettes as $et)
                        <span class="lb-tag" style="background:var(--surface)"><span class="lb-dot" style="background:{{ $et->couleur ?: 'var(--gold)' }}"></span>{{ $et->nom }}</span>
                    @endforeach
                    @if ($contact->categorie)
                        <span class="lb-tag" style="background:var(--surface)"><span class="lb-dot" style="background:{{ $contact->categorie->couleur ?: 'var(--mint)' }}"></span>{{ $contact->categorie->libelleComplet() }}</span>
                    @endif
                </div>

                {{-- Actions rapides --}}
                <div class="flex flex-wrap items-center justify-center gap-2 mt-4">
                    @if ($contact->email)
                        <a href="mailto:{{ $contact->email }}" class="lb-iconbtn" title="Envoyer un e-mail" aria-label="Envoyer un e-mail">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </a>
                    @endif
                    @if ($contact->telephone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $contact->telephone) }}" class="lb-iconbtn" title="Appeler" aria-label="Appeler">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L20 13l1 4v3a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 0-1z"/></svg>
                        </a>
                    @endif
                    <a href="{{ route('contacts.modifier', $contact) }}" class="lb-iconbtn" title="Modifier" aria-label="Modifier" wire:navigate>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    </a>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2 mt-4">
                    <a href="{{ route('contacts.modifier', $contact) }}" class="lb-btn lb-btn-primary" style="padding:8px 15px;font-size:12.5px" wire:navigate>Modifier</a>
                    @if ($contact->estArchive())
                        <button type="button" wire:click="desarchiver" class="lb-btn lb-btn-ghost" style="padding:8px 15px;font-size:12.5px">Désarchiver</button>
                    @else
                        <button type="button" wire:click="archiver" class="lb-btn lb-btn-ghost" style="padding:8px 15px;font-size:12.5px">Archiver</button>
                    @endif
                    <button type="button" x-on:click="confirmerSuppression = true" class="lb-btn lb-btn-danger" style="padding:8px 15px;font-size:12.5px">Supprimer</button>
                </div>
            </div>

            {{-- Informations détaillées --}}
            <div class="lb-card" style="padding:20px">
                <h3 class="lb-h3" style="font-size:18px">Informations</h3>
                <div class="lb-kv" style="grid-template-columns:1fr;gap:14px;margin-top:12px">
                    <div>
                        <div class="k">E-mail</div>
                        <div class="val">
                            <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            @if ($contact->email)<a href="mailto:{{ $contact->email }}" class="lb-truncate">{{ $contact->email }}</a>@else — @endif
                        </div>
                    </div>
                    <div>
                        <div class="k">Téléphone</div>
                        <div class="val">
                            <svg viewBox="0 0 24 24"><path d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L20 13l1 4v3a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 0-1z"/></svg>
                            @if ($contact->telephone)<a href="tel:{{ preg_replace('/\s+/', '', $contact->telephone) }}">{{ $contact->telephone }}</a>@else — @endif
                        </div>
                    </div>

                    @if ($contact->estPersonne())
                        <div><div class="k">Fonction</div><div class="val">{{ $contact->fonction ?: '—' }}</div></div>
                        <div>
                            <div class="k">Entreprise</div>
                            <div class="val">
                                @if ($contact->entreprise)<a href="{{ route('contacts.fiche', $contact->entreprise) }}" wire:navigate>{{ $contact->entreprise->nom }}</a>@else — @endif
                            </div>
                        </div>
                    @else
                        <div>
                            <div class="k">Site web</div>
                            <div class="val">
                                @if ($contact->site_web)<a href="{{ $contact->site_web }}" target="_blank" rel="noopener" class="lb-truncate">{{ preg_replace('#^https?://#', '', $contact->site_web) }}</a>@else — @endif
                            </div>
                        </div>
                        <div><div class="k">Secteur</div><div class="val">{{ $contact->secteur ?: '—' }}</div></div>
                        <div><div class="k">N° d'entreprise (BCE)</div><div class="val">{{ $contact->numero_entreprise ?: '—' }}</div></div>
                        <div><div class="k">N° de TVA</div><div class="val">{{ $contact->numero_tva ?: '—' }}</div></div>
                    @endif

                    @if (filled($contact->url_profil))
                        <div>
                            <div class="k">Compte / lien</div>
                            <div class="val">
                                @if ($href = $contact->urlProfilHref())
                                    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="lb-truncate">🔗 {{ $contact->url_profil }}</a>
                                @else
                                    🔗 {{ $contact->url_profil }}
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($contact->libelleOrigine())
                        <div>
                            <div class="k">Origine du contact</div>
                            <div class="val">{{ $contact->libelleOrigine() }}@if (filled($contact->origine_precision)) <span class="lb-muted">— {{ $contact->origine_precision }}</span>@endif</div>
                        </div>
                    @endif

                    <div>
                        <div class="k">Adresse</div>
                        <div class="val">
                            {{ collect([$contact->adresse_rue, trim(($contact->adresse_code_postal ?? '').' '.($contact->adresse_ville ?? '')), $contact->adresse_pays])->filter()->join(', ') ?: '—' }}
                        </div>
                    </div>

                    @if ($contact->notes)
                        <div>
                            <div class="k">Notes</div>
                            <div class="val" style="white-space:pre-line;display:block;margin-top:6px">{{ $contact->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modale de suppression --}}
    <div x-cloak x-show="confirmerSuppression" x-transition class="lb-modal-backdrop"
         x-on:keydown.escape.window="confirmerSuppression = false">
        <div class="lb-modal" x-on:click.outside="confirmerSuppression = false">
            <h3 class="lb-h3" style="font-size:20px">Supprimer ce contact ?</h3>
            <p class="lb-muted" style="font-size:13.5px;margin-top:8px">
                Cette action est définitive.
                @if ($contact->estEntreprise() && $contact->personnes->isNotEmpty())
                    Les {{ $contact->personnes->count() }} personne(s) rattachée(s) ne seront pas supprimées : elles seront détachées de cette entreprise.
                @endif
            </p>
            <div class="flex justify-end gap-3 mt-5">
                <button type="button" x-on:click="confirmerSuppression = false" class="lb-btn lb-btn-ghost">Annuler</button>
                <button type="button" wire:click="supprimer" class="lb-btn lb-btn-danger">Supprimer définitivement</button>
            </div>
        </div>
    </div>

    <script>
        window.copierAvecLiens = window.copierAvecLiens || async function (texte) {
            const esc = function (s) {
                return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            };
            const lignes = texte.split('\n').map(function (ligne) {
                const m = ligne.match(/(https?:\/\/\S+)/);
                if (!m) { return esc(ligne); }
                const url = m[1];
                const avant = ligne.slice(0, m.index);
                const apres = ligne.slice(m.index + url.length);
                const puceMatch = avant.match(/^[\s\-–•*]+/);
                const puce = puceMatch ? puceMatch[0] : '';
                const label = avant.replace(/^[\s\-–•*]+/, '').replace(/[\s:–-]+$/, '').trim();
                const href = url.replace(/"/g, '%22');
                const lien = '<a href="' + href + '">' + esc(label || url) + '</a>';
                return esc(puce) + lien + esc(apres);
            });
            const html = lignes.join('<br>');
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([texte], { type: 'text/plain' })
                })]);
            } catch (e) {
                await navigator.clipboard.writeText(texte);
            }
        };
    </script>
</div>
