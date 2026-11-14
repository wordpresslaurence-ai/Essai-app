<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    <div class="mt-4">
        <div class="lb-eyebrow">Demande d'avis</div>
        <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px">
            Demande à chaque participant quelques lignes sur son expérience, puis garde ici les avis reçus et l'accord de publication.
        </p>
    </div>

    @if ($seance->fin() && $seance->fin()->isFuture())
        <div class="lb-alert mt-4" style="background:var(--gold-tint);color:var(--ink)">
            Cette session n'est pas encore terminée ({{ $seance->fin()->translatedFormat('l j F Y') }}). L'avis se demande après.
        </div>
    @endif

    <p class="lb-muted" style="font-size:12.5px;margin-top:10px">
        Le texte vient de ton modèle « Avis — après un stage »
        (<a href="{{ route('reglages.modeles') }}" class="lb-accent" style="font-weight:600" wire:navigate>le modifier</a>).
        @if (filled($lienAvis))
            Lien d'avis utilisé : <b>{{ $lienAvis }}</b>.
        @else
            Aucune page d'avis n'est renseignée : le message affichera <code>[lien_avis]</code>.
            <a href="{{ route('reglages.assistant') }}" class="lb-accent" style="font-weight:600" wire:navigate>L'ajouter dans les réglages</a>, ou retire la phrase du message.
        @endif
        Les personnes notées absentes ne sont pas listées.
    </p>

    {{-- Chiffres --}}
    <div class="lb-card mt-4 flex flex-wrap gap-x-8 gap-y-2" style="padding:16px 20px">
        @foreach ([
            'a_demander' => 'à demander',
            'demandes' => 'en attente de réponse',
            'recus' => 'avis reçu(s)',
            'publiables' => 'publiable(s)',
        ] as $cle => $libelle)
            <div>
                <span style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $compteurs[$cle] }}</span>
                <span class="lb-muted"> {{ $libelle }}</span>
            </div>
        @endforeach
    </div>

    {{-- Participants --}}
    <div class="lb-people mt-4">
        @forelse ($inscriptions as $ins)
            @php $c = $ins->contact; @endphp
            <div wire:key="av-{{ $ins->id }}">
                <div class="lb-prow" style="flex-wrap:wrap;row-gap:10px">
                    @if ($ins->avis_recu_le)
                        <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Avis reçu</span>
                    @elseif ($ins->avis_demande_le)
                        <span class="lb-tag" style="background:var(--surface)">Demandé le {{ $ins->avis_demande_le->format('d/m') }}</span>
                    @else
                        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">À demander</span>
                    @endif

                    <a href="{{ route('contacts.fiche', $c) }}" wire:navigate style="min-width:10rem;flex:1;text-decoration:none;color:inherit">
                        <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                        <div class="mt lb-truncate">{{ $c->email ?: 'pas d’e-mail' }}</div>
                    </a>

                    <div class="flex flex-wrap items-center gap-2" style="margin-left:auto">
                        <button type="button" wire:click="noter({{ $ins->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">
                            {{ $ins->avis_recu_le ? 'Modifier l’avis' : 'Noter l’avis reçu' }}
                        </button>
                        @if (! $ins->avis_recu_le)
                            <button type="button" wire:click="preparer({{ $ins->id }})" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px">
                                {{ $ins->avis_demande_le ? 'Redemander' : 'Préparer la demande' }}
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Avis reçu --}}
                @if ($ins->avis_recu_le && ! ($ouverteId === $ins->id && $panneau === 'avis'))
                    <div class="lb-card" style="padding:14px 16px;margin:2px 0 12px" x-data="{ copie: false }">
                        <p style="white-space:pre-line;font-size:13.5px;color:var(--ink)" x-ref="avis">{{ $ins->avis_texte }}</p>
                        <div class="flex flex-wrap items-center gap-2 mt-3" style="font-size:12.5px">
                            @if ($ins->avis_publiable)
                                <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Accord de publication</span>
                            @else
                                <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">Pas encore d'accord pour publier</span>
                            @endif
                            <span class="lb-muted">reçu le {{ $ins->avis_recu_le->format('d/m/Y') }}</span>
                            <span style="flex:1"></span>
                            <button type="button" class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px"
                                    x-on:click="navigator.clipboard.writeText($refs.avis.innerText).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                                <span x-show="!copie">Copier l'avis</span><span x-show="copie" x-cloak>Copié ✓</span>
                            </button>
                            <button type="button" wire:click="supprimerAvis({{ $ins->id }})" wire:confirm="Supprimer cet avis ?"
                                    class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px">Supprimer</button>
                        </div>
                    </div>
                @endif

                {{-- Demande à envoyer --}}
                @if ($ouverteId === $ins->id && $panneau === 'demande')
                    <div class="lb-card" style="padding:18px;margin:4px 0 14px" x-data="{ copie: false }">
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
                            @include('livewire.sessions._envoi')
                            <span style="flex:1"></span>
                            <button type="button" wire:click="fermer" class="lb-btn lb-btn-ghost">Fermer</button>
                            <button type="button" wire:click="marquerEnvoye" class="lb-btn lb-btn-primary">C'est envoyé</button>
                        </div>
                    </div>
                @endif

                {{-- Noter l'avis reçu --}}
                @if ($ouverteId === $ins->id && $panneau === 'avis')
                    <form wire:submit="enregistrerAvis" class="lb-card" style="padding:18px;margin:4px 0 14px">
                        <label for="avisTexte" class="lb-label">Avis de {{ $c->nomComplet() }}</label>
                        <textarea id="avisTexte" rows="6" wire:model="avisTexte" class="lb-field" placeholder="Colle ici sa réponse, telle qu'elle l'a écrite."></textarea>
                        @error('avisTexte') <p class="lb-error">{{ $message }}</p> @enderror

                        <label class="flex items-center gap-2 mt-3" style="font-size:13.5px;cursor:pointer">
                            <input type="checkbox" wire:model="avisPubliable">
                            <span>Cette personne m'a donné son accord pour publier son avis</span>
                        </label>

                        <div class="flex items-center justify-end gap-2 mt-4">
                            <button type="button" wire:click="fermer" class="lb-btn lb-btn-ghost">Annuler</button>
                            <button type="submit" class="lb-btn lb-btn-primary">Enregistrer l'avis</button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">Aucun participant à qui demander un avis pour cette session.</div>
        @endforelse
    </div>
</div>
