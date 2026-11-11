<div>
    <a href="{{ route('reglages.assistant') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux réglages</a>

    <h1 class="lb-h1 mt-4">Réservations du site</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">Chaque réservation prise sur ton site arrive ici : le contact est créé et inscrit à la session du jour, ou un rendez-vous est noté.</p>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif

    {{-- Adresse à coller dans FluentBooking --}}
    <div class="lb-card mt-5" style="padding:20px;max-width:760px" x-data="{ copie: false }">
        <div class="lb-label">Adresse de réception</div>
        @if ($adresse)
            <input type="text" readonly x-ref="adresse" value="{{ $adresse }}" class="lb-field" style="font-size:13px" onclick="this.select()">
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button type="button" class="lb-btn lb-btn-primary"
                        x-on:click="navigator.clipboard.writeText($refs.adresse.value).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                    <span x-show="!copie">Copier l'adresse</span><span x-show="copie" x-cloak>Copié ✓</span>
                </button>
                <button type="button" wire:click="generer" wire:confirm="Remplacer l'adresse ? L'ancienne ne fonctionnera plus : il faudra coller la nouvelle dans FluentBooking."
                        class="lb-btn lb-btn-ghost">Remplacer l'adresse</button>
            </div>
            <p class="lb-muted" style="font-size:12.5px;margin-top:10px">
                Cette adresse est secrète : toute personne qui la connaît peut ajouter des contacts. Ne la partage pas.
            </p>
        @else
            <p class="lb-muted" style="font-size:13.5px">Aucune adresse pour l'instant.</p>
            <button type="button" wire:click="generer" class="lb-btn lb-btn-primary" style="margin-top:10px">Créer mon adresse</button>
        @endif
    </div>

    {{-- Marche à suivre --}}
    <div class="lb-card mt-5" style="padding:20px;max-width:760px">
        <h3 class="lb-h3" style="font-size:18px">Dans FluentBooking</h3>
        <ol style="margin:12px 0 0;padding-left:20px;font-size:14px;line-height:1.7">
            <li>Ouvre le type de réservation concerné (la séance découverte, puis l'appel).</li>
            <li>Onglet <b>Webhooks</b> (ou <b>Intégrations → Webhook</b>), puis « Ajouter un webhook ».</li>
            <li>Colle l'adresse ci-dessus dans « URL », méthode <b>POST</b>, format <b>JSON</b>, corps « toutes les données ».</li>
            <li>Déclencheurs : <b>réservation confirmée</b> et <b>réservation annulée</b>.</li>
            <li>Enregistre, puis fais une réservation d'essai : elle doit apparaître dans le journal ci-dessous.</li>
        </ol>
        <p class="lb-muted" style="font-size:12.5px;margin-top:12px">
            La session est reconnue par sa <b>date</b> : crée dans le CRM une session le même jour que le créneau réservé.
            Sans session ce jour-là (par exemple pour un appel), le CRM note un rendez-vous sur la fiche du contact.
        </p>
    </div>

    {{-- Journal --}}
    <h3 class="lb-h3 mt-7" style="font-size:18px">Dernières réservations reçues</h3>
    <div class="lb-people mt-3" style="max-width:760px">
        @forelse ($recues as $r)
            @php
                [$libelle, $fond, $texte] = match ($r->statut) {
                    \App\Models\ReservationRecue::STATUT_INSCRIT => ['Inscrit', 'var(--mint-tint)', 'var(--mint-deep)'],
                    \App\Models\ReservationRecue::STATUT_RDV => ['Rendez-vous', 'var(--lav-tint)', 'var(--ink)'],
                    \App\Models\ReservationRecue::STATUT_ANNULE => ['Annulé', 'var(--terra-tint)', 'var(--terra)'],
                    default => ['Ignoré', 'var(--gold-tint)', 'var(--accent)'],
                };
            @endphp
            <div wire:key="r-{{ $r->id }}">
                <div class="lb-prow" style="flex-wrap:wrap;row-gap:8px">
                    <span class="lb-tag" style="background:{{ $fond }};color:{{ $texte }}">{{ $libelle }}</span>
                    <div style="min-width:10rem;flex:1">
                        <div class="nm">{{ $r->resume }}</div>
                        <div class="mt">{{ $r->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    @if ($r->contact)
                        <a href="{{ route('contacts.fiche', $r->contact) }}" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px" wire:navigate>Fiche</a>
                    @endif
                    <button type="button" wire:click="basculerDetail({{ $r->id }})" class="lb-btn lb-btn-ghost" style="padding:7px 13px;font-size:12.5px">
                        {{ $detailId === $r->id ? 'Masquer' : 'Détail' }}
                    </button>
                </div>
                @if ($detailId === $r->id)
                    <pre class="lb-card" style="padding:14px;margin:4px 0 12px;font-size:12px;overflow-x:auto;white-space:pre-wrap;overflow-wrap:anywhere">{{ $r->contenu }}</pre>
                @endif
            </div>
        @empty
            <div class="lb-placeholder">Rien reçu pour l'instant.</div>
        @endforelse
    </div>
</div>
