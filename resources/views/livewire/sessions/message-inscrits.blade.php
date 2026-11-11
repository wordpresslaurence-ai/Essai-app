<div>
    <a href="{{ route('sessions.fiche', $seance) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la session</a>

    <div class="mt-4">
        <div class="lb-eyebrow">Message WhatsApp aux inscrits</div>
        <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
        <p class="lb-muted" style="font-size:13px;margin-top:4px">
            Écris le message une fois. Le CRM l'ouvre dans WhatsApp pour chaque personne, tu appuies sur Envoyer, puis tu passes à la suivante.
        </p>
    </div>

    {{-- Le message --}}
    <div class="lb-card mt-5" style="padding:20px">
        @if ($modeles->isNotEmpty())
            <label for="modeleId" class="lb-label">Partir d'un modèle (facultatif)</label>
            <select id="modeleId" wire:model.live="modeleId" class="lb-field" style="max-width:26rem">
                <option value="">— Écrire librement —</option>
                @foreach ($modeles as $m)
                    <option value="{{ $m->id }}">{{ $m->nom }}</option>
                @endforeach
            </select>
        @endif

        <label for="message" class="lb-label" style="margin-top:14px">Ton message</label>
        <textarea id="message" rows="6" wire:model.live.debounce.500ms="message" class="lb-field"
                  placeholder="Bonjour {prenom}, un mot avant notre séance de {date} à {heure}…"></textarea>
        <p class="lb-muted" style="font-size:12px;margin-top:6px">
            Variables utiles : <code>{prenom}</code> <code>{session}</code> <code>{date}</code> <code>{heure}</code> <code>{lieu}</code> <code>{lien}</code> <code>{lien_groupe}</code> <code>{moi}</code>.
            Une variable vide apparaît entre crochets, comme <code>[lien_groupe]</code>.
        </p>

        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin-top:10px;font-size:13.5px">
            <input type="checkbox" wire:model.live="avecAttente" style="width:16px;height:16px;accent-color:var(--accent)">
            Inclure aussi la liste d'attente
        </label>
    </div>

    {{-- Avancement --}}
    <div class="lb-card mt-4 flex flex-wrap items-center justify-between gap-3" style="padding:16px 20px">
        <div>
            <span style="font-size:22px;font-weight:600;color:var(--ink);font-variant-numeric:tabular-nums">{{ $nbEnvoyes }} / {{ $nbJoignables }}</span>
            <span class="lb-muted"> message(s) ouvert(s)</span>
            @if ($lignes->count() > $nbJoignables)
                <span class="lb-muted" style="font-size:12.5px"> · {{ $lignes->count() - $nbJoignables }} sans numéro WhatsApp</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($nbEnvoyes > 0)
                <button type="button" wire:click="recommencer" class="lb-btn lb-btn-ghost">Recommencer</button>
            @endif
            @if ($suivante)
                <a href="{{ $suivante['lien'] }}" target="_blank" rel="noopener" wire:click="marquerEnvoye({{ $suivante['contact']->id }})"
                   class="lb-btn lb-btn-primary" style="text-decoration:none">💬 Suivant : {{ $suivante['contact']->nomComplet() }}</a>
            @elseif ($nbJoignables > 0 && trim($message) !== '')
                <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Tout le monde a été servi ✓</span>
            @endif
        </div>
    </div>

    {{-- Les inscrits --}}
    <div class="lb-people mt-4">
        @forelse ($lignes as $l)
            <div class="lb-prow" wire:key="mi-{{ $l['contact']->id }}" style="flex-wrap:wrap;row-gap:8px">
                @if ($l['envoye'])
                    <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Ouvert ✓</span>
                @elseif (! $l['numero'])
                    <span class="lb-tag" style="background:var(--terra-tint);color:var(--terra)">Pas de numéro</span>
                @else
                    <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">À envoyer</span>
                @endif
                <a href="{{ route('contacts.fiche', $l['contact']) }}" wire:navigate style="min-width:10rem;flex:1;text-decoration:none;color:inherit">
                    <div class="nm lb-truncate">{{ $l['contact']->nomComplet() }}@if ($l['attente']) <span class="lb-muted" style="font-weight:400">· liste d'attente</span>@endif</div>
                    <div class="mt lb-truncate">{{ $l['contact']->telephone ?: 'aucun téléphone sur la fiche' }}</div>
                </a>
                @if ($l['lien'])
                    <a href="{{ $l['lien'] }}" target="_blank" rel="noopener" wire:click="marquerEnvoye({{ $l['contact']->id }})"
                       class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px;text-decoration:none">{{ $l['envoye'] ? 'Rouvrir WhatsApp' : 'Ouvrir WhatsApp' }}</a>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">Personne n'est inscrit à cette session pour l'instant.</div>
        @endforelse
    </div>

    <p class="lb-muted" style="font-size:12px;margin-top:12px">
        Le CRM n'envoie rien : chaque message s'ouvre dans WhatsApp et part quand tu appuies sur Envoyer. Le texte est gardé dans l'historique de chaque contact.
        Pour une annonce à tout le groupe d'un coup, utilise plutôt ta communauté WhatsApp.
    </p>
</div>
