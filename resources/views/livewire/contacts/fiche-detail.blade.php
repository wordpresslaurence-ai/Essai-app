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
