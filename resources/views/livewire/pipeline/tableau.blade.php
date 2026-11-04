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
    <div class="flex flex-wrap items-center gap-3 mt-5">
        <button type="button" wire:click="$toggle('voirPerdus')" class="lb-pill" style="cursor:pointer;border:0">
            {{ $voirPerdus ? '✓ ' : '' }}Voir les perdus ({{ $nbPerdus }})
        </button>

        @if ($poles->isNotEmpty())
            <select wire:model.live="pole" class="lb-select" aria-label="Pôle">
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

        <select wire:model.live="role" class="lb-select" aria-label="Rôle">
            <option value="">Tous les rôles</option>
            @foreach ($roles as $cle => $lib)
                <option value="{{ $cle }}">{{ $lib }}</option>
            @endforeach
        </select>
    </div>

    {{-- Tableau de bord --}}
    <div class="flex flex-wrap gap-3 mt-5">
        <div class="lb-card-sm" style="flex:1 1 160px;min-width:150px">
            <div class="lb-eyebrow">Pipeline en cours</div>
            <div class="serif" style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ number_format($tableauBord['pipeline'], 0, ',', ' ') }} €</div>
            <div class="lb-muted" style="font-size:11.5px">{{ $tableauBord['nb'] }} opportunité(s)</div>
        </div>
        <div class="lb-card-sm" style="flex:1 1 160px;min-width:150px">
            <div class="lb-eyebrow">Prévisionnel pondéré</div>
            <div class="serif" style="font-size:22px;font-weight:600;color:var(--accent);font-variant-numeric:tabular-nums">{{ number_format($tableauBord['previsionnel'], 0, ',', ' ') }} €</div>
            <div class="lb-muted" style="font-size:11.5px">pondéré par étape (10/30/60 %)</div>
        </div>
        <div class="lb-card-sm" style="flex:1 1 160px;min-width:150px">
            <div class="lb-eyebrow">Gagné ce mois</div>
            <div class="serif" style="font-size:22px;font-weight:600;color:var(--mint-deep);font-variant-numeric:tabular-nums">{{ number_format($tableauBord['gagne_mois'], 0, ',', ' ') }} €</div>
        </div>
        <div class="lb-card-sm" style="flex:1 1 160px;min-width:150px">
            <div class="lb-eyebrow">Panier moyen</div>
            <div class="serif" style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ number_format($tableauBord['panier'], 0, ',', ' ') }} €</div>
            @if ($tableauBord['conversion'] !== null)
                <div class="lb-muted" style="font-size:11.5px">Conversion : {{ $tableauBord['conversion'] }} %</div>
            @endif
        </div>
    </div>

    @if ($tableauBord['par_categorie']->isNotEmpty())
        <div class="lb-card mt-4" style="padding:16px">
            <h3 class="lb-h3" style="font-size:16px;margin-bottom:10px">Montant par catégorie (en cours)</h3>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="text-align:left;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.06em">
                            <th style="padding:6px 8px">Catégorie</th>
                            <th style="padding:6px 8px;text-align:right">Nb</th>
                            <th style="padding:6px 8px;text-align:right">En cours</th>
                            <th style="padding:6px 8px;text-align:right">Prévisionnel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tableauBord['par_categorie'] as $ligne)
                            <tr style="border-top:1px solid var(--border)">
                                <td style="padding:7px 8px"><span class="lb-dot" style="background:{{ $ligne['couleur'] ?: 'var(--mint)' }}"></span> {{ $ligne['nom'] }}</td>
                                <td style="padding:7px 8px;text-align:right;font-variant-numeric:tabular-nums">{{ $ligne['nb'] }}</td>
                                <td style="padding:7px 8px;text-align:right;font-variant-numeric:tabular-nums">{{ number_format($ligne['montant'], 0, ',', ' ') }} €</td>
                                <td style="padding:7px 8px;text-align:right;font-variant-numeric:tabular-nums;color:var(--accent)">{{ number_format($ligne['previsionnel'], 0, ',', ' ') }} €</td>
                            </tr>
                        @endforeach
                        <tr style="border-top:2px solid var(--border);font-weight:600">
                            <td style="padding:7px 8px">Total</td>
                            <td style="padding:7px 8px;text-align:right;font-variant-numeric:tabular-nums">{{ $tableauBord['nb'] }}</td>
                            <td style="padding:7px 8px;text-align:right;font-variant-numeric:tabular-nums">{{ number_format($tableauBord['pipeline'], 0, ',', ' ') }} €</td>
                            <td style="padding:7px 8px;text-align:right;font-variant-numeric:tabular-nums;color:var(--accent)">{{ number_format($tableauBord['previsionnel'], 0, ',', ' ') }} €</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

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
                                    <div class="flex flex-wrap items-center gap-2" style="margin-top:5px">
                                        @if ($op->montant)
                                            <div class="serif" style="font-size:18px;font-weight:600;color:var(--accent);font-variant-numeric:tabular-nums">{{ number_format($op->montant, 0, ',', ' ') }} €</div>
                                        @endif
                                        <div class="flex flex-wrap items-center justify-end gap-1" style="margin-left:auto">
                                            @if ($op->categorie)
                                                <span class="lb-tag" style="background:var(--surface);font-size:11px"><span class="lb-dot" style="background:{{ $op->categorie->couleur ?: 'var(--mint)' }}"></span>{{ $op->categorie->nom }}</span>
                                            @endif
                                            @if ($op->libelleRole())
                                                <span class="lb-badge lb-av-{{ \App\Models\Opportunite::couleurRole($op->role) }}" style="font-size:11px">{{ $op->libelleRole() }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                                @if ($op->contact)
                                    <a href="{{ route('contacts.fiche', $op->contact) }}" wire:navigate class="lb-accent" style="display:inline-block;font-size:11.5px;font-weight:600;text-decoration:none;margin-top:4px">→ Voir la fiche</a>
                                @endif
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
