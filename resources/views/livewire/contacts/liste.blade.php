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

        <select wire:model.live="tri" class="lb-select" aria-label="Trier par">
            <option value="nom">Trier : nom</option>
            <option value="updated_at">Trier : récent</option>
        </select>
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
