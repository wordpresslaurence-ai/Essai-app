<div x-data="{ confirmerSuppression: false }">
    <a href="{{ route('activites.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux activités</a>

    <h1 class="lb-h1 mt-4">{{ $activite ? "Modifier l'activité" : 'Nouvelle activité' }}</h1>

    <form wire:submit="enregistrer" class="lb-card mt-5" style="padding:24px;max-width:640px">
        <div>
            <label for="titre" class="lb-label">Titre <span class="lb-req">*</span></label>
            <input id="titre" type="text" wire:model="titre" class="lb-field" placeholder="ex. Rappeler Marie Dubois">
            @error('titre') <p class="lb-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="type" class="lb-label">Type</label>
                <select id="type" wire:model="type" class="lb-field">
                    @foreach ($libelles as $cle => $lib)<option value="{{ $cle }}">{{ $lib }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="echeance" class="lb-label">Échéance</label>
                <input id="echeance" type="datetime-local" wire:model="echeance" class="lb-field">
                @error('echeance') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="contact_id" class="lb-label">Contact</label>
                <select id="contact_id" wire:model="contact_id" class="lb-field">
                    <option value="">— Aucun —</option>
                    @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->nomComplet() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="opportunite_id" class="lb-label">Opportunité</label>
                <select id="opportunite_id" wire:model="opportunite_id" class="lb-field">
                    <option value="">— Aucune —</option>
                    @foreach ($opportunites as $o)<option value="{{ $o->id }}">{{ $o->titre }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="mt-5">
            <label for="notes" class="lb-label">Notes</label>
            <textarea id="notes" rows="3" wire:model="notes" class="lb-field"></textarea>
        </div>

        <div class="flex items-center justify-between mt-7">
            <div>
                @if ($activite)
                    <button type="button" x-on:click="confirmerSuppression = true" class="lb-btn lb-btn-danger">Supprimer</button>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('activites.index') }}" class="lb-muted" style="font-size:13.5px;text-decoration:none" wire:navigate>Annuler</a>
                <button type="submit" class="lb-btn lb-btn-primary">Enregistrer</button>
            </div>
        </div>
    </form>

    @if ($activite)
        <div x-cloak x-show="confirmerSuppression" x-transition class="lb-modal-backdrop" x-on:keydown.escape.window="confirmerSuppression = false">
            <div class="lb-modal" x-on:click.outside="confirmerSuppression = false">
                <h3 class="lb-h3" style="font-size:20px">Supprimer cette activité ?</h3>
                <p class="lb-muted" style="font-size:13.5px;margin-top:8px">Cette action est définitive.</p>
                <div class="flex justify-end gap-3 mt-5">
                    <button type="button" x-on:click="confirmerSuppression = false" class="lb-btn lb-btn-ghost">Annuler</button>
                    <button type="button" wire:click="supprimer" class="lb-btn lb-btn-danger">Supprimer définitivement</button>
                </div>
            </div>
        </div>
    @endif
</div>
