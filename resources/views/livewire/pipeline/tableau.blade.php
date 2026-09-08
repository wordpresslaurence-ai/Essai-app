<div>
    {{-- En-tête --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="lb-eyebrow">Pipeline commercial</div>
            <h1 class="lb-h1">Mes opportunités</h1>
        </div>
        <a href="{{ route('pipeline.creer') }}" class="lb-btn lb-btn-primary" wire:navigate>+ Nouvelle opportunité</a>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Bascule perdus --}}
    <div class="flex items-center gap-3 mt-5">
        <button type="button" wire:click="$toggle('voirPerdus')" class="lb-pill" style="cursor:pointer;border:0">
            {{ $voirPerdus ? '✓ ' : '' }}Voir les perdus ({{ $nbPerdus }})
        </button>
    </div>

    {{-- Kanban --}}
    <div class="mt-5" style="overflow-x:auto">
        <div class="flex gap-4" style="min-width:min-content">
            @foreach ($colonnes as $etape => $col)
                <div class="lb-inset" style="width:280px;flex:none;padding:14px">
                    <div class="flex items-center justify-between" style="margin-bottom:12px">
                        <span style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);font-weight:600">
                            <span class="lb-dot" style="background:var(--{{ $col['couleur'] }});vertical-align:middle;margin-right:6px"></span>{{ $col['libelle'] }}
                        </span>
                        <span class="lb-badge" style="background:var(--surface)">{{ $col['items']->count() }}</span>
                    </div>
                    <div class="lb-muted" style="font-size:12px;margin:-6px 0 12px 16px;font-variant-numeric:tabular-nums">
                        {{ number_format($col['total'], 0, ',', ' ') }} €
                    </div>

                    <div class="flex flex-col gap-2">
                        @forelse ($col['items'] as $op)
                            <div class="lb-card-sm" wire:key="op-{{ $op->id }}">
                                <a href="{{ route('pipeline.modifier', $op) }}" wire:navigate style="text-decoration:none">
                                    <div style="font-weight:600;font-size:13.5px;color:var(--ink)">{{ $op->titre }}</div>
                                    @if ($op->contact)
                                        <div class="lb-muted" style="font-size:12px;margin-top:2px">{{ $op->contact->nomComplet() }}</div>
                                    @endif
                                    @if ($op->montant)
                                        <div class="serif" style="font-size:18px;font-weight:600;color:var(--accent);margin-top:5px;font-variant-numeric:tabular-nums">{{ number_format($op->montant, 0, ',', ' ') }} €</div>
                                    @endif
                                </a>
                                <select class="lb-field mt-2" style="font-size:12px;padding:7px 10px"
                                        wire:change="deplacer({{ $op->id }}, $event.target.value)" aria-label="Changer d'étape">
                                    @foreach ($libelles as $cle => $lib)
                                        <option value="{{ $cle }}" @selected($cle === $op->etape)>{{ $lib }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @empty
                            <div class="lb-muted" style="font-size:12.5px;text-align:center;padding:14px 0">—</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
