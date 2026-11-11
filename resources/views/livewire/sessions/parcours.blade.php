<div>
    <a href="{{ route('sessions.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux sessions</a>

    <div class="mt-4">
        <div class="lb-eyebrow">Sessions</div>
        <h1 class="lb-h1">Parcours formation</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:2px">De l'invitation à la séance découverte jusqu'à l'inscription à la formation.</p>
    </div>

    @if ($vide)
        <div class="lb-placeholder mt-5">
            Aucune session n'a encore de rôle dans le parcours.
            Ouvre une session, clique « Modifier », puis choisis « Séance découverte » ou « Formation (stage) » dans « Rôle dans le parcours ».
        </div>
    @else
        {{-- Entonnoir --}}
        <div class="lb-card mt-5" style="padding:20px">
            <h3 class="lb-h3" style="font-size:18px">Où en sont les personnes</h3>
            <div style="display:flex;flex-direction:column;gap:14px;margin-top:16px">
                @foreach ($etapes as $i => $etape)
                    @php $precedent = $i > 0 ? $etapes[$i - 1]['n'] : null; @endphp
                    <div>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span style="font-weight:600;color:var(--ink)">{{ $etape['libelle'] }}</span>
                            <span>
                                <span style="font-size:20px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $etape['n'] }}</span>
                                @if ($precedent)
                                    <span class="lb-muted" style="font-size:12.5px"> · {{ (int) round($etape['n'] / $precedent * 100) }} % de l'étape précédente</span>
                                @endif
                            </span>
                        </div>
                        <div style="height:10px;border-radius:999px;background:var(--surface);margin-top:6px;overflow:hidden">
                            <div style="height:100%;width:{{ $etape['largeur'] }}%;background:var(--accent);border-radius:999px"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="lb-muted" style="font-size:12.5px;margin-top:16px">
                Chaque personne est comptée une seule fois par étape. « Ont réservé un appel » compte les rendez-vous notés pour une personne invitée ou inscrite à une séance découverte.
                Parmi les inscrits à la formation, {{ $formesViaDecouverte }} sont passés par une séance découverte.
            </p>
        </div>

        {{-- Stages --}}
        <h3 class="lb-h3 mt-7" style="font-size:18px">Places par stage</h3>
        <div class="lb-people mt-3">
            @forelse ($formations as $f)
                @php $s = $f['seance']; $restantes = $s->capacite ? max(0, $s->capacite - $f['confirmes']) : null; @endphp
                <a href="{{ route('sessions.fiche', $s) }}" class="lb-prow" wire:navigate wire:key="pf-{{ $s->id }}" style="text-decoration:none;color:inherit;flex-wrap:wrap;row-gap:8px">
                    <div style="min-width:12rem;flex:1">
                        <div class="nm lb-truncate">{{ $s->titre }}</div>
                        <div class="mt lb-truncate">
                            {{ $s->date_debut ? $s->date_debut->translatedFormat('d/m/Y') : 'date à fixer' }}@if ($s->lieuContact) · {{ $s->lieuContact->nomComplet() }}@if (filled($s->lieuContact->adresse_ville)), {{ $s->lieuContact->adresse_ville }}@endif @elseif (filled($s->lieu)) · {{ $s->lieu }}@endif
                        </div>
                    </div>
                    <span style="font-variant-numeric:tabular-nums"><b>{{ $f['confirmes'] }}</b>@if ($s->capacite) / {{ $s->capacite }}@endif inscrit(s)</span>
                    @if ($restantes !== null)
                        <span class="lb-tag" style="{{ $restantes === 0 ? 'background:var(--terra-tint);color:var(--terra)' : 'background:var(--mint-tint);color:var(--mint-deep)' }}">
                            {{ $restantes === 0 ? 'Complet' : $restantes.' place(s) restante(s)' }}
                        </span>
                    @endif
                    @if ($f['attente'] > 0)
                        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">{{ $f['attente'] }} en attente</span>
                    @endif
                </a>
            @empty
                <div class="lb-placeholder">Aucune session n'a le rôle « Formation (stage) ».</div>
            @endforelse
        </div>

        {{-- Séances découverte --}}
        <h3 class="lb-h3 mt-7" style="font-size:18px">Séances découverte</h3>
        <div class="lb-people mt-3">
            @forelse ($decouvertes as $d)
                @php $s = $d['seance']; @endphp
                <a href="{{ route('sessions.fiche', $s) }}" class="lb-prow" wire:navigate wire:key="pd-{{ $s->id }}" style="text-decoration:none;color:inherit;flex-wrap:wrap;row-gap:8px">
                    <div style="min-width:12rem;flex:1">
                        <div class="nm lb-truncate">{{ $s->titre }}</div>
                        <div class="mt">{{ $s->date_debut ? $s->date_debut->translatedFormat('d/m/Y') : 'date à fixer' }}</div>
                    </div>
                    <span class="lb-muted" style="font-size:13px;font-variant-numeric:tabular-nums">
                        {{ $d['invites'] }} invité(s) · {{ $d['inscrits'] }} inscrit(s) · {{ $d['presents'] }} présent(s) · {{ $d['absents'] }} absent(s)
                    </span>
                </a>
            @empty
                <div class="lb-placeholder">Aucune session n'a le rôle « Séance découverte ».</div>
            @endforelse
        </div>
    @endif
</div>
