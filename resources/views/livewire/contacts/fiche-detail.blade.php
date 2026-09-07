<div class="py-8" x-data="{ confirmerSuppression: false }">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- En-tête + actions --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $contact->nomComplet() }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $contact->estEntreprise() ? 'Entreprise' : 'Personne' }}
                    @if ($contact->estArchive())
                        · <span class="text-amber-600 dark:text-amber-400">Archivé</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('contacts.modifier', $contact) }}"
                   class="inline-flex items-center px-3 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">
                    Modifier
                </a>
                @if ($contact->estArchive())
                    <button type="button" wire:click="desarchiver"
                            class="inline-flex items-center px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Désarchiver
                    </button>
                @else
                    <button type="button" wire:click="archiver"
                            class="inline-flex items-center px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Archiver
                    </button>
                @endif
                <button type="button" x-on:click="confirmerSuppression = true"
                        class="inline-flex items-center px-3 py-2 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 rounded-md text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">
                    Supprimer
                </button>
            </div>
        </div>

        @if (session('message'))
            <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-4 text-sm text-green-700 dark:text-green-300">
                {{ session('message') }}
            </div>
        @endif

        {{-- Coordonnées --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">E-mail</p>
                <p class="text-gray-900 dark:text-gray-100">{{ $contact->email ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">Téléphone</p>
                <p class="text-gray-900 dark:text-gray-100">{{ $contact->telephone ?: '—' }}</p>
            </div>

            @if ($contact->estPersonne())
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Fonction</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $contact->fonction ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Entreprise</p>
                    <p class="text-gray-900 dark:text-gray-100">
                        @if ($contact->entreprise)
                            <a href="{{ route('contacts.fiche', $contact->entreprise) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $contact->entreprise->nom }}</a>
                        @else — @endif
                    </p>
                </div>
            @else
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">N° d'entreprise (BCE)</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $contact->numero_entreprise ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">N° de TVA</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $contact->numero_tva ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Site web</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $contact->site_web ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Secteur</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $contact->secteur ?: '—' }}</p>
                </div>
            @endif

            <div class="sm:col-span-2">
                <p class="text-xs uppercase tracking-wide text-gray-400">Adresse</p>
                <p class="text-gray-900 dark:text-gray-100">
                    {{ collect([$contact->adresse_rue, trim(($contact->adresse_code_postal ?? '').' '.($contact->adresse_ville ?? '')), $contact->adresse_pays])->filter()->join(', ') ?: '—' }}
                </p>
            </div>

            @if ($contact->etiquettes->isNotEmpty())
                <div class="sm:col-span-2">
                    <p class="text-xs uppercase tracking-wide text-gray-400 mb-1">Étiquettes</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($contact->etiquettes as $et)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $et->nom }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($contact->notes)
                <div class="sm:col-span-2">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Notes</p>
                    <p class="text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $contact->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Personnes rattachées (si entreprise) --}}
        @if ($contact->estEntreprise())
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Personnes de cette entreprise</h2>
                @if ($contact->personnes->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Aucune personne rattachée.</p>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($contact->personnes as $p)
                            <li class="py-2 flex justify-between">
                                <a href="{{ route('contacts.fiche', $p) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $p->nomComplet() }}</a>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $p->fonction }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- Emplacement réservé : historique activités/opportunités (EF-22) --}}
        <div class="bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 text-center text-sm text-gray-500 dark:text-gray-400">
            Historique des activités et opportunités — à venir (modules Pipeline et Activités).
        </div>

        <div>
            <a href="{{ route('contacts.index') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">← Retour à la liste</a>
        </div>

        {{-- Modale de confirmation de suppression --}}
        <div x-cloak x-show="confirmerSuppression" x-transition
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             x-on:keydown.escape.window="confirmerSuppression = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 space-y-4" x-on:click.outside="confirmerSuppression = false">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Supprimer ce contact ?</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Cette action est définitive.
                    @if ($contact->estEntreprise() && $contact->personnes->isNotEmpty())
                        Les {{ $contact->personnes->count() }} personne(s) rattachée(s) ne seront pas supprimées : elles seront simplement détachées de cette entreprise.
                    @endif
                </p>
                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="confirmerSuppression = false"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:underline">Annuler</button>
                    <button type="button" wire:click="supprimer"
                            class="px-4 py-2 bg-red-600 rounded-md text-sm font-medium text-white hover:bg-red-500">Supprimer définitivement</button>
                </div>
            </div>
        </div>
    </div>
</div>
