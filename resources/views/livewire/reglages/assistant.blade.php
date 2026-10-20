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

    <form wire:submit="enregistrer" class="mt-5" style="max-width:720px">

        {{-- Boîte e-mail --}}
        <fieldset class="lb-card" style="padding:18px">
            <legend class="lb-label" style="margin-bottom:10px">Boîte e-mail à suivre</legend>

            <div class="mb-4">
                <label for="gmail_email" class="lb-label">Adresse Gmail</label>
                <input id="gmail_email" type="email" wire:model="gmail_email"
                       placeholder="laurencehomeharmony@gmail.com" class="lb-field">
                @error('gmail_email') <p class="lb-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="gmail_app_password" class="lb-label">
                    Mot de passe d'application
                    @if ($gmailMotDePasseDefini)
                        <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">déjà défini</span>
                    @endif
                </label>
                <input id="gmail_app_password" type="password" wire:model="gmail_app_password"
                       placeholder="{{ $gmailMotDePasseDefini ? '•••••••• (laisser vide pour conserver)' : 'colle ici le mot de passe d’application Gmail' }}"
                       class="lb-field" autocomplete="new-password">
                @error('gmail_app_password') <p class="lb-error">{{ $message }}</p> @enderror
                <p class="lb-muted" style="font-size:12px;margin-top:6px">
                    À générer dans ton compte Google (double authentification requise). On te guidera.
                </p>
            </div>
        </fieldset>

        {{-- Assistant IA --}}
        <fieldset class="lb-card mt-5" style="padding:18px">
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

        {{-- Liens Fluent Booking --}}
        <fieldset class="lb-card mt-5" style="padding:18px">
            <legend class="lb-label" style="margin-bottom:10px">Liens de réservation (Fluent Booking)</legend>

            <div class="mb-4">
                <label for="lien_appel_visio" class="lb-label">Appel visio 1-to-1 (gratuit)</label>
                <input id="lien_appel_visio" type="url" wire:model="lien_appel_visio" class="lb-field">
                @error('lien_appel_visio') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label for="lien_seance_decouverte" class="lb-label">Séance découverte gratuite</label>
                <input id="lien_seance_decouverte" type="url" wire:model="lien_seance_decouverte" class="lb-field">
                @error('lien_seance_decouverte') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lien_journee_aura" class="lb-label">Journée nettoyage aura/chakras</label>
                <input id="lien_journee_aura" type="url" wire:model="lien_journee_aura" class="lb-field">
                @error('lien_journee_aura') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        {{-- Prospection --}}
        <fieldset class="lb-card mt-5" style="padding:18px">
            <legend class="lb-label" style="margin-bottom:10px">Prospection (profs de yoga)</legend>
            <div style="margin-bottom:4px">
                <div class="lb-label">Offre &amp; liens (par pôle)</div>
                <p class="lb-muted" style="font-size:12.5px;margin:2px 0 10px">
                    Pour chaque pôle, décris <strong>brièvement l'offre</strong> (2–3 lignes) puis colle <strong>les liens</strong>
                    (pages de l'offre + Fluent Booking). ⚠️ L'assistant <strong>ne peut pas ouvrir les liens</strong> : il s'appuie
                    sur ta description. Selon le <strong>pôle du contact</strong>, il reprend cette offre et insère ces liens dans le mail.
                </p>
                @forelse ($poles as $pole)
                    <div wire:key="liens-{{ $pole->id }}" style="margin-top:10px">
                        <label for="liens-{{ $pole->id }}" class="lb-label" style="font-weight:500;color:var(--text-muted)">
                            <span class="lb-dot" style="background:{{ $pole->couleur ?: 'var(--mint)' }}"></span> {{ $pole->nom }}
                        </label>
                        <textarea id="liens-{{ $pole->id }}" rows="4" wire:model="liensOffre.{{ $pole->id }}" class="lb-field"
                                  placeholder="Ex. Formation de profs de yoga Prana Vidya sur 3 stages de 3 jours, 10 places, avec une séance découverte offerte.&#10;Programme : https://…&#10;Séance découverte : https://…"></textarea>
                    </div>
                @empty
                    <p class="lb-muted" style="font-size:12.5px">
                        Aucun pôle pour l'instant. Crée-les dans
                        <a href="{{ route('organisation.index') }}" wire:navigate style="color:var(--accent)">Organisation</a>.
                    </p>
                @endforelse
            </div>

            <div style="margin-top:18px">
                <label for="pitch_prospection" class="lb-label">Note générale (facultatif)</label>
                <textarea id="pitch_prospection" rows="4" wire:model="pitch_prospection" class="lb-field"
                          placeholder="Optionnel : un contexte ou un ton général qui s'applique à toute ta prospection (qui tu es, ta façon d'écrire…). L'offre et les liens de chaque pôle restent la base."></textarea>
                @error('pitch_prospection') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center justify-end gap-3 mt-6">
            <button type="button" wire:click="synchroniser" class="lb-btn lb-btn-ghost"
                    wire:loading.attr="disabled" wire:target="synchroniser">
                <span wire:loading.remove wire:target="synchroniser">Synchroniser mes mails</span>
                <span wire:loading wire:target="synchroniser">Synchronisation…</span>
            </button>
            <button type="button" wire:click="testerConnexion" class="lb-btn lb-btn-ghost"
                    wire:loading.attr="disabled" wire:target="testerConnexion">
                <span wire:loading.remove wire:target="testerConnexion">Tester la connexion</span>
                <span wire:loading wire:target="testerConnexion">Test en cours…</span>
            </button>
            <button type="submit" class="lb-btn lb-btn-primary">Enregistrer</button>
        </div>
        <p class="lb-muted" style="font-size:12px;text-align:right;margin-top:6px">
            Enregistre d'abord si tu viens de saisir le mot de passe, puis teste la connexion.
        </p>
    </form>
</div>
