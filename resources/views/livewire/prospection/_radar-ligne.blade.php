{{-- Une ligne du radar. Attend $c (contact avec statut_radar) et $offreId. --}}
            @php
                $s = $c->statut_radar;
                $radar = $c->radarSite;
                $aJour = $radar && $radar->analyse_le && $radar->url === $c->site_web;
                $couleur = match ($s) {
                    \App\Models\RadarSite::A_AMELIORER, \App\Models\RadarSite::RESEAU_SEUL => 'background:var(--gold-tint);color:var(--accent)',
                    \App\Models\RadarSite::SANS_SITE, \App\Models\RadarSite::INJOIGNABLE => 'background:var(--terra-tint);color:var(--terra)',
                    \App\Models\RadarSite::CORRECT => 'background:var(--mint-tint);color:var(--mint-deep)',
                    default => 'background:var(--surface)',
                };
                $prospect = in_array($s, [\App\Models\RadarSite::A_AMELIORER, \App\Models\RadarSite::RESEAU_SEUL, \App\Models\RadarSite::SANS_SITE, \App\Models\RadarSite::INJOIGNABLE], true);
            @endphp
            <div class="lb-prow" wire:key="radar-{{ $c->id }}" style="flex-wrap:wrap;row-gap:10px;align-items:flex-start;{{ $radar?->ecarte_le ? 'opacity:.6' : '' }}">
                <span class="lb-tag" style="{{ $couleur }};flex:none">{{ \App\Models\RadarSite::STATUTS[$s] }}</span>

                <div style="min-width:12rem;flex:1">
                    <a href="{{ route('contacts.fiche', $c) }}" wire:navigate class="nm" style="text-decoration:none;color:inherit">{{ $c->nomComplet() }}</a>
                    <div class="mt" style="overflow-wrap:anywhere">
                        @if (filled($c->adresse_ville)){{ $c->adresse_ville }} · @endif
                        @if (filled($c->site_web))
                            <a href="{{ \App\Services\Radar\AnalyseurSite::normaliser($c->site_web) ?? '#' }}" target="_blank" rel="noopener noreferrer" class="lb-accent" style="font-weight:600">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#i', '', $c->site_web), 48) }}</a>
                        @elseif (filled($c->instagram) || filled($c->facebook))
                            présent sur {{ implode(' et ', array_filter([filled($c->instagram) ? 'Instagram' : null, filled($c->facebook) ? 'Facebook' : null])) }}
                        @else
                            aucune adresse web sur la fiche
                        @endif
                        @if ($aJour && $radar->techno) · fait avec {{ $radar->techno }}@endif
                    </div>
                    @if ($aJour && $radar->textesConstats())
                        <ul style="margin:6px 0 0;padding-left:18px;font-size:12.5px;color:var(--ink)">
                            @foreach ($radar->textesConstats() as $constat)
                                <li>{{ $constat }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($aJour)
                        <div class="lb-muted" style="font-size:11.5px;margin-top:4px">Visité le {{ $radar->analyse_le->format('d/m/Y') }}</div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2" style="margin-left:auto">
                    @if (filled($c->site_web))
                        <button type="button" wire:click="analyser({{ $c->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px"
                                wire:loading.attr="disabled" wire:target="analyser({{ $c->id }})">
                            <span wire:loading.remove wire:target="analyser({{ $c->id }})">{{ $aJour ? 'Revisiter' : 'Analyser' }}</span>
                            <span wire:loading wire:target="analyser({{ $c->id }})">Visite…</span>
                        </button>
                    @endif
                    <button type="button" wire:click="basculerEcarte({{ $c->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">
                        {{ $radar?->ecarte_le ? 'Remettre' : 'Écarter' }}
                    </button>
                    @if ($prospect)
                        <a href="{{ route('contacts.fiche', array_filter(['contact' => $c, 'angle' => \App\Livewire\Prospection\Radar::angle($c, $s), 'offre' => $offreId ?: null])) }}#prospection"
                           class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px">Préparer le mail</a>
                    @endif
                </div>
            </div>
