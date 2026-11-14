<div x-data="{ confirmerSuppression: false }">
    <a href="{{ route('sessions.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux sessions</a>

    <h1 class="lb-h1 mt-4">{{ $seance ? 'Modifier la session' : 'Nouvelle session' }}</h1>
    @if ($dupliqueeDe)
        <div class="lb-alert mt-4" style="background:var(--gold-tint);color:var(--ink)">
            Copie de « {{ $dupliqueeDe }} ». Choisis la nouvelle date, ajuste le titre si besoin, puis enregistre.
            Les inscrits, les invitations et le lien du groupe WhatsApp ne sont pas repris.
        </div>
    @endif

    <form wire:submit="enregistrer" class="lb-card mt-5" style="padding:24px;max-width:640px">
        <div>
            <label for="titre" class="lb-label">Titre <span class="lb-req">*</span></label>
            <input id="titre" type="text" wire:model="titre" class="lb-field" placeholder="ex. Formation profs — Promo mars 2027">
            @error('titre') <p class="lb-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="categorie_id" class="lb-label">Catégorie</label>
                @if ($poles->isEmpty())
                    <p class="lb-muted" style="font-size:13px;margin-top:6px">
                        Aucun pôle. <a href="{{ route('organisation.index') }}" wire:navigate style="color:var(--accent)">En créer</a>.
                    </p>
                @else
                    <select id="categorie_id" wire:model="categorie_id" class="lb-field">
                        <option value="">— Sans catégorie —</option>
                        @foreach ($poles as $pole)
                            <optgroup label="{{ $pole->nom }}">
                                @foreach ($pole->categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('categorie_id') <p class="lb-error">{{ $message }}</p> @enderror
                @endif
            </div>
            <div>
                <label for="capacite" class="lb-label">Nombre de places</label>
                <input id="capacite" type="number" min="0" wire:model="capacite" class="lb-field" placeholder="ex. 10 (vide = illimité)">
                @error('capacite') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="prix" class="lb-label">Prix par participant (€)</label>
                <input id="prix" type="text" inputmode="decimal" wire:model="prix" class="lb-field" placeholder="vide = prix de la catégorie">
                @error('prix') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="date_debut" class="lb-label">Date{{ $autresDates ? ' (1re du cycle)' : '' }}</label>
                <input id="date_debut" type="datetime-local" step="900" wire:model="date_debut" class="lb-field">
                @error('date_debut') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lien_visio" class="lb-label">Lien de visio de cette date — optionnel</label>
                <input id="lien_visio" type="url" wire:model="lien_visio" class="lb-field" placeholder="https://…">
                @error('lien_visio') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lieu" class="lb-label">Lieu (texte libre)</label>
                <input id="lieu" type="text" wire:model="lieu" class="lb-field" placeholder="ex. Salle A, 1er étage">
                @error('lieu') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Dates suivantes d'un cycle --}}
        <div class="mt-5">
            <div class="lb-label">Autres dates (cycle de plusieurs séances)</div>
            @foreach ($autresDates as $i => $autre)
                <div class="grid gap-3 sm:grid-cols-2 mt-2" wire:key="autre-date-{{ $i }}">
                    <div>
                        <label for="autre-date-{{ $i }}" class="lb-muted" style="font-size:12px">Date n° {{ $i + 2 }}</label>
                        <input id="autre-date-{{ $i }}" type="datetime-local" step="900" wire:model="autresDates.{{ $i }}.date" class="lb-field">
                        @error('autresDates.'.$i.'.date') <p class="lb-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="autre-visio-{{ $i }}" class="lb-muted" style="font-size:12px">Lien de visio de cette date</label>
                        <div class="flex items-start gap-2">
                            <input id="autre-visio-{{ $i }}" type="url" wire:model="autresDates.{{ $i }}.lien_visio" class="lb-field" placeholder="https://…" style="flex:1">
                            <button type="button" wire:click="retirerDate({{ $i }})" class="lb-btn lb-btn-ghost" style="padding:9px 12px" aria-label="Retirer la date n° {{ $i + 2 }}">✕</button>
                        </div>
                        @error('autresDates.'.$i.'.lien_visio') <p class="lb-error">Indique une adresse complète (https://…).</p> @enderror
                    </div>
                </div>
            @endforeach
            <button type="button" wire:click="ajouterDate" class="lb-btn lb-btn-ghost" style="margin-top:8px;padding:7px 13px;font-size:12.5px">+ Ajouter une date</button>
            <p class="lb-muted" style="font-size:12px;margin-top:6px">
                Une inscription couvre toutes les dates. Les présences se notent ensuite date par date, et les inscriptions se ferment quand le cycle commence.
            </p>
        </div>

        <div class="mt-5">
            <label for="lieu_contact_id" class="lb-label">Contact du lieu (salle) — optionnel</label>
            <input type="search" wire:model.live.debounce.300ms="rechercheContact" class="lb-field"
                   placeholder="Rechercher un contact…" style="margin-bottom:6px">
            <select id="lieu_contact_id" wire:model="lieu_contact_id" class="lb-field">
                <option value="">— Aucun —</option>
                @foreach ($contacts as $c)
                    <option value="{{ $c->id }}" @selected((string) $lieu_contact_id === (string) $c->id)>{{ $c->nomComplet() }}@if (filled($c->adresse_ville)) · {{ $c->adresse_ville }}@endif</option>
                @endforeach
            </select>
            <p class="lb-muted" style="font-size:12px;margin-top:4px">Son nom et sa ville s'afficheront sur la fiche de la session.</p>
        </div>

        <div class="mt-5">
            <label for="role_parcours" class="lb-label">Rôle dans le parcours formation — optionnel</label>
            <select id="role_parcours" wire:model="role_parcours" class="lb-field">
                <option value="">— Aucun —</option>
                @foreach (\App\Models\Seance::ROLES_PARCOURS as $cle => $libelle)
                    <option value="{{ $cle }}">{{ $libelle }}</option>
                @endforeach
            </select>
            @error('role_parcours') <p class="lb-error">{{ $message }}</p> @enderror
            <p class="lb-muted" style="font-size:12px;margin-top:4px">Sert au tableau « Parcours formation » et à proposer la prochaine date aux absents.</p>
        </div>

        <div class="mt-5">
            <label for="lien_inscription" class="lb-label">Lien d'inscription — optionnel</label>
            <input id="lien_inscription" type="url" wire:model="lien_inscription" class="lb-field" placeholder="https://…">
            @error('lien_inscription') <p class="lb-error">{{ $message }}</p> @enderror
            <p class="lb-muted" style="font-size:12px;margin-top:4px">La page où l'on réserve sa place. Il sera repris dans tes invitations.</p>
        </div>

        <div class="mt-5">
            <label for="lien_groupe" class="lb-label">Lien du groupe WhatsApp — optionnel</label>
            <input id="lien_groupe" type="url" wire:model="lien_groupe" class="lb-field" placeholder="https://chat.whatsapp.com/…">
            @error('lien_groupe') <p class="lb-error">{{ $message }}</p> @enderror
            <p class="lb-muted" style="font-size:12px;margin-top:4px">Le lien d'invitation à ta communauté d'annonces. Variable <code>{lien_groupe}</code> dans tes messages.</p>
        </div>

        <div class="mt-5">
            <label for="notes" class="lb-label">Notes</label>
            <textarea id="notes" rows="4" wire:model="notes" class="lb-field" placeholder="ex. 3 stages de 3 jours : 10-12 mars, 14-16 avril, 12-14 mai…"></textarea>
        </div>

        <div class="flex items-center justify-between mt-7">
            <div>
                @if ($seance)
                    <button type="button" x-on:click="confirmerSuppression = true" class="lb-btn lb-btn-danger">Supprimer</button>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('sessions.index') }}" class="lb-muted" style="font-size:13.5px;text-decoration:none" wire:navigate>Annuler</a>
                <button type="submit" class="lb-btn lb-btn-primary">Enregistrer</button>
            </div>
        </div>
    </form>

    @if ($seance)
        <div x-cloak x-show="confirmerSuppression" x-transition class="lb-modal-backdrop" x-on:keydown.escape.window="confirmerSuppression = false">
            <div class="lb-modal" x-on:click.outside="confirmerSuppression = false">
                <h3 class="lb-h3" style="font-size:20px">Supprimer cette session ?</h3>
                <p class="lb-muted" style="font-size:13.5px;margin-top:8px">Les inscriptions liées seront aussi supprimées. Cette action est définitive.</p>
                <div class="flex justify-end gap-3 mt-5">
                    <button type="button" x-on:click="confirmerSuppression = false" class="lb-btn lb-btn-ghost">Annuler</button>
                    <button type="button" wire:click="supprimer" class="lb-btn lb-btn-danger">Supprimer définitivement</button>
                </div>
            </div>
        </div>
    @endif
</div>
