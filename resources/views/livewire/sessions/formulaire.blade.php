<div x-data="{ confirmerSuppression: false }">
    <a href="{{ route('sessions.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux sessions</a>

    <h1 class="lb-h1 mt-4">{{ $seance ? 'Modifier la session' : 'Nouvelle session' }}</h1>

    <form wire:submit="enregistrer" class="lb-card mt-5" style="padding:24px;max-width:640px">
        <div>
            <label for="titre" class="lb-label">Titre <span class="lb-req">*</span></label>
            <input id="titre" type="text" wire:model="titre" class="lb-field" placeholder="ex. Formation profs — Promo mars 2027">
            @error('titre') <p class="lb-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="categorie_id" class="lb-label">Catégorie</label>
                @if ($poles->isEmpty())
                    <p class="lb-muted" style="font-size:13px;margin-top:6px">
                        Aucun pôle. <a href="{{ route('organisation.index') }}" wire:navigate style="color:var(--accent)">En créer</a>.
                    </p>
                @else
                    <select id="categorie_id" wire:model="categorie_id" class="lb-field">
                        <option value="">— Sans catégorie —</option>
                        @foreach ($poles as $pole)
                            <optgroup label="{{ $pole->nom }}">
                                @foreach ($pole->categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('categorie_id') <p class="lb-error">{{ $message }}</p> @enderror
                @endif
            </div>
            <div>
                <label for="capacite" class="lb-label">Nombre de places</label>
                <input id="capacite" type="number" min="0" wire:model="capacite" class="lb-field" placeholder="ex. 10 (vide = illimité)">
                @error('capacite') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="date_debut" class="lb-label">Date</label>
                <input id="date_debut" type="datetime-local" wire:model="date_debut" class="lb-field">
                @error('date_debut') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lieu" class="lb-label">Lieu</label>
                <input id="lieu" type="text" wire:model="lieu" class="lb-field" placeholder="ex. Namur">
                @error('lieu') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5">
            <label for="notes" class="lb-label">Notes</label>
            <textarea id="notes" rows="4" wire:model="notes" class="lb-field" placeholder="ex. 3 stages de 3 jours : 10-12 mars, 14-16 avril, 12-14 mai…"></textarea>
        </div>

        <div class="flex items-center justify-between mt-7">
            <div>
                @if ($seance)
                    <button type="button" x-on:click="confirmerSuppression = true" class="lb-btn lb-btn-danger">Supprimer</button>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('sessions.index') }}" class="lb-muted" style="font-size:13.5px;text-decoration:none" wire:navigate>Annuler</a>
                <button type="submit" class="lb-btn lb-btn-primary">Enregistrer</button>
            </div>
        </div>
    </form>

    @if ($seance)
        <div x-cloak x-show="confirmerSuppression" x-transition class="lb-modal-backdrop" x-on:keydown.escape.window="confirmerSuppression = false">
            <div class="lb-modal" x-on:click.outside="confirmerSuppression = false">
                <h3 class="lb-h3" style="font-size:20px">Supprimer cette session ?</h3>
                <p class="lb-muted" style="font-size:13.5px;margin-top:8px">Les inscriptions liées seront aussi supprimées. Cette action est définitive.</p>
                <div class="flex justify-end gap-3 mt-5">
                    <button type="button" x-on:click="confirmerSuppression = false" class="lb-btn lb-btn-ghost">Annuler</button>
                    <button type="button" wire:click="supprimer" class="lb-btn lb-btn-danger">Supprimer définitivement</button>
                </div>
            </div>
        </div>
    @endif
</div>
