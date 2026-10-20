<div x-data="{ confirmerSuppression: false }">
    <a href="{{ route('contacts.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour au carnet</a>

    {{-- En-tête --}}
    <div class="flex flex-wrap items-center gap-4 mt-4">
        <span class="lb-av lb-av-{{ $contact->couleurAvatar() }} {{ $contact->estEntreprise() ? 'sq' : '' }}" style="width:66px;height:66px;font-size:22px">{{ $contact->initiales() }}</span>
        <div>
            <h1 class="lb-h1" style="font-size:30px">{{ $contact->nomComplet() }}</h1>
            <div class="flex items-center gap-2 mt-2">
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
                @if ($src = $contact->sourceInfo())
                    <span class="lb-tag" style="background:var(--surface)"><span class="lb-dot" style="background:{{ $src[1] }}"></span>{{ $src[0] }}</span>
                @endif
                @if (filled($contact->url_profil))
                    @if ($href = $contact->urlProfilHref())
                        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="lb-tag" style="background:var(--surface);text-decoration:none">🔗 {{ $contact->url_profil }}</a>
                    @else
                        <span class="lb-tag" style="background:var(--surface)">🔗 {{ $contact->url_profil }}</span>
                    @endif
                @endif
                @if ($contact->categorie)
                    <span class="lb-tag" style="background:var(--surface)"><span class="lb-dot" style="background:{{ $contact->categorie->couleur ?: 'var(--mint)' }}"></span>{{ $contact->categorie->libelleComplet() }}</span>
                @endif
                @foreach ($contact->etiquettes as $et)
                    <span class="lb-tag" style="background:var(--surface)"><span class="lb-dot" style="background:{{ $et->couleur ?: 'var(--gold)' }}"></span>{{ $et->nom }}</span>
                @endforeach
            </div>
        </div>
        <div class="flex flex-wrap gap-2" style="margin-left:auto">
            <a href="{{ route('contacts.modifier', $contact) }}" class="lb-btn lb-btn-primary" wire:navigate>Modifier</a>
            @if ($contact->estArchive())
                <button type="button" wire:click="desarchiver" class="lb-btn lb-btn-ghost">Désarchiver</button>
            @else
                <button type="button" wire:click="archiver" class="lb-btn lb-btn-ghost">Archiver</button>
            @endif
            <button type="button" x-on:click="confirmerSuppression = true" class="lb-btn lb-btn-danger">Supprimer</button>
        </div>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    {{-- Coordonnées --}}
    <div class="lb-card mt-5" style="padding:22px 24px">
        <div class="lb-kv">
            <div>
                <div class="k">E-mail</div>
                <div class="val">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    @if ($contact->email)<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@else — @endif
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
                        @if ($contact->entreprise)
                            <a href="{{ route('contacts.fiche', $contact->entreprise) }}" wire:navigate>{{ $contact->entreprise->nom }}</a>
                        @else — @endif
                    </div>
                </div>
            @else
                <div><div class="k">N° d'entreprise (BCE)</div><div class="val">{{ $contact->numero_entreprise ?: '—' }}</div></div>
                <div><div class="k">N° de TVA</div><div class="val">{{ $contact->numero_tva ?: '—' }}</div></div>
                <div>
                    <div class="k">Site web</div>
                    <div class="val">
                        @if ($contact->site_web)<a href="{{ $contact->site_web }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $contact->site_web) }}</a>@else — @endif
                    </div>
                </div>
                <div><div class="k">Secteur</div><div class="val">{{ $contact->secteur ?: '—' }}</div></div>
            @endif

            <div style="grid-column:1/-1">
                <div class="k">Adresse</div>
                <div class="val">
                    {{ collect([$contact->adresse_rue, trim(($contact->adresse_code_postal ?? '').' '.($contact->adresse_ville ?? '')), $contact->adresse_pays])->filter()->join(', ') ?: '—' }}
                </div>
            </div>

            @if ($contact->notes)
                <div style="grid-column:1/-1">
                    <div class="k">Notes</div>
                    <div class="val" style="white-space:pre-line;display:block;margin-top:6px">{{ $contact->notes }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Personnes rattachées + activités --}}
    <div class="grid gap-6 lg:grid-cols-2 mt-5">
        @if ($contact->estEntreprise())
            <div>
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

        <div>
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

            {{-- Inscriptions aux sessions --}}
            @if ($contact->inscriptions->isNotEmpty())
                <h3 class="lb-h3 mt-5" style="font-size:19px">Inscriptions</h3>
                <div class="lb-people mt-3">
                    @foreach ($contact->inscriptions as $ins)
                        @if ($ins->seance)
                            <a href="{{ route('sessions.fiche', $ins->seance) }}" class="lb-prow" wire:navigate wire:key="ins-{{ $ins->id }}" style="text-decoration:none">
                                <span class="lb-tag" style="{{ $ins->estEnAttente() ? 'background:var(--gold-tint);color:var(--accent)' : 'background:var(--mint-tint);color:var(--mint-deep)' }}">{{ $ins->estEnAttente() ? 'En attente' : 'Confirmé' }}</span>
                                <div style="min-width:0">
                                    <div class="nm lb-truncate">{{ $ins->seance->titre }}</div>
                                    @if ($ins->seance->date_debut)
                                        <div class="mt">{{ $ins->seance->date_debut->translatedFormat('d/m/Y') }}</div>
                                    @endif
                                </div>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- Fil des e-mails (assistant) --}}
            <div class="flex items-center justify-between mt-5">
                <h3 class="lb-h3" style="font-size:19px">Emails</h3>
                @if ($contact->emails->isNotEmpty())
                    <span class="lb-muted" style="font-size:12px">{{ $contact->emails->count() }} message(s)</span>
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
                    </div>
                @empty
                    <div class="lb-placeholder">Aucun e-mail synchronisé.</div>
                @endforelse
            </div>

            {{-- Analyse & relances (assistant IA) --}}
            <div class="flex items-center justify-between mt-5">
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
                <div class="lb-card-sm mt-3" x-data="{ copie: false }">
                    <div class="lb-kv"><div class="k">Résumé</div><div class="v">{{ $derniereAnalyse->resume }}</div></div>
                    @if ($derniereAnalyse->verdict)
                        <div class="lb-kv" style="margin-top:8px"><div class="k">À relancer ?</div><div class="v">{{ $derniereAnalyse->verdict }}@if ($derniereAnalyse->delai_conseille) <span class="lb-muted">· {{ $derniereAnalyse->delai_conseille }}</span>@endif</div></div>
                    @endif

                    @if ($derniereAnalyse->brouillon)
                        <div style="margin-top:12px">
                            <div class="lb-label" style="display:flex;align-items:center;justify-content:space-between">
                                <span>Brouillon proposé</span>
                                <button type="button" class="lb-accent" style="font-size:12px;font-weight:600;background:none;border:0;cursor:pointer"
                                        x-on:click="navigator.clipboard.writeText($refs.brouillon.value); copie = true; setTimeout(() => copie = false, 2000)">
                                    <span x-show="!copie">Copier</span>
                                    <span x-show="copie" x-cloak>Copié ✓</span>
                                </button>
                            </div>
                            <textarea x-ref="brouillon" readonly rows="6" class="lb-field" style="margin-top:6px">{{ $derniereAnalyse->brouillon }}</textarea>
                        </div>
                    @endif

                    <p class="lb-muted" style="font-size:11.5px;margin-top:10px">
                        Analyse du {{ $derniereAnalyse->faite_le?->translatedFormat('d/m/Y à H:i') }} · tu copies, tu envoies depuis ta boîte mail.
                    </p>
                </div>
            @else
                <div class="lb-placeholder mt-3">Pas encore d'analyse. Clique sur « Analyser » pour obtenir un conseil de relance et un brouillon.</div>
            @endif

            {{-- Prospection (assistant IA) --}}
            <div class="flex items-center justify-between mt-5">
                <h3 class="lb-h3" style="font-size:19px">Prospection</h3>
            </div>
            <div class="lb-card-sm mt-3" x-data="{ copie: false }">
                <label for="angleProspection" class="lb-label" style="font-weight:500;color:var(--text-muted)">Angle (facultatif)</label>
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

                        <div class="lb-label" style="display:flex;align-items:center;justify-content:space-between;margin-top:12px">
                            <span>Mail proposé</span>
                            <button type="button" class="lb-accent" style="font-size:12px;font-weight:600;background:none;border:0;cursor:pointer"
                                    x-on:click="navigator.clipboard.writeText('Objet : ' + $refs.objet.value + '\n\n' + $refs.corps.value); copie = true; setTimeout(() => copie = false, 2000)">
                                <span x-show="!copie">Copier</span>
                                <span x-show="copie" x-cloak>Copié ✓</span>
                            </button>
                        </div>
                        <textarea wire:model="prospectionCorps" x-ref="corps" rows="9" class="lb-field" style="margin-top:6px"></textarea>

                        <p class="lb-muted" style="font-size:11.5px;margin-top:10px">
                            Relis, ajuste, puis copie — tu l'envoies depuis ta boîte mail. Une tâche « à envoyer » a été ajoutée à ce contact pour garder la trace.
                        </p>
                    </div>
                @else
                    <p class="lb-muted" style="font-size:12.5px;margin-top:10px">
                        L'assistant rédige un brouillon à partir de ton <a href="{{ route('reglages.assistant') }}" wire:navigate style="color:var(--accent)">pitch de prospection</a>. Rien n'est envoyé automatiquement.
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-between mt-5">
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
</div>
