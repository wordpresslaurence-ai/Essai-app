{{-- Agenda annuel des sessions : 12 mois, un point de couleur par session, cliquable. --}}
<div class="flex flex-wrap items-center justify-between gap-3 mt-6">
    <div class="flex items-center gap-2">
        <button type="button" wire:click="anneePrecedente" class="lb-btn lb-btn-ghost" style="padding:7px 13px" aria-label="Année précédente">←</button>
        <span style="font-size:20px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums;min-width:4ch;text-align:center">{{ $annee }}</span>
        <button type="button" wire:click="anneeSuivante" class="lb-btn lb-btn-ghost" style="padding:7px 13px" aria-label="Année suivante">→</button>
        @if ($annee !== (int) now()->year)
            <button type="button" wire:click="anneeCourante" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Cette année</button>
        @endif
    </div>
    <span class="lb-muted" style="font-size:12.5px">
        {{ $agenda['total'] }} session(s) en {{ $annee }}@if ($agenda['sansDate'] > 0) · {{ $agenda['sansDate'] }} sans date (visibles dans « À venir »)@endif
    </span>
</div>

@if ($agenda['legende']->isNotEmpty())
    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3" style="font-size:12.5px;color:var(--text-muted)">
        @foreach ($agenda['legende'] as $entree)
            <span><span class="lb-dot" style="background:{{ $entree['couleur'] }}"></span> {{ $entree['libelle'] }}</span>
        @endforeach
    </div>
@endif

<div class="grid gap-4 mt-4" style="grid-template-columns:repeat(auto-fill,minmax(232px,1fr))">
    @foreach ($agenda['mois'] as $mois)
        <div class="lb-card" style="padding:14px" wire:key="mois-{{ $annee }}-{{ $mois['numero'] }}">
            <div style="font-weight:600;color:var(--ink);text-transform:capitalize;margin-bottom:8px">{{ $mois['nom'] }}</div>
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center">
                @foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $initiale)
                    <div class="lb-muted" style="font-size:10.5px;font-weight:600">{{ $initiale }}</div>
                @endforeach

                @for ($i = 0; $i < $mois['decalage']; $i++)
                    <div></div>
                @endfor

                @foreach ($mois['jours'] as $jour)
                    <div style="min-height:34px;border-radius:8px;padding:2px 0;{{ $jour['aujourdhui'] ? 'box-shadow:inset 0 0 0 1.5px var(--accent);' : '' }}{{ $jour['seances'] ? 'background:var(--surface);' : '' }}">
                        <div style="font-size:11px;font-variant-numeric:tabular-nums;color:{{ $jour['seances'] ? 'var(--ink)' : 'var(--text-muted)' }};{{ $jour['seances'] ? 'font-weight:600' : '' }}">{{ $jour['numero'] }}</div>
                        @if ($jour['seances'])
                            <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:1px">
                                @foreach ($jour['seances'] as $s)
                                    <a href="{{ route('sessions.fiche', $s['id']) }}" wire:navigate
                                       title="{{ $s['titre'] }}" aria-label="{{ $s['libelle'] }}"
                                       style="display:inline-flex;padding:3px;border-radius:999px">
                                        <span style="width:9px;height:9px;border-radius:50%;background:{{ $s['couleur'] }};display:block"></span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<p class="lb-muted" style="font-size:12px;margin-top:12px">Un point par session, à la couleur de sa catégorie. Clique sur un point pour ouvrir la session ; laisse la souris dessus pour voir son titre.</p>
