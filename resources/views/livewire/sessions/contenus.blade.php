<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    <div class="mt-4">
        <div class="lb-eyebrow">Calendrier de contenu</div>
        <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px;max-width:54rem">
            Le plan de tes publications pour ce trimestre. Tu publies et programmes dans Telegram ; ajoute <code>#pratique</code>
            au texte ou à la légende d'une pratique : le bouton « ✓ Fait » apparaît dessous, et la ligne prévue ce jour-là passe ici en « Publié ».
        </p>
    </div>

    {{-- Chiffres --}}
    <div class="lb-card mt-4 flex flex-wrap items-center justify-between gap-3" style="padding:16px 20px">
        <div class="flex flex-wrap gap-x-7 gap-y-2">
            @foreach (\App\Models\Contenu::STATUTS as $cle => $libelle)
                <div>
                    <span style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $compteurs[$cle] }}</span>
                    <span class="lb-muted"> {{ mb_strtolower($libelle) }}</span>
                </div>
            @endforeach
        </div>
        @if ($aPreparer > 0)
            <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">{{ $aPreparer }} à préparer d'ici 7 jours</span>
        @endif
    </div>

    {{-- Ajouter --}}
    <form wire:submit="ajouter" class="lb-card mt-4" style="padding:18px">
        <div class="grid gap-3 sm:grid-cols-4">
            <div>
                <label for="date_prevue" class="lb-label">Date prévue</label>
                <input id="date_prevue" type="date" wire:model="date_prevue" class="lb-field">
                @error('date_prevue') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="format" class="lb-label">Format</label>
                <select id="format" wire:model="format" class="lb-field">
                    @foreach (\App\Models\Contenu::FORMATS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="titre" class="lb-label">Titre</label>
                <input id="titre" type="text" wire:model="titre" class="lb-field" placeholder="ex. Respiration du matin, 10 minutes">
                @error('titre') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex flex-wrap items-end gap-3 mt-3">
            <div style="flex:1;min-width:14rem">
                <label for="notes" class="lb-label">Notes — optionnel</label>
                <input id="notes" type="text" wire:model="notes" class="lb-field" placeholder="ex. reprendre la fiche du stage 1, partie 2">
            </div>
            <button type="submit" class="lb-btn lb-btn-primary">Ajouter au plan</button>
        </div>
    </form>

    @if ($sources->isNotEmpty())
        <div class="flex flex-wrap items-end gap-2 mt-3">
            <div style="min-width:14rem">
                <label for="source" class="lb-muted" style="font-size:12px">Reprendre le plan d'un autre trimestre</label>
                <select id="source" wire:model="source" class="lb-field">
                    <option value="">— Choisir —</option>
                    @foreach ($sources as $s)
                        <option value="{{ $s->id }}">{{ $s->titre }}{{ $s->date_debut ? ' ('.$s->date_debut->translatedFormat('M Y').')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="copierPlan" class="lb-btn lb-btn-ghost" style="padding:9px 13px;font-size:12.5px">Copier ce plan</button>
        </div>
    @endif

    {{-- Le plan, semaine par semaine --}}
    @forelse ($semaines as $lundi => $contenus)
        <h3 class="lb-h3 mt-6" style="font-size:16px">Semaine du {{ \Illuminate\Support\Carbon::parse($lundi)->translatedFormat('j F') }}</h3>
        <div class="lb-people mt-2">
            @foreach ($contenus as $c)
                @php
                    $retard = in_array($c->statut, \App\Models\Contenu::A_PREPARER, true) && $c->date_prevue->lte(now()->addDays(7));
                    $couleur = match ($c->statut) {
                        'publie' => 'background:var(--mint-tint);color:var(--mint-deep)',
                        'pret', 'programme' => 'background:var(--lav-tint);color:var(--lav)',
                        default => $retard ? 'background:var(--terra-tint);color:var(--terra)' : 'background:var(--gold-tint);color:var(--accent)',
                    };
                @endphp
                <div class="lb-prow" wire:key="ct-{{ $c->id }}" style="flex-wrap:wrap;row-gap:8px;align-items:flex-start">
                    <div style="width:6.5rem;flex:none">
                        <input type="date" value="{{ $c->date_prevue->toDateString() }}" aria-label="Date prévue"
                               wire:change="changer({{ $c->id }}, 'date_prevue', $event.target.value)" class="lb-field" style="padding:6px 8px;font-size:12px">
                        <div class="lb-muted" style="font-size:11.5px;margin-top:2px">{{ $c->date_prevue->translatedFormat('l') }}</div>
                    </div>
                    <div style="min-width:12rem;flex:1">
                        <input type="text" value="{{ $c->titre }}" aria-label="Titre"
                               wire:change="changer({{ $c->id }}, 'titre', $event.target.value)" class="lb-field" style="padding:6px 10px;font-size:13.5px;font-weight:600">
                        <input type="text" value="{{ $c->notes }}" aria-label="Notes" placeholder="notes"
                               wire:change="changer({{ $c->id }}, 'notes', $event.target.value)" class="lb-field" style="padding:5px 10px;font-size:12px;margin-top:4px">
                        @if ($c->pratique)
                            <div class="lb-muted" style="font-size:12px;margin-top:4px">
                                Publiée le {{ $c->pratique->publie_le->translatedFormat('j F à H:i') }} · {{ $faites[$c->pratique_telegram_id] ?? 0 }} « fait »
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2" style="flex:none">
                        <select aria-label="Format" wire:change="changer({{ $c->id }}, 'format', $event.target.value)" class="lb-field" style="width:auto;padding:6px 8px;font-size:12.5px">
                            @foreach (\App\Models\Contenu::FORMATS as $cle => $libelle)
                                <option value="{{ $cle }}" @selected($c->format === $cle)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <select aria-label="Statut" wire:change="changer({{ $c->id }}, 'statut', $event.target.value)" class="lb-field" style="width:auto;padding:6px 8px;font-size:12.5px;font-weight:600;{{ $couleur }}">
                            @foreach (\App\Models\Contenu::STATUTS as $cle => $libelle)
                                <option value="{{ $cle }}" @selected($c->statut === $cle)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="supprimer({{ $c->id }})" wire:confirm="Retirer « {{ $c->titre }} » du plan ?"
                                class="lb-btn lb-btn-ghost" style="padding:6px 10px;font-size:12px" aria-label="Retirer du plan">✕</button>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="lb-placeholder mt-5">Le plan est vide. Ajoute ta première publication ci-dessus.</div>
    @endforelse
</div>
