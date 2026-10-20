{{-- Vue calendrier des activités (mois ou semaine). Données : $calendrier --}}
@php($hauteurCellule = $modeCalendrier === 'semaine' ? '220px' : '104px')
@php($maxChips = $modeCalendrier === 'semaine' ? 99 : 3)

<div class="lb-card mt-4" style="padding:16px;overflow-x:auto">
    <div style="min-width:680px">
        {{-- En-tête des jours de la semaine --}}
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:8px">
            @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $js)
                <div class="lb-muted" style="font-size:12px;font-weight:600;text-align:center">{{ $js }}</div>
            @endforeach
        </div>

        {{-- Grille des jours --}}
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px">
            @foreach ($calendrier['jours'] as $jour)
                <div style="min-height:{{ $hauteurCellule }};border-radius:14px;padding:8px;
                            background:{{ $jour['dansMois'] ? 'var(--surface)' : 'transparent' }};
                            box-shadow:{{ $jour['dansMois'] ? 'var(--raise-sm)' : 'none' }};
                            {{ $jour['aujourdhui'] ? 'outline:2px solid var(--accent);outline-offset:-2px' : '' }}">
                    <button type="button" wire:click="$set('echeance','{{ $jour['date']->toDateString() }}')"
                            title="Pré-remplir une activité à cette date"
                            style="border:0;background:none;cursor:pointer;font-size:12.5px;font-weight:600;line-height:1;
                                   color:{{ $jour['aujourdhui'] ? 'var(--accent)' : ($jour['dansMois'] ? 'var(--ink)' : 'var(--text-muted)') }}">
                        {{ $jour['date']->format('j') }}
                    </button>

                    <div class="flex flex-col gap-1" style="margin-top:6px">
                        @foreach ($jour['activites']->take($maxChips) as $a)
                            <a href="{{ route('activites.modifier', $a) }}" wire:navigate wire:key="cal-{{ $a->id }}"
                               title="{{ $a->libelleType() }} · {{ $a->titre }}{{ $a->contact ? ' · '.$a->contact->nomComplet() : '' }}"
                               style="display:flex;align-items:center;gap:6px;text-decoration:none;
                                      background:var(--bg);border-radius:8px;padding:4px 7px;box-shadow:var(--inset);
                                      {{ $a->estTerminee() ? 'opacity:.55' : '' }}">
                                <span class="lb-dot" style="flex:none;background:var(--{{ \App\Models\Activite::couleurType($a->type) }})"></span>
                                <span class="lb-truncate" style="font-size:11.5px;color:var(--text);{{ $a->estTerminee() ? 'text-decoration:line-through' : '' }}">{{ $a->titre }}</span>
                            </a>
                            @if ($modeCalendrier === 'semaine' && $a->contact)
                                <span class="lb-muted" style="font-size:10.5px;margin:-2px 0 2px 13px">{{ $a->contact->nomComplet() }}</span>
                            @endif
                        @endforeach

                        @if ($jour['activites']->count() > $maxChips)
                            <span class="lb-muted" style="font-size:10.5px;padding-left:2px">+{{ $jour['activites']->count() - $maxChips }} autre(s)</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Activités sans échéance (non plaçables sur le calendrier) --}}
@if ($calendrier['sansDate']->isNotEmpty())
    <div class="mt-4">
        <div class="lb-muted" style="font-size:12.5px;font-weight:600;margin-bottom:8px">Sans échéance</div>
        <div class="flex flex-wrap gap-2">
            @foreach ($calendrier['sansDate'] as $a)
                <a href="{{ route('activites.modifier', $a) }}" wire:navigate wire:key="nd-{{ $a->id }}" class="lb-tag" style="background:var(--surface);text-decoration:none">
                    <span class="lb-dot" style="background:var(--{{ \App\Models\Activite::couleurType($a->type) }})"></span>{{ $a->titre }}
                </a>
            @endforeach
        </div>
    </div>
@endif
