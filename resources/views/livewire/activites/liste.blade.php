<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="lb-eyebrow">Tâches &amp; rappels</div>
            <h1 class="lb-h1">Mes activités</h1>
        </div>
        <a href="{{ route('activites.creer') }}" class="lb-btn lb-btn-primary" wire:navigate>+ Nouvelle activité</a>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Ajout rapide --}}
    <form wire:submit="ajouter" class="lb-card mt-5 flex flex-wrap items-end gap-3" style="padding:16px">
        <div style="flex:1;min-width:180px">
            <label for="titre" class="lb-label">Nouvelle activité</label>
            <input id="titre" type="text" wire:model="titre" placeholder="ex. Rappeler Marie" class="lb-field">
            @error('titre') <p class="lb-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="type" class="lb-label">Type</label>
            <select id="type" wire:model="type" class="lb-field">
                @foreach ($libelles as $cle => $lib)<option value="{{ $cle }}">{{ $lib }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="echeance" class="lb-label">Échéance</label>
            <input id="echeance" type="date" wire:model="echeance" class="lb-field">
        </div>
        <div>
            <label for="contact_id" class="lb-label">Contact</label>
            <select id="contact_id" wire:model="contact_id" class="lb-field">
                <option value="">—</option>
                @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->nomComplet() }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="lb-btn lb-btn-primary">Ajouter</button>
    </form>

    {{-- Filtres --}}
    <div class="flex flex-wrap items-center gap-2 mt-5">
        @foreach (['a_faire' => 'À faire', 'terminees' => 'Terminées', 'toutes' => 'Toutes'] as $cle => $lib)
            <button type="button" wire:click="$set('filtre', '{{ $cle }}')"
                    class="lb-pill" style="cursor:pointer;border:0;{{ $filtre === $cle ? 'color:var(--accent)' : '' }}">
                {{ $lib }}
            </button>
        @endforeach

        @if ($poles->isNotEmpty())
            <select wire:model.live="pole" class="lb-select" aria-label="Pôle" style="margin-left:auto">
                <option value="">Tous les pôles</option>
                @foreach ($poles as $p)
                    <option value="{{ $p->id }}">{{ $p->nom }}</option>
                @endforeach
            </select>

            @php($poleActif = $pole !== '' ? $poles->firstWhere('id', (int) $pole) : null)
            @if ($poleActif && $poleActif->categories->isNotEmpty())
                <select wire:model.live="categorie" class="lb-select" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach ($poleActif->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                    @endforeach
                </select>
            @endif
        @endif
    </div>

    {{-- Bascule Liste / Calendrier --}}
    <div class="flex flex-wrap items-center gap-3 mt-4">
        <div class="flex gap-1" style="background:var(--bg);border-radius:999px;padding:4px;box-shadow:var(--inset)">
            <button type="button" wire:click="$set('vue','liste')" class="lb-pill" style="border:0;cursor:pointer;{{ $vue === 'liste' ? 'color:var(--accent);box-shadow:var(--raise-sm)' : 'background:transparent;box-shadow:none' }}">Liste</button>
            <button type="button" wire:click="$set('vue','calendrier')" class="lb-pill" style="border:0;cursor:pointer;{{ $vue === 'calendrier' ? 'color:var(--accent);box-shadow:var(--raise-sm)' : 'background:transparent;box-shadow:none' }}">Calendrier</button>
        </div>

        @if ($vue === 'calendrier')
            <div class="flex gap-1" style="background:var(--bg);border-radius:999px;padding:4px;box-shadow:var(--inset)">
                <button type="button" wire:click="$set('modeCalendrier','mois')" class="lb-pill" style="border:0;cursor:pointer;{{ $modeCalendrier === 'mois' ? 'color:var(--accent);box-shadow:var(--raise-sm)' : 'background:transparent;box-shadow:none' }}">Mois</button>
                <button type="button" wire:click="$set('modeCalendrier','semaine')" class="lb-pill" style="border:0;cursor:pointer;{{ $modeCalendrier === 'semaine' ? 'color:var(--accent);box-shadow:var(--raise-sm)' : 'background:transparent;box-shadow:none' }}">Semaine</button>
            </div>

            <div class="flex items-center gap-2" style="margin-left:auto">
                <button type="button" wire:click="periodePrecedente" class="lb-iconbtn" style="font-size:18px" aria-label="Période précédente">‹</button>
                <button type="button" wire:click="aujourdhui" class="lb-pill" style="border:0;cursor:pointer">Aujourd'hui</button>
                <button type="button" wire:click="periodeSuivante" class="lb-iconbtn" style="font-size:18px" aria-label="Période suivante">›</button>
                <span class="serif" style="font-size:16px;font-weight:600;margin-left:6px">{{ $calendrier['libelle'] }}</span>
            </div>
        @endif
    </div>

    @if ($vue === 'liste')
    {{-- Liste --}}
    <div class="lb-people mt-4">
        @forelse ($activites as $a)
            <div class="lb-prow" wire:key="a-{{ $a->id }}">
                <button type="button" class="lb-check {{ $a->estTerminee() ? 'done' : '' }}" wire:click="basculer({{ $a->id }})"
                        aria-label="{{ $a->estTerminee() ? 'Marquer à faire' : 'Marquer terminée' }}">
                    <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </button>
                <span class="lb-badge lb-av-{{ \App\Models\Activite::couleurType($a->type) }}" style="box-shadow:var(--raise-sm)">{{ $a->libelleType() }}</span>
                <div style="min-width:0">
                    <div class="nm lb-truncate" style="{{ $a->estTerminee() ? 'text-decoration:line-through;color:var(--text-muted)' : '' }}">{{ $a->titre }}</div>
                    <div class="mt lb-truncate">
                        @if ($a->contact) {{ $a->contact->nomComplet() }} @endif
                        @if ($a->contact && $a->echeance) · @endif
                        @if ($a->echeance)
                            <span style="{{ $a->enRetard() ? 'color:var(--terra);font-weight:600' : '' }}">
                                {{ $a->enRetard() ? 'En retard — ' : '' }}{{ $a->echeance->translatedFormat('d/m/Y') }}
                            </span>
                        @endif
                    </div>
                </div>
                <div style="margin-left:auto" class="flex items-center gap-2">
                    @if ($a->contact)
                        <a href="{{ route('contacts.fiche', $a->contact) }}" class="lb-muted" style="font-size:12.5px;font-weight:600;text-decoration:none" wire:navigate>Fiche</a>
                    @endif
                    <a href="{{ route('activites.modifier', $a) }}" class="lb-accent" style="font-size:12.5px;font-weight:600;text-decoration:none" wire:navigate>Modifier</a>
                    <button type="button" wire:click="supprimer({{ $a->id }})" wire:confirm="Supprimer cette activité ?"
                            class="lb-iconbtn" style="width:30px;height:30px;color:var(--terra)" aria-label="Supprimer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                    </button>
                </div>
            </div>
        @empty
            <div class="lb-placeholder">Aucune activité {{ $filtre === 'a_faire' ? 'à faire' : ($filtre === 'terminees' ? 'terminée' : '') }}.</div>
        @endforelse
    </div>
    @else
        @include('livewire.activites._calendrier')
    @endif
</div>
