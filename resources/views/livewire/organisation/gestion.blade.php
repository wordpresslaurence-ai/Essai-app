<div>
    <h1 class="lb-h1">Organisation</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">
        Range ton activité par <strong>pôle</strong> (Prana, Digital…) puis par <strong>catégorie</strong>
        (Formation profs, Recherche de salle, Sites web…). Crée librement une nouvelle section dès que tu lances une offre ou un produit.
    </p>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    <div class="mt-5 flex flex-wrap gap-4">
        {{-- Nouveau pôle --}}
        <form wire:submit="ajouterPole" class="lb-card flex flex-wrap items-end gap-4" style="padding:18px;flex:1;min-width:18rem">
            <div style="flex:1;min-width:10rem">
                <label for="poleNom" class="lb-label">Nouveau pôle</label>
                <input id="poleNom" type="text" wire:model="poleNom" placeholder="ex. Prana, Digital…" class="lb-field">
                @error('poleNom') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="poleCouleur" class="lb-label">Couleur</label>
                <input id="poleCouleur" type="color" wire:model="poleCouleur" class="lb-field" style="height:44px;width:60px;padding:4px;box-shadow:var(--raise-sm)">
            </div>
            <button type="submit" class="lb-btn lb-btn-primary">Ajouter le pôle</button>
        </form>

        {{-- Nouvelle catégorie --}}
        <form wire:submit="ajouterCategorie" class="lb-card flex flex-wrap items-end gap-4" style="padding:18px;flex:1;min-width:18rem">
            <div style="min-width:9rem">
                <label for="categoriePoleId" class="lb-label">Dans le pôle</label>
                <select id="categoriePoleId" wire:model="categoriePoleId" class="lb-field">
                    <option value="">— Choisir —</option>
                    @foreach ($poles as $p)
                        <option value="{{ $p->id }}">{{ $p->nom }}</option>
                    @endforeach
                </select>
                @error('categoriePoleId') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div style="flex:1;min-width:10rem">
                <label for="categorieNom" class="lb-label">Nouvelle catégorie</label>
                <input id="categorieNom" type="text" wire:model="categorieNom" placeholder="ex. Formation profs, Sites web…" class="lb-field">
                @error('categorieNom') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="categorieCouleur" class="lb-label">Couleur</label>
                <input id="categorieCouleur" type="color" wire:model="categorieCouleur" class="lb-field" style="height:44px;width:60px;padding:4px;box-shadow:var(--raise-sm)">
            </div>
            <button type="submit" class="lb-btn lb-btn-primary">Ajouter la catégorie</button>
        </form>
    </div>

    {{-- Liste des pôles et de leurs catégories --}}
    <div class="mt-6" style="display:flex;flex-direction:column;gap:14px">
        @forelse ($poles as $pole)
            <div wire:key="pole-{{ $pole->id }}" class="lb-card" style="padding:18px">
                <div class="flex items-center gap-3">
                    <span class="lb-dot" style="width:16px;height:16px;background:{{ $pole->couleur ?? 'var(--mint)' }}"></span>
                    @if ($poleEditId === $pole->id)
                        <form wire:submit="renommerPole" class="flex items-center gap-2" style="flex:1">
                            <input type="text" wire:model="poleEditNom" class="lb-field" style="max-width:16rem" autofocus>
                            <button type="submit" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px">Enregistrer</button>
                            <button type="button" wire:click="annulerEditionPole" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Annuler</button>
                            @error('poleEditNom') <span class="lb-error">{{ $message }}</span> @enderror
                        </form>
                    @else
                        <span style="font-weight:600;font-size:15px">{{ $pole->nom }}</span>
                        <span class="lb-muted" style="font-size:12px">· {{ $pole->categories->count() }} catégorie(s)</span>
                        <button type="button" wire:click="editerPole({{ $pole->id }})"
                                class="lb-btn lb-btn-ghost" style="margin-left:auto;padding:7px 13px;font-size:12.5px">Renommer</button>
                        <button type="button" wire:click="supprimerPole({{ $pole->id }})"
                                wire:confirm="Supprimer le pôle « {{ $pole->nom }} » et ses catégories ? Les contacts et opportunités seront simplement détachés."
                                class="lb-btn lb-btn-danger" style="padding:7px 13px;font-size:12.5px">Supprimer</button>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse ($pole->categories as $cat)
                        @if ($categorieEditId === $cat->id)
                            <form wire:key="cat-edit-{{ $cat->id }}" wire:submit="renommerCategorie" class="flex items-center gap-2">
                                <input type="text" wire:model="categorieEditNom" class="lb-field" style="max-width:14rem" autofocus>
                                <button type="submit" class="lb-btn lb-btn-primary" style="padding:6px 11px;font-size:12px">OK</button>
                                <button type="button" wire:click="annulerEditionCategorie" class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px">Annuler</button>
                                @error('categorieEditNom') <span class="lb-error">{{ $message }}</span> @enderror
                            </form>
                        @else
                            <span wire:key="cat-{{ $cat->id }}" class="lb-tag" style="gap:7px">
                                <span class="lb-dot" style="width:11px;height:11px;background:{{ $cat->couleur ?? 'var(--lav)' }}"></span>
                                {{ $cat->nom }}
                                <button type="button" wire:click="editerCategorie({{ $cat->id }})"
                                        title="Renommer" aria-label="Renommer la catégorie"
                                        style="border:0;background:none;cursor:pointer;color:var(--text-muted);font-size:12px;line-height:1">✎</button>
                                <button type="button" wire:click="supprimerCategorie({{ $cat->id }})"
                                        wire:confirm="Supprimer la catégorie « {{ $cat->nom }} » ?"
                                        title="Supprimer" aria-label="Supprimer la catégorie"
                                        style="border:0;background:none;cursor:pointer;color:var(--text-muted);font-size:15px;line-height:1">×</button>
                            </span>
                        @endif
                    @empty
                        <span class="lb-muted" style="font-size:12.5px">Aucune catégorie dans ce pôle pour l'instant.</span>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="lb-placeholder">Aucun pôle pour l'instant. Commence par en créer un ci-dessus.</div>
        @endforelse
    </div>
</div>
