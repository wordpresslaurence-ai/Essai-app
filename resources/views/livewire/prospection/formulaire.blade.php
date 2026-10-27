<div>
    <a href="{{ route('prospection.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux recherches</a>

    <h1 class="lb-h1 mt-4">{{ $recherche ? 'Recherche de prospection' : 'Nouvelle recherche' }}</h1>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Brief généré --}}
    @if ($brief)
        <div class="lb-card mt-5" style="padding:22px" x-data="{ copie: false }">
            <div class="flex items-center justify-between gap-3">
                <h3 class="lb-h3" style="font-size:18px">Brief à coller dans ChatGPT</h3>
                <button type="button" class="lb-btn lb-btn-primary" style="padding:8px 14px;font-size:13px"
                        x-on:click="navigator.clipboard.writeText($refs.brief.innerText).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                    <span x-show="!copie">Copier le brief</span>
                    <span x-show="copie" x-cloak>✓ Copié</span>
                </button>
            </div>
            <p class="lb-muted" style="font-size:12.5px;margin-top:4px">
                Colle-le dans ChatGPT. Tu récupéreras un tableau + un CSV que tu pourras ensuite réimporter ici.
            </p>
            <pre x-ref="brief" style="white-space:pre-wrap;word-break:break-word;background:var(--bg);border-radius:12px;padding:16px;margin-top:12px;font-size:12px;line-height:1.5;box-shadow:var(--inset);max-height:420px;overflow:auto">{{ $brief }}</pre>
        </div>
    @endif

    <form wire:submit="enregistrer" class="lb-card mt-5" style="padding:24px;max-width:760px">
        <div>
            <label for="nom" class="lb-label">Nom de la recherche <span class="lb-req">*</span></label>
            <input id="nom" type="text" wire:model="nom" class="lb-field" placeholder="ex. Profs de yoga nidra — Bruxelles">
            @error('nom') <p class="lb-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="categorie_id" class="lb-label">Offre concernée (catégorie)</label>
                @if ($poles->isEmpty())
                    <p class="lb-muted" style="font-size:13px;margin-top:6px">Aucune catégorie. <a href="{{ route('organisation.index') }}" wire:navigate style="color:var(--accent)">En créer</a>.</p>
                @else
                    <select id="categorie_id" wire:model="categorie_id" class="lb-field">
                        <option value="">— Aucune —</option>
                        @foreach ($poles as $pole)
                            <optgroup label="{{ $pole->nom }}">
                                @foreach ($pole->categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nom }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                @endif
                <p class="lb-muted" style="font-size:11.5px;margin-top:4px">La description de l'offre vient des Réglages → Assistant.</p>
            </div>
            <div>
                <label for="nombre" class="lb-label">Nombre souhaité</label>
                <input id="nombre" type="number" min="1" max="100" wire:model="nombre" class="lb-field">
                @error('nombre') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5">
            <span class="lb-label">Sources</span>
            <div class="flex flex-wrap gap-4 mt-2">
                @foreach ($sourcesDispo as $cle => $lib)
                    <label class="flex items-center gap-2" style="font-size:13.5px;cursor:pointer">
                        <input type="checkbox" value="{{ $cle }}" wire:model="sources" style="width:16px;height:16px">
                        {{ $lib }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="role" class="lb-label">Rôle recherché</label>
                <select id="role" wire:model="role" class="lb-field">
                    <option value="">Participant / Partenaire (les deux)</option>
                    @foreach ($rolesDispo as $cle => $lib)
                        <option value="{{ $cle }}">{{ $lib }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="langues" class="lb-label">Langues souhaitées</label>
                <input id="langues" type="text" wire:model="langues" class="lb-field" placeholder="ex. FR, NL">
            </div>
        </div>

        <div class="mt-5">
            <label for="personnes_recherchees" class="lb-label">Personnes recherchées</label>
            <textarea id="personnes_recherchees" rows="2" wire:model="personnes_recherchees" class="lb-field" placeholder="ex. professeurs de yoga enseignant le nidra ou le pranayama"></textarea>
        </div>

        <div class="mt-5">
            <label for="zones" class="lb-label">Localisations (lieu d'activité)</label>
            <textarea id="zones" rows="2" wire:model="zones" class="lb-field" placeholder="ex. Bruxelles, Wallonie"></textarea>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="criteres_obligatoires" class="lb-label">Critères obligatoires</label>
                <textarea id="criteres_obligatoires" rows="2" wire:model="criteres_obligatoires" class="lb-field" placeholder="ex. e-mail professionnel publié"></textarea>
            </div>
            <div>
                <label for="criteres_souhaitables" class="lb-label">Critères souhaitables</label>
                <textarea id="criteres_souhaitables" rows="2" wire:model="criteres_souhaitables" class="lb-field" placeholder="ex. organise des ateliers"></textarea>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="profils_privilegier" class="lb-label">Profils à privilégier</label>
                <textarea id="profils_privilegier" rows="2" wire:model="profils_privilegier" class="lb-field" placeholder="ex. enseignants généralistes"></textarea>
            </div>
            <div>
                <label for="profils_eviter" class="lb-label">Profils à éviter</label>
                <textarea id="profils_eviter" rows="2" wire:model="profils_eviter" class="lb-field" placeholder="ex. experts très avancés pour cette offre"></textarea>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 mt-5">
            <div>
                <label for="contact_requis" class="lb-label">Contact professionnel requis</label>
                <input id="contact_requis" type="text" wire:model="contact_requis" class="lb-field" placeholder="E-mail publié ou formulaire">
            </div>
            <div>
                <label for="exclusions" class="lb-label">Exclusions</label>
                <textarea id="exclusions" rows="2" wire:model="exclusions" class="lb-field" placeholder="ex. studios ou personnes à écarter"></textarea>
            </div>
        </div>

        <div class="mt-5">
            <label class="flex items-center gap-2" style="font-size:13.5px;cursor:pointer">
                <input type="checkbox" wire:model="enrichissement" style="width:16px;height:16px">
                Autoriser la consultation du site professionnel depuis un profil social
            </label>
        </div>

        <div class="mt-5">
            <label for="instructions" class="lb-label">Instructions complémentaires</label>
            <textarea id="instructions" rows="2" wire:model="instructions" class="lb-field"></textarea>
        </div>

        <div class="flex items-center justify-end gap-3 mt-7">
            <a href="{{ route('prospection.index') }}" class="lb-muted" style="font-size:13.5px;text-decoration:none" wire:navigate>Annuler</a>
            <button type="submit" class="lb-btn lb-btn-primary">{{ $recherche ? 'Enregistrer et regénérer le brief' : 'Créer et générer le brief' }}</button>
        </div>
    </form>
</div>
