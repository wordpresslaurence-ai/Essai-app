<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    <div class="mt-4">
        <div class="lb-eyebrow">Paiements</div>
        <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px">
            Note ce que chaque participant a versé (acompte, solde) et vois d'un coup d'œil ce qu'il reste à recevoir.
        </p>
    </div>

    @if ($prix === null)
        <div class="lb-alert mt-4" style="background:var(--gold-tint);color:var(--ink)">
            Aucun prix n'est défini pour cette session.
            <a href="{{ route('sessions.modifier', $seance) }}" class="lb-accent" style="font-weight:600" wire:navigate>Indique le prix par participant</a>
            pour calculer ce qu'il reste à payer, ou saisis un montant pour chaque personne ci-dessous.
        </div>
    @else
        <p class="lb-muted" style="font-size:12.5px;margin-top:10px">
            Prix par participant : <b>{{ \App\Models\Paiement::euros($prix) }}</b>@if ($seance->prix === null) (prix de la catégorie)@endif.
            Tu peux indiquer un autre montant pour une personne (tarif réduit, paiement en plusieurs stages…).
        </p>
    @endif

    {{-- Totaux --}}
    <div class="lb-card mt-4 flex flex-wrap gap-x-8 gap-y-2" style="padding:16px 20px">
        @foreach ([
            'attendu' => 'attendu',
            'recu' => 'reçu',
            'reste' => 'reste à recevoir',
        ] as $cle => $libelle)
            <div>
                <span style="font-size:22px;font-weight:600;font-variant-numeric:tabular-nums;color:{{ $cle === 'reste' && $totaux['reste'] > 0 ? 'var(--terra)' : 'var(--ink)' }}">{{ \App\Models\Paiement::euros($totaux[$cle]) }}</span>
                <span class="lb-muted"> {{ $libelle }}</span>
            </div>
        @endforeach
        <div>
            <span style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $totaux['soldes'] }} / {{ $inscriptions->count() }}</span>
            <span class="lb-muted"> ont tout payé</span>
        </div>
    </div>

    {{-- Participants --}}
    <div class="lb-people mt-4">
        @forelse ($inscriptions as $ins)
            @php
                $c = $ins->contact;
                $etat = $ins->etatPaiement();
                $reste = $ins->resteDu();
            @endphp
            <div wire:key="pa-{{ $ins->id }}">
                <div class="lb-prow" style="flex-wrap:wrap;row-gap:10px">
                    @if ($etat === 'solde')
                        <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Payé</span>
                    @elseif ($etat === 'partiel')
                        <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">Acompte</span>
                    @elseif ($etat === 'impaye')
                        <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">À payer</span>
                    @else
                        <span class="lb-tag" style="background:var(--surface)">Sans montant</span>
                    @endif

                    <a href="{{ route('contacts.fiche', $c) }}" wire:navigate style="min-width:10rem;flex:1;text-decoration:none;color:inherit">
                        <div class="nm lb-truncate">{{ $c->nomComplet() }}</div>
                        <div class="mt lb-truncate">
                            Reçu {{ \App\Models\Paiement::euros($ins->totalPaye()) }}@if ($ins->montantDu() !== null) sur {{ \App\Models\Paiement::euros($ins->montantDu()) }}@endif
                            @if ($reste) · <b style="color:var(--terra)">reste {{ \App\Models\Paiement::euros($reste) }}</b>@endif
                        </div>
                    </a>

                    <div class="flex flex-wrap items-center gap-2" style="margin-left:auto">
                        <input type="text" inputmode="decimal" aria-label="Montant attendu pour {{ $c->nomComplet() }}"
                               value="{{ $ins->montant_du !== null ? rtrim(rtrim(number_format($ins->montant_du, 2, ',', ''), '0'), ',') : '' }}"
                               wire:change="definirMontant({{ $ins->id }}, $event.target.value)"
                               placeholder="{{ $prix !== null ? 'montant ('.\App\Models\Paiement::euros($prix).')' : 'montant attendu' }}"
                               class="lb-field" style="width:9.5rem;padding:7px 10px;font-size:12.5px">
                        @if ($reste)
                            <button type="button" wire:click="solder({{ $ins->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px"
                                    title="Note le solde comme reçu aujourd'hui, par virement">Tout est payé</button>
                            <button type="button" wire:click="preparerRappel({{ $ins->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">Rappel</button>
                        @endif
                        <button type="button" wire:click="ouvrir({{ $ins->id }})" class="lb-btn lb-btn-primary" style="padding:7px 13px;font-size:12.5px">Noter un paiement</button>
                    </div>
                </div>

                {{-- Versements déjà notés --}}
                @if ($ins->paiements->isNotEmpty())
                    <div style="margin:2px 0 10px 12px;font-size:12.5px">
                        @foreach ($ins->paiements->sortBy('paye_le') as $p)
                            <div class="flex flex-wrap items-center gap-2 lb-muted" wire:key="pp-{{ $p->id }}" style="padding:2px 0">
                                <span style="font-variant-numeric:tabular-nums;color:var(--ink);font-weight:600">{{ \App\Models\Paiement::euros($p->montant) }}</span>
                                <span>le {{ $p->paye_le->format('d/m/Y') }}</span>
                                @if ($p->libelleMode() !== '')<span>· {{ $p->libelleMode() }}</span>@endif
                                @if (filled($p->note))<span>· {{ $p->note }}</span>@endif
                                <button type="button" wire:click="supprimerPaiement({{ $p->id }})" wire:confirm="Supprimer ce paiement ?"
                                        style="background:none;border:0;cursor:pointer;color:var(--terra);font-size:12px;text-decoration:underline">supprimer</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Noter un paiement --}}
                @if ($ouverteId === $ins->id)
                    <form wire:submit="enregistrerPaiement" class="lb-card" style="padding:18px;margin:4px 0 14px">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="montant" class="lb-label">Montant reçu (€)</label>
                                <input id="montant" type="text" inputmode="decimal" wire:model="montant" class="lb-field" placeholder="ex. 285">
                                @error('montant') <p class="lb-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="paye_le" class="lb-label">Reçu le</label>
                                <input id="paye_le" type="date" wire:model="paye_le" class="lb-field">
                                @error('paye_le') <p class="lb-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="mode" class="lb-label">Moyen</label>
                                <select id="mode" wire:model="mode" class="lb-field">
                                    @foreach (\App\Models\Paiement::MODES as $cle => $libelle)
                                        <option value="{{ $cle }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="note" class="lb-label">Note — optionnel</label>
                            <input id="note" type="text" wire:model="note" class="lb-field" placeholder="ex. acompte, stage 1 sur 3">
                            @error('note') <p class="lb-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center justify-end gap-2 mt-4">
                            <button type="button" wire:click="fermer" class="lb-btn lb-btn-ghost">Annuler</button>
                            <button type="submit" class="lb-btn lb-btn-primary">Enregistrer le paiement</button>
                        </div>
                    </form>
                @endif

                {{-- Rappel de paiement --}}
                @if ($rappelId === $ins->id)
                    <div class="lb-card" style="padding:18px;margin:4px 0 14px" x-data="{ copie: false }">
                        <label for="rappelObjet" class="lb-label">Objet</label>
                        <input id="rappelObjet" type="text" x-ref="objet" wire:model="rappelObjet" class="lb-field">

                        <label for="rappelCorps" class="lb-label" style="margin-top:12px">Rappel pour {{ $c->nomComplet() }}</label>
                        <textarea id="rappelCorps" rows="10" x-ref="corps" wire:model="rappelCorps" class="lb-field"></textarea>
                        @if (str_contains($rappelCorps.$rappelObjet, '['))
                            <p style="font-size:12.5px;margin-top:6px;color:var(--terra);font-weight:600">
                                Il reste des repères entre crochets à compléter avant d'envoyer (par exemple comment payer).
                            </p>
                        @endif
                        <p class="lb-muted" style="font-size:12px;margin-top:6px">
                            Le texte vient de ton modèle « Paiement — rappel »
                            (<a href="{{ route('reglages.modeles') }}" class="lb-accent" style="font-weight:600" wire:navigate>le modifier</a>).
                        </p>

                        <div class="flex flex-wrap items-center gap-2 mt-4">
                            @include('livewire.sessions._envoi')
                            <span style="flex:1"></span>
                            <button type="button" wire:click="fermerRappel" class="lb-btn lb-btn-ghost">Fermer</button>
                            <button type="button" wire:click="rappelEnvoye" class="lb-btn lb-btn-primary">C'est envoyé</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">Aucun participant confirmé pour cette session.</div>
        @endforelse
    </div>
</div>
