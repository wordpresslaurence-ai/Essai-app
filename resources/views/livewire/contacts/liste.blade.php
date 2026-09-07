<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- En-tête --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Contacts</h1>
            <div class="flex gap-2">
                <a href="{{ route('contacts.import') }}"
                   class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Importer un CSV
                </a>
                <a href="{{ route('contacts.creer') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">
                    + Nouveau contact
                </a>
            </div>
        </div>

        {{-- Message flash --}}
        @if (session('message'))
            <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-4 text-sm text-green-700 dark:text-green-300">
                {{ session('message') }}
            </div>
        @endif

        {{-- Barre de filtres --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="recherche" class="sr-only">Rechercher</label>
                <input id="recherche" type="search" wire:model.live.debounce.300ms="recherche"
                       placeholder="Rechercher (nom, e-mail, téléphone…)"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="type" class="sr-only">Type</label>
                <select id="type" wire:model.live="type"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les types</option>
                    <option value="personne">Personnes</option>
                    <option value="entreprise">Entreprises</option>
                </select>
            </div>

            <div>
                <label for="etiquette" class="sr-only">Étiquette</label>
                <select id="etiquette" wire:model.live="etiquette"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Toutes les étiquettes</option>
                    @foreach ($etiquettes as $et)
                        <option value="{{ $et->id }}">{{ $et->nom }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="statut" class="sr-only">Statut</label>
                <select id="statut" wire:model.live="statut"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="actifs">Actifs</option>
                    <option value="archives">Archivés</option>
                </select>
            </div>
        </div>

        {{-- Tableau --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <button type="button" wire:click="trierPar('nom')"
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200">
                                    Nom @if ($tri === 'nom') <span aria-hidden="true">{{ $sens === 'asc' ? '▲' : '▼' }}</span> @endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-mail</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Téléphone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Entreprise</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button type="button" wire:click="trierPar('updated_at')"
                                        class="hover:text-gray-700 dark:hover:text-gray-200">
                                    Modifié @if ($tri === 'updated_at') <span aria-hidden="true">{{ $sens === 'asc' ? '▲' : '▼' }}</span> @endif
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($contacts as $contact)
                            <tr wire:key="contact-{{ $contact->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('contacts.fiche', $contact) }}"
                                       class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $contact->nomComplet() }}
                                    </a>
                                    @if ($contact->estArchive())
                                        <span class="ml-2 text-xs text-gray-400">(archivé)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $contact->estEntreprise() ? 'Entreprise' : 'Personne' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $contact->email }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $contact->telephone }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $contact->entreprise?->nom }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">
                                    {{ $contact->updated_at?->translatedFormat('d/m/Y') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    Aucun contact trouvé.
                                    <a href="{{ route('contacts.creer') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">En créer un ?</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div>{{ $contacts->links() }}</div>
    </div>
</div>
