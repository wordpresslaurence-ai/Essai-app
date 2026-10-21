<div>
    <a href="{{ route('contacts.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux contacts</a>

    <h1 class="lb-h1 mt-4">Export newsletter</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">
        Exporte tes contacts en CSV pour les importer dans FluentCRM et leur envoyer une newsletter.
    </p>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-5">{{ session('message') }}</div>
    @endif
    @if (session('erreur'))
        <div class="lb-alert lb-alert-danger mt-5">{{ session('erreur') }}</div>
    @endif

    <div class="lb-card mt-5" style="padding:22px;max-width:620px">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="etiquette" class="lb-label">Qui exporter ?</label>
                <select id="etiquette" wire:model.live="etiquette" class="lb-field">
                    <option value="">Tous les contacts avec e-mail</option>
                    @foreach ($etiquettes as $et)
                        <option value="{{ $et->id }}">Étiquette « {{ $et->nom }} » ({{ $et->contacts_count }})</option>
                    @endforeach
                </select>
                <p class="lb-muted" style="font-size:12px;margin-top:6px">
                    Astuce : crée une étiquette <strong>Newsletter</strong> et applique-la aux contacts à qui tu écris
                    (les salles non taguées seront exclues). Gérer les étiquettes dans
                    <a href="{{ route('etiquettes.index') }}" wire:navigate style="color:var(--accent)">Étiquettes</a>.
                </p>
            </div>
            <div>
                <label for="scope" class="lb-label">Lesquels ?</label>
                <select id="scope" wire:model.live="scope" class="lb-field">
                    <option value="nouveaux">Seulement les nouveaux (jamais exportés)</option>
                    <option value="tous">Tous (même déjà exportés)</option>
                </select>
            </div>
        </div>

        <div class="lb-inset mt-5" style="padding:14px 16px;font-size:13.5px">
            <strong style="color:var(--ink);font-size:17px">{{ $total }}</strong> contact(s) ciblé(s)
            <span class="lb-muted">· dont</span>
            <strong style="color:var(--accent)">{{ $nouveaux }}</strong> nouveau(x) (jamais exporté)
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mt-5">
            <button type="button" wire:click="reinitialiser"
                    wire:confirm="Réinitialiser le suivi : tous les contacts ciblés redeviendront « à exporter ». Continuer ?"
                    class="lb-btn lb-btn-ghost" style="font-size:12.5px">Réinitialiser le suivi</button>
            <button type="button" wire:click="exporter" class="lb-btn lb-btn-primary">
                ⬇ Exporter en CSV
            </button>
        </div>
    </div>

    <div class="lb-card mt-4" style="padding:18px;max-width:620px">
        <div class="lb-label" style="margin-bottom:8px">Importer dans FluentCRM (version gratuite)</div>
        <ol class="lb-muted" style="font-size:13px;line-height:1.7;padding-left:18px;margin:0">
            <li>WordPress → <strong>FluentCRM → Contacts → Import</strong> → choisis « CSV ».</li>
            <li>Dépose le fichier exporté, puis associe les colonnes (Prénom, Nom, Email…).</li>
            <li>Assigne une <strong>liste</strong> / un <strong>tag</strong> (ex. « Newsletter »), puis importe.</li>
            <li>FluentCRM met à jour les contacts existants (par e-mail) — pas de doublon.</li>
            <li>Ensuite : <strong>Email → Campaigns</strong> pour envoyer ta newsletter.</li>
        </ol>
    </div>
</div>
