<div>
    <h1 class="lb-h1">Réglages de l'assistant</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">
        Connexion e-mail, clé de l'assistant IA et liens de réservation. Les mots de passe et clés
        sont chiffrés et ne sont jamais réaffichés.
    </p>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif
    @if ($testMessage)
        <div class="lb-alert lb-alert-success mt-5">{{ $testMessage }}</div>
    @endif
    @if ($testErreur)
        <div class="lb-alert lb-alert-danger mt-5">{{ $testErreur }}</div>
    @endif

    {{-- Boîtes e-mail (brique 009) : gérées sur un écran dédié --}}
    <div class="lb-card mt-5" style="padding:18px;max-width:720px">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="lb-label" style="margin-bottom:2px">Boîtes e-mail à suivre</div>
                <p class="lb-muted" style="font-size:12.5px;margin:0">
                    Branche une ou plusieurs boîtes (Gmail, Hostinger…), chacune rattachée à un pôle.
                </p>
            </div>
            <a href="{{ route('reglages.boites') }}" class="lb-btn lb-btn-ghost" wire:navigate>Gérer les boîtes e-mail →</a>
        </div>
    </div>

    <form wire:submit="enregistrer" class="mt-5" style="max-width:720px">

        {{-- Assistant IA --}}
        <fieldset class="lb-card" style="padding:18px">
            <legend class="lb-label" style="margin-bottom:10px">Assistant IA (Claude)</legend>

            <div class="mb-4">
                <label for="anthropic_api_key" class="lb-label">
                    Clé API Claude
                    @if ($cleApiDefinie)
                        <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">déjà définie</span>
                    @endif
                </label>
                <input id="anthropic_api_key" type="password" wire:model="anthropic_api_key"
                       placeholder="{{ $cleApiDefinie ? '•••••••• (laisser vide pour conserver)' : 'colle ici ta clé API Anthropic' }}"
                       class="lb-field" autocomplete="new-password">
                @error('anthropic_api_key') <p class="lb-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="anthropic_modele" class="lb-label">Modèle</label>
                    <input id="anthropic_modele" type="text" wire:model="anthropic_modele"
                           placeholder="modèle économique (par défaut)" class="lb-field">
                    @error('anthropic_modele') <p class="lb-error">{{ $message }}</p> @enderror
                    <p class="lb-muted" style="font-size:12px;margin-top:6px">Laisse vide pour le choix économique par défaut.</p>
                </div>
                <div>
                    <label for="relance_delai_jours" class="lb-label">Relancer après (jours)</label>
                    <input id="relance_delai_jours" type="number" min="1" max="365"
                           wire:model="relance_delai_jours" class="lb-field">
                    @error('relance_delai_jours') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        {{-- Prospection --}}
        <fieldset class="lb-card mt-5" style="padding:18px">
            <legend class="lb-label" style="margin-bottom:10px">Prospection (profs de yoga)</legend>
            <div style="margin-bottom:4px">
                <div class="lb-label">Offre &amp; liens (par catégorie)</div>
                <p class="lb-muted" style="font-size:12.5px;margin:2px 0 10px">
                    Pour chaque <strong>catégorie</strong>, décris l'offre (tu peux coller toute ta page de vente, sans limite) et
                    ajoute autant de <strong>liens</strong> que tu veux (pages de l'offre + réservation Fluent Booking).
                    ⚠️ L'assistant <strong>ne peut pas ouvrir les liens</strong> : il s'appuie sur ta description. Selon la
                    <strong>catégorie du contact</strong>, il reprend cette offre et insère ces liens dans le mail.
                </p>

                @forelse ($poles as $pole)
                    <div wire:key="pole-{{ $pole->id }}" style="margin-top:14px">
                        <div class="lb-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em">
                            <span class="lb-dot" style="background:{{ $pole->couleur ?: 'var(--mint)' }}"></span> {{ $pole->nom }}
                        </div>

                        @forelse ($pole->categories as $cat)
                            <div wire:key="cat-{{ $cat->id }}" x-data="{ ouvert: false }" class="lb-inset" style="margin-top:8px;padding:0;overflow:hidden">
                                <button type="button" x-on:click="ouvert = !ouvert"
                                        style="width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;background:none;border:0;cursor:pointer;padding:12px 14px;color:var(--text);font:inherit;font-weight:600">
                                    <span>{{ $cat->nom }}</span>
                                    <span class="lb-muted" style="font-size:12px" x-text="ouvert ? '▲' : '▼'"></span>
                                </button>

                                <div x-show="ouvert" x-transition style="padding:0 14px 14px">
                                    <label for="offre-{{ $cat->id }}" class="lb-label" style="font-weight:500;color:var(--text-muted)">Description de l'offre</label>
                                    <textarea id="offre-{{ $cat->id }}" rows="5" wire:model="offres.{{ $cat->id }}" class="lb-field"
                                              placeholder="Colle ici ta page de vente / la description de cette offre. Aucune limite de longueur."></textarea>

                                    <div class="lb-label" style="font-weight:500;color:var(--text-muted);margin-top:12px">Liens</div>
                                    @forelse ($liens[$cat->id] ?? [] as $i => $lien)
                                        <div wire:key="lien-{{ $cat->id }}-{{ $i }}" class="flex flex-wrap items-center gap-2" style="margin-top:6px">
                                            <input type="text" wire:model="liens.{{ $cat->id }}.{{ $i }}.libelle" class="lb-field" style="flex:1;min-width:9rem" placeholder="Libellé (ex. Programme)">
                                            <input type="url" wire:model="liens.{{ $cat->id }}.{{ $i }}.url" class="lb-field" style="flex:2;min-width:12rem" placeholder="https://…">
                                            <button type="button" wire:click="supprimerLien({{ $cat->id }}, {{ $i }})"
                                                    class="lb-iconbtn" style="width:34px;height:34px;color:var(--terra);flex:none" title="Retirer ce lien" aria-label="Retirer ce lien">×</button>
                                        </div>
                                    @empty
                                        <p class="lb-muted" style="font-size:12px;margin-top:4px">Aucun lien pour l'instant.</p>
                                    @endforelse

                                    <button type="button" wire:click="ajouterLien({{ $cat->id }})" class="lb-btn lb-btn-ghost" style="margin-top:8px;padding:7px 13px;font-size:12.5px">+ Ajouter un lien</button>
                                </div>
                            </div>
                        @empty
                            <p class="lb-muted" style="font-size:12px;margin-top:6px">Aucune catégorie dans ce pôle.</p>
                        @endforelse
                    </div>
                @empty
                    <p class="lb-muted" style="font-size:12.5px">
                        Aucun pôle/catégorie pour l'instant. Crée-les dans
                        <a href="{{ route('organisation.index') }}" wire:navigate style="color:var(--accent)">Organisation</a>.
                    </p>
                @endforelse
            </div>

            <div style="margin-top:18px">
                <label for="pitch_prospection" class="lb-label">Note générale (facultatif)</label>
                <textarea id="pitch_prospection" rows="4" wire:model="pitch_prospection" class="lb-field"
                          placeholder="Optionnel : un contexte ou un ton général qui s'applique à toute ta prospection (qui tu es, ta façon d'écrire…). L'offre et les liens de chaque catégorie restent la base."></textarea>
                @error('pitch_prospection') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center justify-end gap-3 mt-6">
            <button type="button" wire:click="synchroniser" class="lb-btn lb-btn-ghost"
                    wire:loading.attr="disabled" wire:target="synchroniser">
                <span wire:loading.remove wire:target="synchroniser">Synchroniser mes mails</span>
                <span wire:loading wire:target="synchroniser">Synchronisation…</span>
            </button>
            <button type="submit" class="lb-btn lb-btn-primary">Enregistrer</button>
        </div>
        <p class="lb-muted" style="font-size:12px;text-align:right;margin-top:6px">
            « Synchroniser » lit toutes les boîtes actives (voir « Gérer les boîtes e-mail »).
        </p>
    </form>
</div>
