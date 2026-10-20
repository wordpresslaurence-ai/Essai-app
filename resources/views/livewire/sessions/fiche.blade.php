<div>
    <a href="{{ route('sessions.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour aux sessions</a>

    @if (session('message'))
        <div class="lb-alert lb-alert-success mt-4">{{ session('message') }}</div>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-4 mt-4">
        <div style="min-width:0">
            <h1 class="lb-h1" style="font-size:28px">{{ $seance->titre }}</h1>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2" style="font-size:13px;color:var(--text-muted)">
                @if ($seance->categorie)
                    <span><span class="lb-dot" style="background:{{ $seance->categorie->couleur ?: 'var(--mint)' }}"></span> {{ $seance->categorie->libelleComplet() }}</span>
                @endif
                @if ($seance->date_debut)
                    <span>📅 {{ $seance->date_debut->translatedFormat('d/m/Y') }}{{ $seance->date_debut->format('H:i') !== '00:00' ? ' · '.$seance->date_debut->format('H:i') : '' }}</span>
                @endif
                @if ($seance->lieu)
                    <span>📍 {{ $seance->lieu }}</span>
                @endif
            </div>
        </div>
        <a href="{{ route('sessions.modifier', $seance) }}" class="lb-btn lb-btn-primary" wire:navigate>Modifier</a>
    </div>

    {{-- Compteur de places --}}
    <div class="lb-card mt-5" style="padding:16px 20px">
        <span style="font-size:22px;font-weight:600;color:var(--ink)">{{ $confirmes->count() }}@if ($seance->capacite)<span class="lb-muted" style="font-size:16px"> / {{ $seance->capacite }}</span>@endif</span>
        <span class="lb-muted"> confirmé(s)</span>
        @if ($seance->capacite)
            <span class="lb-tag" style="margin-left:10px;{{ $confirmes->count() >= $seance->capacite ? 'background:var(--terra-tint);color:var(--terra)' : 'background:var(--mint-tint);color:var(--mint-deep)' }}">
                {{ $confirmes->count() >= $seance->capacite ? 'Complet' : max(0, $seance->capacite - $confirmes->count()).' place(s) restante(s)' }}
            </span>
        @endif
        @if ($enAttente->isNotEmpty())
            <span class="lb-tag" style="margin-left:6px;background:var(--gold-tint);color:var(--accent)">{{ $enAttente->count() }} en attente</span>
        @endif
    </div>

    {{-- Inscrire un contact --}}
    <form wire:submit="inscrire" class="lb-card mt-4 flex flex-wrap items-end gap-3" style="padding:16px">
        <div style="flex:1;min-width:14rem">
            <label for="contactAInscrire" class="lb-label">Inscrire un contact</label>
            <select id="contactAInscrire" wire:model="contactAInscrire" class="lb-field">
                <option value="">— Choisir un contact —</option>
                @foreach ($contactsDispo as $c)
                    <option value="{{ $c->id }}">{{ $c->nomComplet() }}</option>
                @endforeach
            </select>
            @error('contactAInscrire') <p class="lb-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="lb-btn lb-btn-primary">Inscrire</button>
    </form>
    @if ($seance->capacite && $confirmes->count() >= $seance->capacite)
        <p class="lb-muted" style="font-size:12.5px;margin-top:6px">La session est complète : les nouvelles inscriptions iront en liste d'attente.</p>
    @endif

    {{-- Confirmés --}}
    <h3 class="lb-h3 mt-6" style="font-size:18px">Confirmés</h3>
    <div class="lb-people mt-3">
        @forelse ($confirmes as $ins)
            <div class="lb-prow" wire:key="c-{{ $ins->id }}">
                <span class="lb-tag" style="background:var(--mint-tint);color:var(--mint-deep)">Confirmé</span>
                <a href="{{ route('contacts.fiche', $ins->contact) }}" wire:navigate style="min-width:0;flex:1;text-decoration:none;color:inherit">
                    <div class="nm lb-truncate">{{ $ins->contact->nomComplet() }}</div>
                </a>
                <button type="button" wire:click="desinscrire({{ $ins->id }})" wire:confirm="Retirer cette inscription ?"
                        class="lb-btn lb-btn-ghost" style="margin-left:auto;padding:6px 11px;font-size:12px">Désinscrire</button>
            </div>
        @empty
            <div class="lb-placeholder">Aucun inscrit confirmé.</div>
        @endforelse
    </div>

    {{-- Liste d'attente --}}
    @if ($enAttente->isNotEmpty())
        <h3 class="lb-h3 mt-6" style="font-size:18px">Liste d'attente</h3>
        <div class="lb-people mt-3">
            @foreach ($enAttente as $i => $ins)
                <div class="lb-prow" wire:key="a-{{ $ins->id }}">
                    <span class="lb-tag" style="background:var(--gold-tint);color:var(--accent)">{{ $i + 1 }}ᵉ</span>
                    <a href="{{ route('contacts.fiche', $ins->contact) }}" wire:navigate style="min-width:0;flex:1;text-decoration:none;color:inherit">
                        <div class="nm lb-truncate">{{ $ins->contact->nomComplet() }}</div>
                    </a>
                    <div style="margin-left:auto" class="flex items-center gap-2">
                        <button type="button" wire:click="promouvoir({{ $ins->id }})"
                                class="lb-btn lb-btn-primary" style="padding:6px 11px;font-size:12px">Promouvoir</button>
                        <button type="button" wire:click="desinscrire({{ $ins->id }})" wire:confirm="Retirer cette inscription ?"
                                class="lb-btn lb-btn-ghost" style="padding:6px 11px;font-size:12px">Retirer</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
