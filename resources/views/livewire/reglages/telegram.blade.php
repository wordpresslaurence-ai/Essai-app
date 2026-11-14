<div>
    <a href="{{ route('reglages.assistant') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux réglages</a>

    <div class="mt-4">
        <h1 class="lb-h1">Telegram</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:2px;max-width:52rem">
            Un robot Telegram reçoit les questions de tes contacts et les range sur leur fiche. Tu réponds depuis le CRM.
            Le robot ne lit pas ton compte Telegram personnel : il ne voit que ce qu'on lui écrit à lui.
        </p>
    </div>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif
    @if ($erreur)
        <div class="lb-alert lb-alert-danger mt-5">{{ $erreur }}</div>
    @endif

    @if ($connecte)
        <div class="lb-card mt-5" style="padding:20px;max-width:720px">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Connecté</span>
                    <span style="font-weight:600;color:var(--ink);margin-left:8px">{{ '@'.$robot }}</span>
                    <div class="lb-muted" style="font-size:12.5px;margin-top:6px">{{ $nbRelies }} contact(s) relié(s) à leur compte Telegram.</div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="verifier" class="lb-btn lb-btn-ghost">Vérifier le branchement</button>
                    <button type="button" wire:click="deconnecter" wire:confirm="Déconnecter le robot ? Les messages ne seront plus reçus dans le CRM." class="lb-btn lb-btn-danger">Déconnecter</button>
                </div>
            </div>

            @if ($etat !== null)
                <div style="margin-top:14px;font-size:13.5px">
                    @if (filled($etat['last_error_message'] ?? null))
                        <p style="color:var(--terra);font-weight:600">Telegram n'arrive pas à joindre le CRM : {{ $etat['last_error_message'] }}</p>
                        <p class="lb-muted" style="margin-top:4px">Vérifie que le CRM est bien en ligne en https, puis reconnecte le robot.</p>
                    @elseif (blank($etat['url'] ?? null))
                        <p style="color:var(--terra);font-weight:600">Le robot n'est relié à aucune adresse. Reconnecte-le en recollant son jeton.</p>
                    @else
                        <p style="color:var(--mint-deep);font-weight:600">Tout est en ordre : Telegram envoie bien les messages au CRM.</p>
                    @endif
                    <p class="lb-muted" style="margin-top:4px">{{ (int) ($etat['pending_update_count'] ?? 0) }} message(s) en attente de livraison.</p>
                </div>
            @endif
        </div>

        <div class="lb-card mt-5" style="padding:20px;max-width:720px">
            <div class="lb-label">Comment relier un contact</div>
            <ol style="margin:8px 0 0;padding-left:20px;font-size:13.5px;color:var(--ink);line-height:1.7">
                <li>Ouvre la fiche du contact : son <b>lien Telegram personnel</b> s'y trouve, avec un bouton pour le copier.</li>
                <li>Envoie-lui ce lien (mail, WhatsApp). Pour un groupe d'inscrits, utilise la variable <code>{lien_telegram}</code> dans « Message aux inscrits ».</li>
                <li>Quand il l'ouvre et appuie sur « Démarrer », son compte est relié : ses messages arrivent sur sa fiche et dans l'Assistant.</li>
            </ol>
            <p class="lb-muted" style="font-size:12.5px;margin-top:10px">Le robot ne peut pas écrire le premier : la personne doit d'abord ouvrir son lien.</p>
        </div>
    @else
        <div class="lb-card mt-5" style="padding:20px;max-width:720px">
            <div class="lb-label">Créer ton robot (2 minutes, une seule fois)</div>
            <ol style="margin:8px 0 0;padding-left:20px;font-size:13.5px;color:var(--ink);line-height:1.7">
                <li>Dans Telegram, cherche <b>@BotFather</b> (compte officiel, avec une coche bleue) et ouvre la conversation.</li>
                <li>Écris <code>/newbot</code>.</li>
                <li>Donne un <b>nom</b> à ton robot, celui que verront tes contacts. Exemple : Laurence · Prana Vidya.</li>
                <li>Donne-lui un <b>identifiant</b>, sans espace, qui finit par « bot ». Exemple : <code>laurence_prana_bot</code>.</li>
                <li>BotFather te répond avec un <b>jeton</b>, une longue suite du type <code>123456789:AAH…</code>. Copie-le et colle-le ci-dessous.</li>
            </ol>

            <form wire:submit="connecter" style="margin-top:16px">
                <label for="jeton" class="lb-label">Jeton du robot</label>
                <input id="jeton" type="password" wire:model="jeton" class="lb-field" placeholder="123456789:AAH…" autocomplete="off">
                @error('jeton') <p class="lb-error">{{ $message }}</p> @enderror
                <p class="lb-muted" style="font-size:12px;margin-top:6px">Ce jeton est un mot de passe : ne le partage avec personne. Il est gardé chiffré et n'est plus jamais affiché.</p>
                <div class="flex justify-end" style="margin-top:12px">
                    <button type="submit" class="lb-btn lb-btn-primary" wire:loading.attr="disabled" wire:target="connecter">
                        <span wire:loading.remove wire:target="connecter">Connecter le robot</span>
                        <span wire:loading wire:target="connecter">Connexion…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Messages de comptes non reconnus --}}
    @if ($inconnus->isNotEmpty())
        <h3 class="lb-h3 mt-6" style="font-size:18px">Messages de comptes non reconnus</h3>
        <p class="lb-muted" style="font-size:12.5px;margin-top:2px;max-width:52rem">
            Ces personnes ont écrit au robot sans passer par leur lien personnel. Le robot leur a demandé de l'ouvrir : dès qu'elles le font, leurs messages rejoignent leur fiche.
        </p>
        <div class="lb-people mt-3" style="max-width:720px">
            @foreach ($inconnus as $m)
                <div class="lb-prow" wire:key="inc-{{ $m->id }}" style="align-items:flex-start">
                    <div style="min-width:0;flex:1">
                        <div class="nm">{{ $m->expediteur ?: 'Compte sans nom' }}</div>
                        <div class="mt" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($m->apercu(), 240) }}</div>
                        <div class="lb-muted" style="font-size:11.5px">{{ $m->created_at->translatedFormat('d/m/Y à H:i') }}</div>
                    </div>
                    <button type="button" wire:click="supprimerMessage({{ $m->id }})" class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px;flex:none">Supprimer</button>
                </div>
            @endforeach
        </div>
    @endif
</div>
