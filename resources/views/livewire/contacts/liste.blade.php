<div>
    {{-- En-tête --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="lb-eyebrow">{{ $contacts->total() }} contact{{ $contacts->total() > 1 ? 's' : '' }}</div>
            <h1 class="lb-h1">Mon carnet</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('contacts.newsletter') }}" class="lb-btn lb-btn-ghost" wire:navigate>Export newsletter</a>
            <a href="{{ route('contacts.import') }}" class="lb-btn lb-btn-ghost" wire:navigate>Importer un CSV</a>
            <a href="{{ route('contacts.creer') }}" class="lb-btn lb-btn-primary" wire:navigate>+ Nouveau contact</a>
        </div>
    </div>

    {{-- Message flash --}}
    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Alerte doublons --}}
    @if ($nbDoublons > 0 && ! $doublons)
        <div class="lb-alert mt-5 flex flex-wrap items-center justify-between gap-3" style="background:var(--gold-tint);color:var(--ink)">
            <span>⚠️ {{ $nbDoublons }} fiches partagent leur adresse e-mail avec une autre fiche : ce sont probablement des doublons.</span>
            <button type="button" wire:click="$set('doublons', true)" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Voir les doublons</button>
        </div>
    @elseif ($doublons)
        <div class="lb-alert mt-5" style="background:var(--gold-tint);color:var(--ink)">
            Fiches en double, rangées par adresse e-mail : garde la bonne (catégorie, mails, activités) et supprime l'autre depuis sa fiche.
            <button type="button" wire:click="$set('doublons', false)" class="lb-accent" style="background:none;border:0;cursor:pointer;font-weight:600;margin-left:6px">Revenir au carnet complet</button>
        </div>
    @endif

    {{-- Barre de filtres --}}
    <div class="flex flex-wrap items-center gap-3 mt-6">
        <label class="lb-search" style="flex:1;min-width:220px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;flex:none"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
            <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Rechercher un nom, un e-mail, un téléphone…" aria-label="Rechercher">
        </label>

        <select wire:model.live="type" class="lb-select" aria-label="Type">
            <option value="">Tous les types</option>
            <option value="personne">Personnes</option>
            <option value="entreprise">Entreprises</option>
        </select>

        <select wire:model.live="etiquette" class="lb-select" aria-label="Étiquette">
            <option value="">Toutes les étiquettes</option>
            @foreach ($etiquettes as $et)
                <option value="{{ $et->id }}">{{ $et->nom }}</option>
            @endforeach
        </select>

        <select wire:model.live="statut" class="lb-select" aria-label="Statut">
            <option value="actifs">Actifs</option>
            <option value="archives">Archivés</option>
        </select>

        <select wire:model.live="temperature" class="lb-select" aria-label="Température">
            <option value="">Toutes températures</option>
            <option value="leads">Leads uniquement</option>
            @foreach (\App\Models\Contact::TEMPERATURES as $cle => $info)
                <option value="{{ $cle }}">{{ $info[0] }}</option>
            @endforeach
        </select>

        <select wire:model.live="origine" class="lb-select" aria-label="Origine">
            <option value="">Toutes origines</option>
            @foreach (\App\Models\Contact::ORIGINES as $cle => $libelle)
                <option value="{{ $cle }}">{{ $libelle }}</option>
            @endforeach
        </select>

        @if ($poles->isNotEmpty())
            <select wire:model.live="pole" class="lb-select" aria-label="Pôle">
                <option value="">Tous les pôles</option>
                @foreach ($poles as $p)
                    <option value="{{ $p->id }}">{{ $p->nom }}</option>
                @endforeach
            </select>

            {{-- Filtre catégorie toujours disponible, groupé par pôle --}}
            <select wire:model.live="categorie" class="lb-select" aria-label="Catégorie">
                <option value="">Toutes les catégories</option>
                <option value="aucune">— Sans catégorie —</option>
                @foreach ($poles as $p)
                    @if ($p->categories->isNotEmpty())
                        <optgroup label="{{ $p->nom }}">
                            @foreach ($p->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
        @endif

        <select wire:model.live="mails" class="lb-select" aria-label="Mails envoyés">
            <option value="">Tous (mails)</option>
            <option value="sans">Jamais écrit</option>
            <option value="avec">Déjà écrit</option>
        </select>

        <select wire:model.live="tri" class="lb-select" aria-label="Trier par">
            <option value="nom">Trier : nom</option>
            <option value="updated_at">Trier : récent</option>
        </select>

        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text-muted)">
            <input type="checkbox" wire:model.live="inclureMasques" style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
            Afficher les masqués
        </label>

        @if ($this->filtresActifs())
            <button type="button" wire:click="reinitialiserFiltres" class="lb-btn lb-btn-ghost" style="padding:8px 13px;font-size:12.5px">✕ Effacer les filtres</button>
        @endif
    </div>

    {{-- Cartes --}}
    <div class="lb-cards mt-6">
        @forelse ($contacts as $contact)
            <div class="lb-ccard" wire:key="c-{{ $contact->id }}" style="flex-wrap:wrap">
                <a href="{{ route('contacts.fiche', $contact) }}" wire:navigate
                   style="display:flex;gap:14px;align-items:flex-start;flex:1;min-width:200px;text-decoration:none;color:inherit">
                    <span class="lb-av lb-av-44 lb-av-{{ $contact->couleurAvatar() }} {{ $contact->estEntreprise() ? 'sq' : '' }}">{{ $contact->initiales() }}</span>
                    <div style="min-width:0;flex:1">
                        <div class="nm lb-truncate">
                            {{ $contact->nomComplet() }}
                            @if ($envoi = $contact->dernierEnvoi())
                                <span title="Dernier mail envoyé le {{ $envoi->translatedFormat('j F Y') }}" aria-label="Dernier mail envoyé le {{ $envoi->translatedFormat('j F Y') }}"
                                      style="display:inline-flex;align-items:center;gap:4px;vertical-align:middle;font-size:11.5px;font-weight:500;color:var(--mint-deep);background:var(--mint-tint);border-radius:999px;padding:1px 7px;margin-left:4px;white-space:nowrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:13px;height:13px;flex:none;color:var(--envoi)"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>{{ $envoi->format('d/m') }}</span>
                            @endif
                            @if ($contact->estArchive()) <span class="lb-muted" style="font-size:11px;font-weight:400">· archivé</span> @endif
                        </div>
                        <div class="meta lb-truncate">
                            {{ $contact->estEntreprise() ? ($contact->secteur ?: 'Entreprise') : ($contact->fonction ?: 'Personne') }}
                            @if ($contact->entreprise) · {{ $contact->entreprise->nom }} @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <span class="lb-badge {{ $contact->estEntreprise() ? 'lb-b-firm' : 'lb-b-person' }}">
                                <span class="lb-dot" style="background:{{ $contact->estEntreprise() ? 'var(--mint)' : 'var(--lav)' }}"></span>
                                {{ $contact->estEntreprise() ? 'Entreprise' : 'Personne' }}
                            </span>
                            @if ($info = $contact->temperatureInfo())
                                <span class="lb-badge lb-av-{{ $info[1] === 'muted' ? 'gold' : $info[1] }}">{{ $contact->temperature === 'chaud' ? '🔥 ' : '' }}{{ $info[0] }}</span>
                            @endif
                            @if ($contact->libelleOrigine())
                                <span class="lb-tag" style="background:var(--surface)">📍 {{ $contact->libelleOrigine() }}</span>
                            @endif
                            @foreach ($contact->etiquettes as $et)
                                <span class="lb-tag" style="background:var(--surface)">
                                    <span class="lb-dot" style="background:{{ $et->couleur ?: 'var(--gold)' }}"></span>{{ $et->nom }}
                                </span>
                            @endforeach
                            @if ($contact->email)
                                <span class="lb-accent" style="font-size:12.5px;font-weight:500">{{ $contact->email }}</span>
                            @endif
                        </div>
                    </div>
                </a>

                {{-- Pôle / catégorie : modifiable directement depuis la liste --}}
                <div style="flex:none;width:200px;max-width:100%">
                    <label class="lb-muted" style="font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;font-weight:600;display:block;margin-bottom:4px">Pôle / catégorie</label>
                    <select class="lb-select" style="width:100%;font-size:12.5px;padding:8px 30px 8px 12px"
                            aria-label="Pôle et catégorie du contact"
                            wire:change="changerCategorie({{ $contact->id }}, $event.target.value)">
                        <option value="">— Aucune —</option>
                        @foreach ($poles as $p)
                            <optgroup label="{{ $p->nom }}">
                                @foreach ($p->categories as $cat)
                                    <option value="{{ $cat->id }}" @selected((int) $contact->categorie_id === (int) $cat->id)>{{ $cat->nom }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
        @empty
            <div class="lb-placeholder" style="grid-column:1/-1">
                Aucun contact trouvé.
                <a href="{{ route('contacts.creer') }}" class="lb-accent" style="font-weight:600" wire:navigate>En créer un ?</a>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-6">{{ $contacts->links() }}</div>
</div>
