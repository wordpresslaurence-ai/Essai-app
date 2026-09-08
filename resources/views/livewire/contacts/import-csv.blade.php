<div>
    <a href="{{ route('contacts.index') }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour au carnet</a>
    <h1 class="lb-h1 mt-4">Importer des contacts (CSV)</h1>

    {{-- ÉTAPE 1 : upload --}}
    @if ($etape === 'upload')
        <div class="lb-card mt-5" style="padding:24px">
            <p class="lb-muted" style="font-size:13.5px">
                Sélectionne un fichier CSV (par exemple exporté depuis Odoo). La première ligne doit
                contenir les noms de colonnes. Séparateur « , » ou « ; » accepté.
            </p>
            <div class="mt-4">
                <label for="fichier" class="lb-label">Fichier CSV</label>
                <input id="fichier" type="file" wire:model="fichier" accept=".csv,text/csv" class="lb-field" style="box-shadow:var(--raise-sm)">
                <div wire:loading wire:target="fichier" class="lb-muted mt-2" style="font-size:13px">Lecture du fichier…</div>
                @error('fichier') <p class="lb-error">{{ $message }}</p> @enderror
            </div>
        </div>
    @endif

    {{-- ÉTAPE 2 : mapping + aperçu --}}
    @if ($etape === 'mapping')
        <div class="lb-card mt-5" style="padding:24px">
            <h3 class="lb-h3" style="font-size:19px">1. Faire correspondre les colonnes</h3>
            <p class="lb-muted" style="font-size:13px;margin-top:4px">Indique à quel champ correspond chaque colonne (ou « Ignorer »).</p>
            <div class="mt-4 flex flex-col gap-2">
                @foreach ($entetes as $i => $entete)
                    <div class="flex items-center gap-3">
                        <span class="lb-truncate" style="width:45%;font-size:13.5px" title="{{ $entete }}">{{ $entete ?: '(colonne '.($i + 1).')' }}</span>
                        <span class="lb-muted" aria-hidden="true">→</span>
                        <select wire:model.live="mapping.{{ $i }}" class="lb-field" style="width:45%">
                            <option value="">— Ignorer —</option>
                            @foreach ($champs as $cle => $libelle)
                                <option value="{{ $cle }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($apercu)
            <div class="lb-card mt-4" style="padding:24px">
                <h3 class="lb-h3" style="font-size:19px">2. Récapitulatif avant import</h3>
                <div class="grid grid-cols-3 gap-3 mt-3 text-center">
                    <div class="lb-inset" style="padding:14px">
                        <div class="serif" style="font-size:28px;font-weight:600">{{ $apercu['total'] }}</div>
                        <div class="lb-muted" style="font-size:12px">lignes</div>
                    </div>
                    <div class="lb-card-sm" style="background:var(--mint-tint)">
                        <div class="serif" style="font-size:28px;font-weight:600;color:var(--mint-deep)">{{ $apercu['valides'] }}</div>
                        <div style="font-size:12px;color:var(--mint-deep)">valides</div>
                    </div>
                    <div class="lb-card-sm" style="background:var(--terra-tint)">
                        <div class="serif" style="font-size:28px;font-weight:600;color:var(--terra)">{{ count($apercu['erreurs']) }}</div>
                        <div style="font-size:12px;color:var(--terra)">en erreur</div>
                    </div>
                </div>

                @if ($apercu['doublons'] > 0)
                    <p class="lb-accent mt-3" style="font-size:13px">⚠️ {{ $apercu['doublons'] }} doublon(s) potentiel(s) (même e-mail) — importés tout de même.</p>
                @endif

                @if (count($apercu['erreurs']) > 0)
                    <div class="lb-inset mt-3" style="padding:14px;max-height:12rem;overflow-y:auto">
                        <p style="font-weight:600;font-size:13px">Lignes en erreur (ignorées) :</p>
                        <ul class="mt-1" style="font-size:12.5px;color:var(--terra)">
                            @foreach ($apercu['erreurs'] as $err)
                                <li>Ligne {{ $err['ligne'] }} : {{ implode(' ', $err['messages']) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex items-center justify-between mt-5">
                    <button type="button" wire:click="recommencer" class="lb-muted" style="font-size:13px;background:none;border:0;cursor:pointer">← Choisir un autre fichier</button>
                    <button type="button" wire:click="importer" @disabled($apercu['valides'] === 0) class="lb-btn lb-btn-primary" style="{{ $apercu['valides'] === 0 ? 'opacity:.5' : '' }}">
                        Importer {{ $apercu['valides'] }} contact(s)
                    </button>
                </div>
            </div>
        @endif
    @endif

    {{-- ÉTAPE 3 : terminé --}}
    @if ($etape === 'termine')
        <div class="lb-card mt-5" style="padding:24px">
            <h3 class="lb-h3" style="font-size:19px">Import terminé</h3>
            <div class="lb-alert lb-alert-success mt-3">✅ {{ $rapport['importes'] }} contact(s) importé(s).</div>
            @if (count($rapport['ignorees']) > 0)
                <p style="color:var(--terra);font-size:13.5px;margin-top:10px">{{ count($rapport['ignorees']) }} ligne(s) ignorée(s) :</p>
                <ul class="lb-inset mt-2" style="padding:14px;max-height:12rem;overflow-y:auto;font-size:12.5px;color:var(--terra)">
                    @foreach ($rapport['ignorees'] as $err)
                        <li>Ligne {{ $err['ligne'] }} : {{ implode(' ', $err['messages']) }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="flex gap-3 mt-5">
                <a href="{{ route('contacts.index') }}" class="lb-btn lb-btn-primary" wire:navigate>Voir mes contacts</a>
                <button type="button" wire:click="recommencer" class="lb-btn lb-btn-ghost">Importer un autre fichier</button>
            </div>
        </div>
    @endif
</div>
