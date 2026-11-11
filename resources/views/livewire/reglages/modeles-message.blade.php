<div>
    <a href="{{ route('reglages.assistant') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux réglages</a>

    <div class="flex flex-wrap items-end justify-between gap-4 mt-4">
        <div>
            <h1 class="lb-h1">Modèles de messages</h1>
            <p class="lb-muted" style="font-size:13px;margin-top:2px">Tes textes d'invitation et de relance, personnalisés automatiquement pour chaque contact.</p>
        </div>
        <button type="button" wire:click="ajouterModelesDeDepart" class="lb-btn lb-btn-ghost">Ajouter les modèles de départ</button>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Formulaire (ajout ou modification) --}}
    <form wire:submit="enregistrer" class="lb-card mt-5" style="padding:20px">
        <h3 class="lb-h3" style="font-size:18px">{{ $editId ? 'Modifier le modèle' : 'Nouveau modèle' }}</h3>

        <div class="grid gap-4 sm:grid-cols-2 mt-4">
            <div>
                <label for="nom" class="lb-label">Nom du modèle</label>
                <input id="nom" type="text" wire:model="nom" class="lb-field" placeholder="ex. Invitation — professeur de yoga">
                @error('nom') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="canal" class="lb-label">Type</label>
                <select id="canal" wire:model.live="canal" class="lb-field">
                    @foreach (\App\Models\ModeleMessage::CANAUX as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($canal === \App\Models\ModeleMessage::CANAL_EMAIL)
            <div class="mt-4">
                <label for="objet" class="lb-label">Objet de l'e-mail</label>
                <input id="objet" type="text" wire:model="objet" class="lb-field" placeholder="ex. {session}, {date}">
                @error('objet') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="mt-4">
            <label for="corps" class="lb-label">Texte</label>
            <textarea id="corps" rows="10" wire:model="corps" class="lb-field" placeholder="Bonjour {prenom}, …"></textarea>
            @error('corps') <p class="lb-error">{{ $message }}</p> @enderror
        </div>

        <details class="mt-3" style="font-size:13px">
            <summary class="lb-accent" style="cursor:pointer;font-weight:600">Les variables que tu peux utiliser</summary>
            <div class="grid gap-x-6 gap-y-1 sm:grid-cols-2 mt-3">
                @foreach (\App\Models\ModeleMessage::VARIABLES as $variable => $explication)
                    <div><code style="color:var(--accent);font-weight:600">{{ '{'.$variable.'}' }}</code> <span class="lb-muted">{{ $explication }}</span></div>
                @endforeach
            </div>
            <p class="lb-muted" style="margin-top:10px">
                Une variable sans valeur apparaît entre crochets, par exemple <code>[ville]</code>, pour que tu la repères avant d'envoyer.
                Tu peux aussi écrire tes propres repères entre crochets, comme <code>[un détail précis]</code>, à compléter à la main pour chaque personne.
            </p>
        </details>

        <div class="flex items-center justify-end gap-3 mt-5">
            @if ($editId)
                <button type="button" wire:click="annuler" class="lb-btn lb-btn-ghost">Annuler</button>
            @endif
            <button type="submit" class="lb-btn lb-btn-primary">{{ $editId ? 'Enregistrer' : 'Ajouter' }}</button>
        </div>
    </form>

    {{-- Liste --}}
    <div class="lb-people mt-5">
        @forelse ($modeles as $m)
            <div wire:key="m-{{ $m->id }}" class="lb-prow" style="align-items:flex-start">
                <div style="min-width:0;flex:1">
                    <div class="nm">{{ $m->nom }} <span class="lb-tag" style="margin-left:6px;background:var(--surface)">{{ $m->libelleCanal() }}</span></div>
                    @if (filled($m->objet))<div class="mt lb-truncate">Objet : {{ $m->objet }}</div>@endif
                    <div class="mt lb-truncate">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $m->corps), 140) }}</div>
                </div>
                <div class="flex items-center gap-2" style="flex:none">
                    <button type="button" wire:click="editer({{ $m->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Modifier</button>
                    <button type="button" wire:click="supprimer({{ $m->id }})" wire:confirm="Supprimer ce modèle ?" class="lb-btn lb-btn-danger" style="padding:7px 13px;font-size:12.5px">Supprimer</button>
                </div>
            </div>
        @empty
            <div class="lb-placeholder">
                Aucun modèle pour l'instant. Le bouton « Ajouter les modèles de départ » t'en propose plusieurs à adapter.
            </div>
        @endforelse
    </div>
</div>
