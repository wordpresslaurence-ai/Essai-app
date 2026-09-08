<div>
    <a href="{{ route('contacts.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour au carnet</a>

    <h1 class="lb-h1 mt-4">{{ $contact ? 'Modifier le contact' : 'Nouveau contact' }}</h1>

    {{-- Avertissement doublons (non bloquant) --}}
    @if ($doublons->isNotEmpty())
        <div class="lb-alert lb-alert-warn mt-5">
            <p style="font-weight:600">Doublon potentiel détecté :</p>
            <ul style="list-style:disc;margin:6px 0 0 18px">
                @foreach ($doublons as $d)
                    <li>{{ $d->nomComplet() }} @if ($d->email) — {{ $d->email }} @endif</li>
                @endforeach
            </ul>
            <p style="font-size:12px;margin-top:4px">Tu peux tout de même enregistrer si ce n'est pas la même personne.</p>
        </div>
    @endif

    <form wire:submit="enregistrer" class="lb-card mt-5" style="padding:24px">
        {{-- Type --}}
        <div class="max-w-xs">
            <label for="type" class="lb-label">Type de contact</label>
            <select id="type" wire:model.live="type" class="lb-field">
                <option value="personne">Personne</option>
                <option value="entreprise">Entreprise</option>
            </select>
        </div>

        {{-- Nom / Raison sociale --}}
        <div class="mt-5">
            <label for="nom" class="lb-label">{{ $type === 'entreprise' ? 'Raison sociale' : 'Nom' }} <span class="lb-req">*</span></label>
            <input id="nom" type="text" wire:model.blur="nom" class="lb-field">
            @error('nom') <p class="lb-error">{{ $message }}</p> @enderror
        </div>

        {{-- Champs PERSONNE --}}
        @if ($type === 'personne')
            <div class="grid gap-5 sm:grid-cols-2 mt-5">
                <div>
                    <label for="prenom" class="lb-label">Prénom</label>
                    <input id="prenom" type="text" wire:model="prenom" class="lb-field">
                    @error('prenom') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="fonction" class="lb-label">Fonction</label>
                    <input id="fonction" type="text" wire:model="fonction" class="lb-field">
                </div>
            </div>
            <div class="mt-5">
                <label for="entreprise_id" class="lb-label">Entreprise</label>
                <select id="entreprise_id" wire:model="entreprise_id" class="lb-field">
                    <option value="">— Aucune —</option>
                    @foreach ($entreprises as $e)
                        <option value="{{ $e->id }}">{{ $e->nom }}</option>
                    @endforeach
                </select>
                @error('entreprise_id') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- Champs ENTREPRISE --}}
        @if ($type === 'entreprise')
            <div class="grid gap-5 sm:grid-cols-2 mt-5">
                <div>
                    <label for="numero_entreprise" class="lb-label">N° d'entreprise (BCE)</label>
                    <input id="numero_entreprise" type="text" wire:model.blur="numero_entreprise" placeholder="0403.170.701" class="lb-field">
                    @error('numero_entreprise') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="numero_tva" class="lb-label">N° de TVA</label>
                    <input id="numero_tva" type="text" wire:model.blur="numero_tva" placeholder="BE0403.170.701" class="lb-field">
                    @error('numero_tva') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="site_web" class="lb-label">Site web</label>
                    <input id="site_web" type="url" wire:model="site_web" placeholder="https://…" class="lb-field">
                    @error('site_web') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="secteur" class="lb-label">Secteur d'activité</label>
                    <input id="secteur" type="text" wire:model="secteur" class="lb-field">
                </div>
            </div>
        @endif

        {{-- Coordonnées --}}
        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="email" class="lb-label">E-mail</label>
                <input id="email" type="email" wire:model.blur="email" class="lb-field">
                @error('email') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="telephone" class="lb-label">Téléphone</label>
                <input id="telephone" type="text" wire:model="telephone" class="lb-field">
            </div>
        </div>

        {{-- Adresse --}}
        <fieldset class="mt-6">
            <legend class="lb-label" style="margin-bottom:10px">Adresse</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="adresse_rue" class="lb-label" style="font-weight:500;color:var(--text-muted)">Rue</label>
                    <input id="adresse_rue" type="text" wire:model="adresse_rue" class="lb-field">
                </div>
                <div>
                    <label for="adresse_code_postal" class="lb-label" style="font-weight:500;color:var(--text-muted)">Code postal</label>
                    <input id="adresse_code_postal" type="text" wire:model="adresse_code_postal" class="lb-field">
                </div>
                <div>
                    <label for="adresse_ville" class="lb-label" style="font-weight:500;color:var(--text-muted)">Ville</label>
                    <input id="adresse_ville" type="text" wire:model="adresse_ville" class="lb-field">
                </div>
                <div>
                    <label for="adresse_pays" class="lb-label" style="font-weight:500;color:var(--text-muted)">Pays</label>
                    <input id="adresse_pays" type="text" wire:model="adresse_pays" class="lb-field">
                </div>
            </div>
        </fieldset>

        {{-- Lead : source & température --}}
        <fieldset class="mt-6">
            <legend class="lb-label" style="margin-bottom:10px">Lead (facultatif)</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="source" class="lb-label" style="font-weight:500;color:var(--text-muted)">Source</label>
                    <select id="source" wire:model="source" class="lb-field">
                        <option value="">—</option>
                        @foreach (\App\Models\Contact::SOURCES as $cle => $info)
                            <option value="{{ $cle }}">{{ $info[0] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="temperature" class="lb-label" style="font-weight:500;color:var(--text-muted)">Température</label>
                    <select id="temperature" wire:model="temperature" class="lb-field">
                        <option value="">—</option>
                        @foreach (\App\Models\Contact::TEMPERATURES as $cle => $info)
                            <option value="{{ $cle }}">{{ $info[0] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </fieldset>

        {{-- Notes --}}
        <div class="mt-5">
            <label for="notes" class="lb-label">Notes</label>
            <textarea id="notes" rows="3" wire:model="notes" class="lb-field"></textarea>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 mt-7">
            <a href="{{ route('contacts.index') }}" class="lb-muted" style="font-size:13.5px;text-decoration:none" wire:navigate>Annuler</a>
            <button type="submit" class="lb-btn lb-btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
