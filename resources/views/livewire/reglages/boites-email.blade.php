<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="lb-eyebrow">Réglages</div>
            <h1 class="lb-h1">Boîtes e-mail</h1>
            <p class="lb-muted" style="font-size:13px;margin-top:2px">
                Branche une ou plusieurs boîtes à suivre (Gmail, Hostinger…), chacune rattachée à un pôle.
                Les mots de passe sont chiffrés et ne sont jamais réaffichés.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reglages.assistant') }}" class="lb-btn lb-btn-ghost" wire:navigate>← Réglages</a>
            <button type="button" wire:click="nouvelle" class="lb-btn lb-btn-primary">+ Ajouter une boîte</button>
        </div>
    </div>

    @if ($message)
        <div class="lb-alert lb-alert-success mt-5">{{ $message }}</div>
    @endif
    @if ($erreur)
        <div class="lb-alert lb-alert-danger mt-5">{{ $erreur }}</div>
    @endif

    {{-- Formulaire d'ajout / modification --}}
    @if ($formOuvert)
        <form wire:submit="enregistrer" class="lb-card mt-5" style="padding:18px;max-width:720px">
            <div class="lb-label" style="margin-bottom:12px">{{ $boiteId ? 'Modifier la boîte' : 'Nouvelle boîte' }}</div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="fournisseur" class="lb-label">Type de boîte</label>
                    <select id="fournisseur" wire:model.live="fournisseur" class="lb-field lb-select">
                        @foreach ($fournisseurs as $cle => $nom)
                            <option value="{{ $cle }}">{{ $nom }}</option>
                        @endforeach
                    </select>
                    @error('fournisseur') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="libelle" class="lb-label">Nom (facultatif)</label>
                    <input id="libelle" type="text" wire:model="libelle" class="lb-field" placeholder="ex. Gmail Prana">
                    @error('libelle') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 mt-4">
                <div>
                    <label for="email" class="lb-label">Adresse e-mail</label>
                    <input id="email" type="email" wire:model="email" class="lb-field"
                           placeholder="laurence@homeharmony.be">
                    @error('email') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mot_de_passe" class="lb-label">
                        Mot de passe
                        @if ($motDePasseDefini)
                            <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">déjà défini</span>
                        @endif
                    </label>
                    <input id="mot_de_passe" type="password" wire:model="mot_de_passe"
                           placeholder="{{ $motDePasseDefini ? '•••••••• (laisser vide pour conserver)' : 'mot de passe de la boîte' }}"
                           class="lb-field" autocomplete="new-password">
                    @error('mot_de_passe') <p class="lb-error">{{ $message }}</p> @enderror
                    <p class="lb-muted" style="font-size:12px;margin-top:6px">
                        @if ($fournisseur === \App\Models\BoiteEmail::FOURNISSEUR_GMAIL)
                            Gmail : utilise un <strong>mot de passe d'application</strong> (double authentification requise).
                        @elseif ($fournisseur === \App\Models\BoiteEmail::FOURNISSEUR_HOSTINGER)
                            Hostinger : le mot de passe de la boîte (défini dans hPanel → Emails).
                        @else
                            Le mot de passe de connexion IMAP de cette boîte.
                        @endif
                    </p>
                </div>
            </div>

            {{-- Réglages techniques : uniquement pour « Autre » --}}
            @if ($fournisseur === \App\Models\BoiteEmail::FOURNISSEUR_AUTRE)
                <div class="grid gap-5 sm:grid-cols-3 mt-4">
                    <div>
                        <label for="imap_host" class="lb-label">Serveur IMAP</label>
                        <input id="imap_host" type="text" wire:model="imap_host" class="lb-field" placeholder="imap.exemple.com">
                        @error('imap_host') <p class="lb-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="imap_port" class="lb-label">Port</label>
                        <input id="imap_port" type="number" wire:model="imap_port" class="lb-field" placeholder="993">
                        @error('imap_port') <p class="lb-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="dossier_envoyes" class="lb-label">Dossier « Envoyés »</label>
                        <input id="dossier_envoyes" type="text" wire:model="dossier_envoyes" class="lb-field" placeholder="INBOX.Sent">
                        @error('dossier_envoyes') <p class="lb-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @else
                <p class="lb-muted" style="font-size:12px;margin-top:10px">
                    Les réglages techniques (serveur, port, dossier « Envoyés ») sont automatiques pour ce type de boîte.
                </p>
            @endif

            <div class="grid gap-5 sm:grid-cols-2 mt-4">
                <div>
                    <label for="pole_id" class="lb-label">Pôle</label>
                    <select id="pole_id" wire:model="pole_id" class="lb-field lb-select">
                        <option value="">— Sans pôle —</option>
                        @foreach ($poles as $pole)
                            <option value="{{ $pole->id }}">{{ $pole->nom }}</option>
                        @endforeach
                    </select>
                    @error('pole_id') <p class="lb-error">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2" style="cursor:pointer">
                        <input type="checkbox" wire:model="active" style="width:16px;height:16px;accent-color:var(--accent)">
                        <span class="lb-label" style="margin:0">Boîte active (lue à la synchronisation)</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" wire:click="annuler" class="lb-btn lb-btn-ghost">Annuler</button>
                <button type="submit" class="lb-btn lb-btn-primary">Enregistrer</button>
            </div>
        </form>
    @endif

    {{-- Liste des boîtes --}}
    <div class="lb-people mt-6" style="max-width:720px">
        @forelse ($boites as $boite)
            <div class="lb-prow" wire:key="boite-{{ $boite->id }}">
                <span class="lb-av lb-av-44 lb-av-{{ $boite->active ? 'mint' : 'muted' }} sq" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                </span>
                <div style="min-width:0;flex:1">
                    <div class="nm lb-truncate" style="font-weight:600">
                        {{ $boite->nomAffichage() }}
                        <span class="lb-tag" style="background:var(--surface)">{{ $fournisseurs[$boite->fournisseur] ?? $boite->fournisseur }}</span>
                        @unless ($boite->active)
                            <span class="lb-tag" style="background:var(--surface);color:var(--text-muted)">désactivée</span>
                        @endunless
                    </div>
                    <div class="mt lb-truncate" style="font-size:12px;color:var(--text-muted)">
                        {{ $boite->email }}
                        · {{ $boite->pole ? $boite->pole->nom : 'Sans pôle' }}
                        @if ($boite->derniere_synchro_at)
                            · synchro {{ $boite->derniere_synchro_at->diffForHumans(short: true) }}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2" style="flex:none">
                    <button type="button" wire:click="tester({{ $boite->id }})" class="lb-btn lb-btn-ghost" style="padding:6px 12px;font-size:12.5px"
                            wire:loading.attr="disabled" wire:target="tester({{ $boite->id }})">Tester</button>
                    <button type="button" wire:click="basculerActive({{ $boite->id }})" class="lb-btn lb-btn-ghost" style="padding:6px 12px;font-size:12.5px">
                        {{ $boite->active ? 'Désactiver' : 'Activer' }}
                    </button>
                    <button type="button" wire:click="modifier({{ $boite->id }})" class="lb-btn lb-btn-ghost" style="padding:6px 12px;font-size:12.5px">Modifier</button>
                    <button type="button" wire:click="supprimer({{ $boite->id }})" class="lb-iconbtn" style="width:34px;height:34px;color:var(--terra)"
                            x-on:click="if(!confirm('Supprimer cette boîte ? Les e-mails déjà importés sont conservés.')) $event.stopImmediatePropagation()"
                            title="Supprimer" aria-label="Supprimer">×</button>
                </div>
            </div>
        @empty
            <div class="lb-placeholder">
                Aucune boîte e-mail pour l'instant.
                <button type="button" wire:click="nouvelle" class="lb-accent" style="font-weight:600;background:none;border:0;cursor:pointer">Ajouter la première ?</button>
            </div>
        @endforelse
    </div>
</div>
