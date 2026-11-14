<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    <div class="mt-4">
        <div class="lb-eyebrow">Suivi après la séance</div>
        <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px">
            Note qui était présent, puis envoie à chacun le message qui convient : un merci aux présents, la prochaine date aux absents.
        </p>
    </div>

    @if ($seance->fin() && $seance->fin()->isFuture())
        <div class="lb-alert mt-4" style="background:var(--gold-tint);color:var(--ink)">
            Cette séance n'est pas encore terminée ({{ $seance->fin()->translatedFormat('l j F Y') }}). Le suivi se fait après.
        </div>
    @endif

    <p class="lb-muted" style="font-size:12.5px;margin-top:10px">
        @if ($prochaine)
            Prochaine date proposée aux absents : <b>{{ $prochaine->titre }}</b>, {{ $prochaine->date_debut->translatedFormat('l j F Y') }}.
        @else
            Aucune prochaine session du même type n'est créée : le message des absents affichera <code>[prochaine_date]</code>.
        @endif
        Les textes viennent de tes modèles « Suivi — après la séance » et « Suivi — absent à la séance »
        (<a href="{{ route('reglages.modeles') }}" class="lb-accent" style="font-weight:600" wire:navigate>les modifier</a>).
    </p>

    {{-- Chiffres --}}
    <div class="lb-card mt-4 flex flex-wrap items-center justify-between gap-3" style="padding:16px 20px">
        <div class="flex flex-wrap gap-x-8 gap-y-2">
            @foreach ([
                'presents' => 'présent(s)',
                'absents' => 'absent(s)',
                'non_notes' => 'non noté(s)',
                'envoyes' => 'suivi(s) envoyé(s)',
            ] as $cle => $libelle)
                <div>
                    <span style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $compteurs[$cle] }}</span>
                    <span class="lb-muted"> {{ $libelle }}</span>
                </div>
            @endforeach
        </div>
        @if ($compteurs['non_notes'] > 0)
            <button type="button" wire:click="toutPresent" class="lb-btn lb-btn-ghost">Marquer les non notés « présents »</button>
        @endif
    </div>

    {{-- Participants --}}
    <div class="lb-people mt-4">
        @forelse ($inscriptions as $ins)
            @php $c = $ins->contact; @endphp
            <div wire:key="su-{{ $ins->id }}">
                <div class="lb-prow" style="flex-wrap:wrap;row-gap:10px">
                    @if ($ins->suivi_envoye_le)
                        <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Envoyé le {{ $ins->suivi_envoye_le->format('d/m') }}</span>
                    @else
                        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">À faire</span>
                    @endif

                    <a href="{{ route('contacts.fiche', $c) }}" wire:navigate style="min-width:10rem;flex:1;text-decoration:none;color:inherit">
                        <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                        <div class="mt lb-truncate">{{ $c->email ?: 'pas d’e-mail' }}</div>
                    </a>

                    <div class="flex flex-wrap items-center gap-2" style="margin-left:auto">
                        <button type="button" wire:click="noterPresence({{ $ins->id }}, 'present')" aria-pressed="{{ $ins->presence === 'present' ? 'true' : 'false' }}"
                                class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px;{{ $ins->presence === 'present' ? 'background:var(--mint-tint);color:var(--mint-deep)' : '' }}">Présent</button>
                        <button type="button" wire:click="noterPresence({{ $ins->id }}, 'absent')" aria-pressed="{{ $ins->presence === 'absent' ? 'true' : 'false' }}"
                                class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px;{{ $ins->presence === 'absent' ? 'background:var(--terra-tint);color:var(--terra)' : '' }}">Absent</button>
                        <button type="button" wire:click="preparer({{ $ins->id }})" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px">
                            {{ $ins->suivi_envoye_le ? 'Revoir le message' : 'Préparer le message' }}
                        </button>
                    </div>
                </div>

                @if ($ouverteId === $ins->id)
                    @php $whatsapp = $c->numeroWhatsapp(); @endphp
                    <div class="lb-card" style="padding:18px;margin:4px 0 14px" x-data="{ copie: false }">
                        <div class="lb-muted" style="font-size:12.5px;margin-bottom:10px">
                            Message {{ $ins->estAbsent() ? 'pour une personne absente' : 'pour une personne présente' }}@if ($ins->presence === null) (présence non notée)@endif.
                        </div>
                        <label for="brouillonObjet" class="lb-label">Objet</label>
                        <input id="brouillonObjet" type="text" x-ref="objet" wire:model="brouillonObjet" class="lb-field">

                        <label for="brouillonCorps" class="lb-label" style="margin-top:12px">Message pour {{ $c->nomComplet() }}</label>
                        <textarea id="brouillonCorps" rows="11" x-ref="corps" wire:model="brouillonCorps" class="lb-field"></textarea>
                        @if (str_contains($brouillonCorps.$brouillonObjet, '['))
                            <p style="font-size:12.5px;margin-top:6px;color:var(--terra);font-weight:600">
                                Il reste des repères entre crochets à compléter avant d'envoyer.
                            </p>
                        @endif

                        <div class="flex flex-wrap items-center gap-2 mt-4">
                            <button type="button" class="lb-btn lb-btn-ghost"
                                    x-on:click="navigator.clipboard.writeText($refs.corps.value).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                                <span x-show="!copie">Copier le message</span><span x-show="copie" x-cloak>Copié ✓</span>
                            </button>
                            @if (filled($c->email))
                                <button type="button" class="lb-btn lb-btn-ghost"
                                        x-on:click="window.location.href = 'mailto:' + @js($c->email) + '?subject=' + encodeURIComponent($refs.objet.value) + '&body=' + encodeURIComponent($refs.corps.value)">
                                    Ouvrir ma messagerie
                                </button>
                            @endif
                            @if ($whatsapp)
                                <button type="button" class="lb-btn lb-btn-ghost"
                                        x-on:click="window.open('https://wa.me/' + @js($whatsapp) + '?text=' + encodeURIComponent($refs.corps.value), '_blank')">
                                    Ouvrir WhatsApp
                                </button>
                            @endif
                            <span style="flex:1"></span>
                            <button type="button" wire:click="fermer" class="lb-btn lb-btn-ghost">Fermer</button>
                            <button type="button" wire:click="marquerEnvoye" class="lb-btn lb-btn-primary">C'est envoyé</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">Aucun participant confirmé pour cette session.</div>
        @endforelse
    </div>
</div>
