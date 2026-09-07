<div>
    {{-- En-tête --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="lb-eyebrow">{{ ucfirst(now()->translatedFormat('l j F')) }}</div>
            <h1 class="lb-h1">Tableau de bord</h1>
        </div>
        <a href="{{ route('contacts.creer') }}" class="lb-btn lb-btn-primary" wire:navigate>+ Nouveau contact</a>
    </div>

    {{-- Chiffres clés --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-7">
        <div class="lb-stat">
            <span class="lb-av lb-av-34 lb-av-mint ic" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 19a6.5 6.5 0 0 1 13 0"/><path d="M17 8a3.5 3.5 0 0 1 0 7"/><path d="M22 19a6 6 0 0 0-4-5.7"/></svg>
            </span>
            <div class="k">Contacts</div>
            <div class="v">{{ $nbContacts }}</div>
            <div class="d">au total</div>
        </div>
        <div class="lb-stat">
            <span class="lb-av lb-av-34 lb-av-mint ic" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-6h6v6"/></svg>
            </span>
            <div class="k">Entreprises</div>
            <div class="v">{{ $nbEntreprises }}</div>
            <div class="d">sociétés</div>
        </div>
        <div class="lb-stat">
            <span class="lb-av lb-av-34 lb-av-lav ic" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
            </span>
            <div class="k">Personnes</div>
            <div class="v">{{ $nbPersonnes }}</div>
            <div class="d">individus</div>
        </div>
        <div class="lb-stat">
            <span class="lb-av lb-av-34 lb-av-gold ic" aria-hidden="true">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
            </span>
            <div class="k">Archivés</div>
            <div class="v">{{ $nbArchives }}</div>
            <div class="d">consultables à part</div>
        </div>
    </div>

    {{-- Colonnes : derniers contacts + à venir --}}
    <div class="grid gap-6 lg:grid-cols-2 mt-8">
        {{-- Derniers contacts --}}
        <div>
            <div class="flex items-end justify-between">
                <div>
                    <h3 class="lb-h3">Derniers contacts</h3>
                    <div class="lb-muted" style="font-size:12.5px">Ajoutés récemment à ton carnet</div>
                </div>
                <a href="{{ route('contacts.index') }}" class="lb-accent" style="font-weight:600;font-size:13px;text-decoration:none" wire:navigate>Voir tout →</a>
            </div>

            <div class="lb-people mt-3">
                @forelse ($derniers as $contact)
                    <a href="{{ route('contacts.fiche', $contact) }}" class="lb-prow" wire:navigate wire:key="d-{{ $contact->id }}" style="text-decoration:none">
                        <span class="lb-av lb-av-44 lb-av-{{ $contact->couleurAvatar() }} {{ $contact->estEntreprise() ? 'sq' : '' }}">{{ $contact->initiales() }}</span>
                        <div style="min-width:0">
                            <div class="nm lb-truncate">{{ $contact->nomComplet() }}</div>
                            <div class="mt lb-truncate">
                                {{ $contact->estEntreprise() ? 'Entreprise' : ($contact->fonction ?: 'Personne') }}
                                @if ($contact->entreprise) · {{ $contact->entreprise->nom }} @endif
                            </div>
                        </div>
                        <span class="lb-muted" style="margin-left:auto;font-size:12px;white-space:nowrap">{{ $contact->created_at?->diffForHumans(short: true) }}</span>
                    </a>
                @empty
                    <div class="lb-placeholder">
                        Aucun contact pour l'instant.
                        <a href="{{ route('contacts.creer') }}" class="lb-accent" style="font-weight:600" wire:navigate>Créer le premier ?</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- À venir : pipeline & activités --}}
        <div>
            <h3 class="lb-h3">Pipeline &amp; activités</h3>
            <div class="lb-muted" style="font-size:12.5px">Prochains modules du CRM</div>
            <div class="lb-placeholder mt-3" style="padding:34px 22px">
                🌿 Bientôt ici : ton <strong>pipeline commercial</strong> (opportunités par étape)
                et tes <strong>tâches du jour</strong> (rappels, rendez-vous).
            </div>
        </div>
    </div>
</div>
