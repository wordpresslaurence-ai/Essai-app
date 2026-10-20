<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="lb-eyebrow">Formations &amp; journées</div>
            <h1 class="lb-h1">Sessions</h1>
        </div>
        <a href="{{ route('sessions.creer') }}" class="lb-btn lb-btn-primary" wire:navigate>+ Nouvelle session</a>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Onglets + filtres --}}
    <div class="flex flex-wrap items-center gap-2 mt-6">
        @foreach (['avenir' => 'À venir', 'passees' => 'Passées'] as $cle => $lib)
            <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                    class="lb-pill" style="cursor:pointer;border:0;{{ $onglet === $cle ? 'color:var(--accent)' : '' }}">
                {{ $lib }}
            </button>
        @endforeach

        @if ($poles->isNotEmpty())
            <select wire:model.live="pole" class="lb-select" aria-label="Pôle" style="margin-left:auto">
                <option value="">Tous les pôles</option>
                @foreach ($poles as $p)
                    <option value="{{ $p->id }}">{{ $p->nom }}</option>
                @endforeach
            </select>

            @php($poleActif = $pole !== '' ? $poles->firstWhere('id', (int) $pole) : null)
            @if ($poleActif && $poleActif->categories->isNotEmpty())
                <select wire:model.live="categorie" class="lb-select" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach ($poleActif->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                    @endforeach
                </select>
            @endif
        @endif
    </div>

    {{-- Cartes --}}
    <div class="lb-cards mt-6">
        @forelse ($seances as $seance)
            @php($restantes = $seance->capacite ? max(0, $seance->capacite - $seance->confirmes_count) : null)
            <a href="{{ route('sessions.fiche', $seance) }}" class="lb-ccard" wire:navigate wire:key="s-{{ $seance->id }}" style="flex-direction:column;align-items:stretch;gap:10px">
                <div class="flex items-start justify-between gap-3">
                    <div style="min-width:0">
                        <div class="nm lb-truncate">{{ $seance->titre }}</div>
                        @if ($seance->categorie)
                            <div class="meta lb-truncate"><span class="lb-dot" style="background:{{ $seance->categorie->couleur ?: 'var(--mint)' }}"></span> {{ $seance->categorie->libelleComplet() }}</div>
                        @endif
                    </div>
                    @if ($seance->capacite && $restantes === 0)
                        <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra);flex:none">Complet</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1" style="font-size:12.5px;color:var(--text-muted)">
                    @if ($seance->date_debut)
                        <span>📅 {{ $seance->date_debut->translatedFormat('d/m/Y') }}{{ $seance->date_debut->format('H:i') !== '00:00' ? ' · '.$seance->date_debut->format('H:i') : '' }}</span>
                    @endif
                    @if ($seance->lieu)
                        <span class="lb-truncate">📍 {{ $seance->lieu }}</span>
                    @endif
                </div>

                <div class="flex items-center gap-2" style="font-size:13px">
                    <span style="font-weight:600;color:var(--ink)">
                        {{ $seance->confirmes_count }}@if ($seance->capacite) / {{ $seance->capacite }}@endif
                    </span>
                    <span class="lb-muted">inscrit(s)</span>
                    @if ($seance->attente_count > 0)
                        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent);margin-left:auto">{{ $seance->attente_count }} en attente</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="lb-placeholder">Aucune session {{ $onglet === 'passees' ? 'passée' : 'à venir' }} pour l'instant.</div>
        @endforelse
    </div>
</div>
