<div class="py-8">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            {{ $contact ? 'Modifier le contact' : 'Nouveau contact' }}
        </h1>

        {{-- Avertissement doublons (non bloquant, EF-13) --}}
        @if ($doublons->isNotEmpty())
            <div class="rounded-md bg-amber-50 dark:bg-amber-900/30 p-4 text-sm text-amber-800 dark:text-amber-200">
                <p class="font-medium">Doublon potentiel détecté :</p>
                <ul class="list-disc list-inside mt-1">
                    @foreach ($doublons as $d)
                        <li>{{ $d->nomComplet() }} @if ($d->email) — {{ $d->email }} @endif</li>
                    @endforeach
                </ul>
                <p class="mt-1 text-xs">Vous pouvez tout de même enregistrer si ce n'est pas la même personne.</p>
            </div>
        @endif

        <form wire:submit="enregistrer"
              class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-5">

            {{-- Type --}}
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Type de contact</label>
                <select id="type" wire:model.live="type"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="personne">Personne</option>
                    <option value="entreprise">Entreprise</option>
                </select>
            </div>

            {{-- Nom / Raison sociale --}}
            <div>
                <label for="nom" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ $type === 'entreprise' ? 'Raison sociale' : 'Nom' }} <span class="text-red-500">*</span>
                </label>
                <input id="nom" type="text" wire:model.blur="nom"
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('nom') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Champs PERSONNE --}}
            @if ($type === 'personne')
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="prenom" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Prénom</label>
                        <input id="prenom" type="text" wire:model="prenom"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('prenom') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fonction" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Fonction</label>
                        <input id="fonction" type="text" wire:model="fonction"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="entreprise_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Entreprise</label>
                    <select id="entreprise_id" wire:model="entreprise_id"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Aucune —</option>
                        @foreach ($entreprises as $e)
                            <option value="{{ $e->id }}">{{ $e->nom }}</option>
                        @endforeach
                    </select>
                    @error('entreprise_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Champs ENTREPRISE --}}
            @if ($type === 'entreprise')
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="numero_entreprise" class="block text-sm font-medium text-gray-700 dark:text-gray-200">N° d'entreprise (BCE)</label>
                        <input id="numero_entreprise" type="text" wire:model.blur="numero_entreprise" placeholder="0403.170.701"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('numero_entreprise') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="numero_tva" class="block text-sm font-medium text-gray-700 dark:text-gray-200">N° de TVA</label>
                        <input id="numero_tva" type="text" wire:model.blur="numero_tva" placeholder="BE0403.170.701"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('numero_tva') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="site_web" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Site web</label>
                        <input id="site_web" type="url" wire:model="site_web" placeholder="https://…"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('site_web') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="secteur" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Secteur d'activité</label>
                        <input id="secteur" type="text" wire:model="secteur"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
            @endif

            {{-- Coordonnées communes --}}
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200">E-mail</label>
                    <input id="email" type="email" wire:model.blur="email"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="telephone" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Téléphone</label>
                    <input id="telephone" type="text" wire:model="telephone"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            {{-- Adresse --}}
            <fieldset class="grid gap-5 sm:grid-cols-2">
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Adresse</legend>
                <div class="sm:col-span-2">
                    <label for="adresse_rue" class="block text-sm text-gray-600 dark:text-gray-300">Rue</label>
                    <input id="adresse_rue" type="text" wire:model="adresse_rue"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="adresse_code_postal" class="block text-sm text-gray-600 dark:text-gray-300">Code postal</label>
                    <input id="adresse_code_postal" type="text" wire:model="adresse_code_postal"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="adresse_ville" class="block text-sm text-gray-600 dark:text-gray-300">Ville</label>
                    <input id="adresse_ville" type="text" wire:model="adresse_ville"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="adresse_pays" class="block text-sm text-gray-600 dark:text-gray-300">Pays</label>
                    <input id="adresse_pays" type="text" wire:model="adresse_pays"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </fieldset>

            {{-- Notes --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Notes</label>
                <textarea id="notes" rows="3" wire:model="notes"
                          class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('contacts.index') }}"
                   class="text-sm text-gray-600 dark:text-gray-300 hover:underline">Annuler</a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
