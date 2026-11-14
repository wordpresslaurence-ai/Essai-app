<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    @if (session('erreur'))
        <div class="lb-alert lb-alert-danger mt-4">{{ session('erreur') }}</div>
    @endif

    <div class="mt-4">
        <div class="lb-eyebrow">Dates et présences</div>
        <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px">
            Pour chaque date : le lien de visio, le rappel de la veille, les présences, puis l'enregistrement pour les absents.
            Les textes viennent de tes modèles « Rappel — veille de séance » et « Enregistrement — pour les absents »
            (<a href="{{ route('reglages.modeles') }}" class="lb-accent" style="font-weight:600" wire:navigate>les modifier</a>).
        </p>
    </div>

    @forelse ($dates as $numero => $date)
        @php
            $pDate = $presences[$date->id] ?? [];
            $nbPresents = collect($pDate)->where('presence', 'present')->count();
            $nbAbsents = collect($pDate)->where('presence', 'absent')->count();
            $nbNonNotes = $inscrits->count() - $nbPresents - $nbAbsents;
        @endphp
        <div class="lb-card mt-5" style="padding:20px" wire:key="date-{{ $date->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="lb-muted" style="font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase">Date {{ $numero + 1 }} sur {{ $dates->count() }}</div>
                    <h3 class="lb-h3" style="font-size:19px">{{ ucfirst($date->libelle()) }}</h3>
                    <div class="lb-muted" style="font-size:12.5px;margin-top:2px">
                        {{ $nbPresents }} présent(s) · {{ $nbAbsents }} absent(s)@if ($nbNonNotes > 0) · {{ $nbNonNotes }} non noté(s)@endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="ouvrir({{ $date->id }}, 'rappel')" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Rappel aux inscrits</button>
                    @if ($nbAbsents > 0)
                        <button type="button" wire:click="ouvrir({{ $date->id }}, 'enregistrement')" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px">Enregistrement aux absents</button>
                    @endif
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 mt-4">
                <div>
                    <label for="visio-{{ $date->id }}" class="lb-label">Lien de visio</label>
                    <input id="visio-{{ $date->id }}" type="url" value="{{ $date->lien_visio }}" placeholder="https://…" class="lb-field"
                           wire:change="majLien({{ $date->id }}, 'lien_visio', $event.target.value)">
                </div>
                <div>
                    <label for="enreg-{{ $date->id }}" class="lb-label">Lien de l'enregistrement</label>
                    <input id="enreg-{{ $date->id }}" type="url" value="{{ $date->lien_enregistrement }}" placeholder="à coller après la séance" class="lb-field"
                           wire:change="majLien({{ $date->id }}, 'lien_enregistrement', $event.target.value)">
                </div>
            </div>

            {{-- Panneau de message --}}
            @if ($dateOuverte && $dateOuverte->id === $date->id)
                <div style="margin-top:16px;padding:16px;border-radius:14px;background:var(--surface)">
                    <label for="message" class="lb-label">
                        {{ $mode === 'rappel' ? 'Rappel pour tous les inscrits' : 'Message pour les absents de cette date' }}
                    </label>
                    <textarea id="message" rows="6" wire:model.live.debounce.500ms="message" class="lb-field"></textarea>
                    @if ($mode === 'rappel' && blank($date->lien_visio))
                        <p style="font-size:12.5px;margin-top:6px;color:var(--terra);font-weight:600">Le lien de visio de cette date n'est pas encore saisi : le message affichera [lien_visio].</p>
                    @endif
                    @if ($mode === 'enregistrement' && blank($date->lien_enregistrement))
                        <p style="font-size:12.5px;margin-top:6px;color:var(--terra);font-weight:600">Colle d'abord le lien de l'enregistrement ci-dessus : le message affichera sinon [lien_enregistrement].</p>
                    @endif

                    <div class="lb-people mt-3">
                        @forelse ($lignes as $l)
                            @php $c = $l['inscription']->contact; @endphp
                            <div class="lb-prow" wire:key="msg-{{ $date->id }}-{{ $l['inscription']->id }}" style="flex-wrap:wrap;row-gap:8px">
                                @if ($l['fait'])
                                    <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">{{ $mode === 'rappel' ? 'Ouvert ✓' : 'Envoyé ✓' }}</span>
                                @else
                                    <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">À envoyer</span>
                                @endif
                                <div style="min-width:10rem;flex:1">
                                    <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                                    <div class="mt lb-truncate">{{ $c->telephone ?: ($c->email ?: 'ni téléphone ni e-mail') }}</div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2" x-data="{ copie: false }">
                                    <button type="button" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px"
                                            x-on:click="navigator.clipboard.writeText(@js($l['texte'])).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                                        <span x-show="!copie">Copier</span><span x-show="copie" x-cloak>Copié ✓</span>
                                    </button>
                                    @if ($l['mail'])
                                        <a href="{{ $l['mail'] }}" wire:click="marquer({{ $l['inscription']->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px;text-decoration:none">Ouvrir ma messagerie</a>
                                    @endif
                                    @if ($l['whatsapp'])
                                        <a href="{{ $l['whatsapp'] }}" target="_blank" rel="noopener" wire:click="marquer({{ $l['inscription']->id }})" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px;text-decoration:none">Ouvrir WhatsApp</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="lb-placeholder">{{ $mode === 'rappel' ? 'Aucun inscrit confirmé.' : 'Aucun absent noté pour cette date.' }}</div>
                        @endforelse
                    </div>

                    <div class="flex items-center justify-between gap-3 mt-3">
                        <p class="lb-muted" style="font-size:12px">Le CRM n'envoie rien : chaque message s'ouvre chez toi et part quand tu appuies sur Envoyer.</p>
                        <button type="button" wire:click="fermer" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Fermer</button>
                    </div>
                </div>
            @endif

            {{-- Présences --}}
            <div class="flex items-center justify-between gap-3 mt-5">
                <div class="lb-label" style="margin:0">Présences</div>
                @if ($nbNonNotes > 0 && $inscrits->isNotEmpty())
                    <button type="button" wire:click="toutPresent({{ $date->id }})" class="lb-accent" style="font-size:12.5px;font-weight:600;background:none;border:0;cursor:pointer">Marquer les non notés « présents »</button>
                @endif
            </div>
            <div class="lb-people mt-2">
                @forelse ($inscrits as $ins)
                    @php $p = $pDate[$ins->id] ?? null; @endphp
                    <div class="lb-prow" wire:key="pres-{{ $date->id }}-{{ $ins->id }}" style="flex-wrap:wrap;row-gap:8px">
                        <div style="min-width:10rem;flex:1">
                            <div class="nm lb-truncate">{{ $ins->contact->nomComplet() }}</div>
                            @if ($p?->enregistrement_envoye_le)
                                <div class="mt">Enregistrement envoyé le {{ $p->enregistrement_envoye_le->format('d/m') }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="noterPresence({{ $date->id }}, {{ $ins->id }}, 'present')" aria-pressed="{{ $p?->presence === 'present' ? 'true' : 'false' }}"
                                    class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px;{{ $p?->presence === 'present' ? 'background:var(--mint-tint);color:var(--mint-deep)' : '' }}">Présent</button>
                            <button type="button" wire:click="noterPresence({{ $date->id }}, {{ $ins->id }}, 'absent')" aria-pressed="{{ $p?->presence === 'absent' ? 'true' : 'false' }}"
                                    class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px;{{ $p?->presence === 'absent' ? 'background:var(--terra-tint);color:var(--terra)' : '' }}">Absent</button>
                        </div>
                    </div>
                @empty
                    <div class="lb-placeholder">Aucun participant confirmé pour cette session.</div>
                @endforelse
            </div>
        </div>
    @empty
        <div class="lb-placeholder mt-5">
            Cette session n'a pas encore de date.
            <a href="{{ route('sessions.modifier', $seance) }}" class="lb-accent" style="font-weight:600" wire:navigate>Ajoute-les dans la session</a>.
        </div>
    @endforelse
</div>
