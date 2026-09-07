<div class="py-8">
    <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Étiquettes</h1>

        @if (session('message'))
            <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-4 text-sm text-green-700 dark:text-green-300">
                {{ session('message') }}
            </div>
        @endif

        {{-- Ajout --}}
        <form wire:submit="ajouter" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[12rem]">
                <label for="nom" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Nouvelle étiquette</label>
                <input id="nom" type="text" wire:model="nom" placeholder="ex. Client, Prospect, Fournisseur…"
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('nom') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="couleur" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Couleur</label>
                <input id="couleur" type="color" wire:model="couleur"
                       class="mt-1 h-10 w-16 rounded-md border-gray-300 dark:border-gray-600">
            </div>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-500">
                Ajouter
            </button>
        </form>

        {{-- Liste --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($etiquettes as $et)
                <div wire:key="etiquette-{{ $et->id }}" class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $et->couleur ?? '#6b7280' }}"></span>
                        <span class="text-gray-900 dark:text-gray-100">{{ $et->nom }}</span>
                        <span class="text-xs text-gray-400">({{ $et->contacts_count }} contact(s))</span>
                    </div>
                    <button type="button" wire:click="supprimer({{ $et->id }})"
                            wire:confirm="Supprimer cette étiquette ?"
                            class="text-sm text-red-600 dark:text-red-400 hover:underline">Supprimer</button>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500 dark:text-gray-400">Aucune étiquette pour l'instant.</p>
            @endforelse
        </div>

        <div>
            <a href="{{ route('contacts.index') }}" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">← Retour aux contacts</a>
        </div>
    </div>
</div>
