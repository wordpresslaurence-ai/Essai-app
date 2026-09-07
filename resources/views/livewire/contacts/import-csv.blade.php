<div class="py-8">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Importer des contacts (CSV)</h1>

        {{-- ÉTAPE 1 : upload --}}
        @if ($etape === 'upload')
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Sélectionnez un fichier CSV (par exemple exporté depuis Odoo). La première ligne
                    doit contenir les noms de colonnes. Séparateur « , » ou « ; » accepté.
                </p>
                <div>
                    <label for="fichier" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Fichier CSV</label>
                    <input id="fichier" type="file" wire:model="fichier" accept=".csv,text/csv"
                           class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-200">
                    <div wire:loading wire:target="fichier" class="mt-2 text-sm text-gray-500">Lecture du fichier…</div>
                    @error('fichier') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <a href="{{ route('contacts.index') }}" class="inline-block text-sm text-gray-600 dark:text-gray-300 hover:underline">← Annuler</a>
            </div>
        @endif

        {{-- ÉTAPE 2 : mapping + aperçu --}}
        @if ($etape === 'mapping')
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-4">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">1. Faire correspondre les colonnes</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Indiquez à quel champ correspond chaque colonne de votre fichier (ou « Ignorer »).</p>

                <div class="space-y-2">
                    @foreach ($entetes as $i => $entete)
                        <div class="flex items-center gap-3">
                            <span class="w-1/2 truncate text-sm text-gray-700 dark:text-gray-200" title="{{ $entete }}">{{ $entete ?: '(colonne '.($i + 1).')' }}</span>
                            <span aria-hidden="true" class="text-gray-400">→</span>
                            <select wire:model.live="mapping.{{ $i }}"
                                    class="w-1/2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Ignorer —</option>
                                @foreach ($champs as $cle => $libelle)
                                    <option value="{{ $cle }}">{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($apercu)
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-3">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">2. Récapitulatif avant import</h2>
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-md bg-gray-50 dark:bg-gray-900/40 p-3">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $apercu['total'] }}</p>
                            <p class="text-xs text-gray-500">lignes</p>
                        </div>
                        <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-3">
                            <p class="text-2xl font-semibold text-green-700 dark:text-green-300">{{ $apercu['valides'] }}</p>
                            <p class="text-xs text-green-600 dark:text-green-400">valides</p>
                        </div>
                        <div class="rounded-md bg-red-50 dark:bg-red-900/30 p-3">
                            <p class="text-2xl font-semibold text-red-700 dark:text-red-300">{{ count($apercu['erreurs']) }}</p>
                            <p class="text-xs text-red-600 dark:text-red-400">en erreur</p>
                        </div>
                    </div>
                    @if ($apercu['doublons'] > 0)
                        <p class="text-sm text-amber-700 dark:text-amber-300">⚠️ {{ $apercu['doublons'] }} doublon(s) potentiel(s) détecté(s) (même e-mail) — ils seront tout de même importés.</p>
                    @endif

                    @if (count($apercu['erreurs']) > 0)
                        <div class="max-h-48 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 p-3 text-sm">
                            <p class="font-medium text-gray-700 dark:text-gray-200 mb-1">Lignes en erreur (elles seront ignorées) :</p>
                            <ul class="space-y-1">
                                @foreach ($apercu['erreurs'] as $err)
                                    <li class="text-red-600 dark:text-red-400">Ligne {{ $err['ligne'] }} : {{ implode(' ', $err['messages']) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="flex items-center justify-between pt-2">
                        <button type="button" wire:click="recommencer" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">← Choisir un autre fichier</button>
                        <button type="button" wire:click="importer" @disabled($apercu['valides'] === 0)
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                            Importer {{ $apercu['valides'] }} contact(s)
                        </button>
                    </div>
                </div>
            @endif
        @endif

        {{-- ÉTAPE 3 : terminé --}}
        @if ($etape === 'termine')
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-3">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Import terminé</h2>
                <p class="text-green-700 dark:text-green-300">✅ {{ $rapport['importes'] }} contact(s) importé(s).</p>
                @if (count($rapport['ignorees']) > 0)
                    <p class="text-red-600 dark:text-red-400">{{ count($rapport['ignorees']) }} ligne(s) ignorée(s) (erreurs) :</p>
                    <ul class="max-h-48 overflow-y-auto space-y-1 text-sm">
                        @foreach ($rapport['ignorees'] as $err)
                            <li class="text-red-600 dark:text-red-400">Ligne {{ $err['ligne'] }} : {{ implode(' ', $err['messages']) }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="flex gap-3 pt-2">
                    <a href="{{ route('contacts.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">Voir mes contacts</a>
                    <button type="button" wire:click="recommencer" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">Importer un autre fichier</button>
                </div>
            </div>
        @endif
    </div>
</div>
