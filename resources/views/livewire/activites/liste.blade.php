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
    <div class="flex gap-2 mt-5">
        @foreach (['a_faire' => 'À faire', 'terminees' => 'Terminées', 'toutes' => 'Toutes'] as $cle => $lib)
            <button type="button" wire:click="$set('filtre', '{{ $cle }}')"
                    class="lb-pill" style="cursor:pointer;border:0;{{ $filtre === $cle ? 'color:var(--accent)' : '' }}">
                {{ $lib }}
            </button>
        @endforeach
    </div>

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
</div>
