<div>
    <h1 class="lb-h1">Étiquettes</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">Classe tes contacts (client, prospect, fournisseur…)</p>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Ajout --}}
    <form wire:submit="ajouter" class="lb-card mt-5 flex flex-wrap items-end gap-4" style="padding:18px">
        <div style="flex:1;min-width:12rem">
            <label for="nom" class="lb-label">Nouvelle étiquette</label>
            <input id="nom" type="text" wire:model="nom" placeholder="ex. Client, Prospect, Fournisseur…" class="lb-field">
            @error('nom') <p class="lb-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="couleur" class="lb-label">Couleur</label>
            <input id="couleur" type="color" wire:model="couleur" class="lb-field" style="height:44px;width:60px;padding:4px;box-shadow:var(--raise-sm)">
        </div>
        <button type="submit" class="lb-btn lb-btn-primary">Ajouter</button>
    </form>

    {{-- Liste --}}
    <div class="lb-people mt-5">
        @forelse ($etiquettes as $et)
            <div wire:key="e-{{ $et->id }}" class="lb-prow">
                <span class="lb-dot" style="width:14px;height:14px;background:{{ $et->couleur ?? 'var(--gold)' }}"></span>
                <span class="nm">{{ $et->nom }}</span>
                <span class="lb-muted" style="font-size:12px">· {{ $et->contacts_count }} contact(s)</span>
                <button type="button" wire:click="supprimer({{ $et->id }})" wire:confirm="Supprimer cette étiquette ?"
                        class="lb-btn lb-btn-danger" style="margin-left:auto;padding:7px 13px;font-size:12.5px">Supprimer</button>
            </div>
        @empty
            <div class="lb-placeholder">Aucune étiquette pour l'instant.</div>
        @endforelse
    </div>

    <div class="mt-6">
        <a href="{{ route('contacts.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux contacts</a>
    </div>
</div>
