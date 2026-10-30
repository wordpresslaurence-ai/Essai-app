<div>
    <a href="{{ route('contacts.fiche', $contact) }}" class="lb-muted" style="font-size:13px;text-decoration:none" wire:navigate>← Retour à la fiche</a>

    <div class="lb-eyebrow mt-4">Débrief d'appel</div>
    <h1 class="lb-h1">{{ $contact->nomComplet() }}</h1>
    <p class="lb-muted" style="font-size:13px;margin-top:2px">
        Transforme la transcription d'un appel (ex. tl;dv) en note + tâche, via ChatGPT. Aucun envoi automatique.
    </p>

    @if ($erreur)
        <div class="lb-alert lb-alert-danger mt-5">{{ $erreur }}</div>
    @endif

    {{-- Étape 1 : le prompt à coller dans ChatGPT --}}
    <div class="lb-card mt-5" style="padding:22px" x-data="{ copie: false }">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="lb-h3" style="font-size:18px">1. Copie ce prompt dans ChatGPT</h3>
            <button type="button" class="lb-btn lb-btn-primary" style="padding:8px 14px;font-size:13px"
                    x-on:click="navigator.clipboard.writeText($refs.prompt.innerText).then(() => { copie = true; setTimeout(() => copie = false, 2000) })">
                <span x-show="!copie">Copier le prompt</span>
                <span x-show="copie" x-cloak>✓ Copié</span>
            </button>
        </div>
        <p class="lb-muted" style="font-size:12.5px;margin-top:4px">
            Dans ChatGPT, colle ce prompt puis, à la fin, la transcription de l'appel. Récupère le débrief et reviens le coller ici.
        </p>
        <pre x-ref="prompt" style="white-space:pre-wrap;word-break:break-word;background:var(--bg);border-radius:12px;padding:16px;margin-top:12px;font-size:12px;line-height:1.5;box-shadow:var(--inset);max-height:360px;overflow:auto">{{ $prompt }}</pre>
    </div>

    {{-- Étape 2 : coller le débrief renvoyé --}}
    <div class="lb-card mt-5" style="padding:22px">
        <h3 class="lb-h3" style="font-size:18px">2. Colle ici le débrief renvoyé par ChatGPT</h3>
        <textarea wire:model="contenu" rows="8" class="lb-field" style="margin-top:12px;font-family:ui-monospace,monospace;font-size:12.5px"
                  placeholder="=== DÉBRIEF D'APPEL ===&#10;TYPE : prospection_salle&#10;RÉSUMÉ : …"></textarea>
        <div class="mt-3">
            <button type="button" wire:click="analyserDebrief" class="lb-btn lb-btn-primary" style="padding:9px 16px;font-size:13px">
                Analyser le débrief
            </button>
        </div>
    </div>

    {{-- Étape 3 : aperçu éditable avant écriture --}}
    @if ($analyse)
        <div class="lb-card mt-5" style="padding:22px">
            <h3 class="lb-h3" style="font-size:18px">3. Vérifie et enregistre</h3>
            <p class="lb-muted" style="font-size:12.5px;margin-top:4px">Tu peux tout corriger avant d'enregistrer. Rien n'est écrit tant que tu ne cliques pas sur « Enregistrer ».</p>

            {{-- Note d'historique --}}
            <label class="lb-eyebrow" style="display:block;margin-top:16px">Note ajoutée à la fiche</label>
            <textarea wire:model="noteTexte" rows="8" class="lb-field" style="margin-top:6px;font-size:13px;line-height:1.5"></textarea>
            @error('noteTexte') <div class="lb-alert lb-alert-danger mt-2">{{ $message }}</div> @enderror

            {{-- Capacité / tarif --}}
            <div class="flex flex-wrap gap-4 mt-5" style="align-items:flex-end">
                <div style="flex:1 1 160px;min-width:0">
                    <label class="lb-eyebrow" style="display:block">Capacité (personnes)</label>
                    <input type="number" min="1" wire:model="capacite" class="lb-field" style="margin-top:6px" placeholder="ex. 40">
                    @error('capacite') <div class="lb-alert lb-alert-danger mt-2">{{ $message }}</div> @enderror
                </div>
                <div style="flex:1 1 220px;min-width:0">
                    <label class="lb-eyebrow" style="display:block">Tarif de location</label>
                    <input type="text" wire:model="tarif" class="lb-field" style="margin-top:6px" placeholder="ex. 180 €/jour">
                </div>
            </div>
            <label class="flex items-center gap-2 mt-3" style="font-size:13px;cursor:pointer">
                <input type="checkbox" wire:model="majFiche">
                Mettre à jour la capacité / le tarif sur la fiche
            </label>

            {{-- Tâches de relance (une par action détectée) --}}
            <div class="flex items-center justify-between gap-3 mt-6 flex-wrap">
                <label class="lb-eyebrow" style="display:block">Tâches de relance à créer</label>
                <button type="button" wire:click="ajouterTache" class="lb-btn lb-btn-ghost" style="padding:5px 11px;font-size:12px">+ Ajouter une tâche</button>
            </div>
            <p class="lb-muted" style="font-size:12px;margin-top:2px">Décoche celles que tu ne veux pas créer. Chaque action du débrief devient une tâche.</p>

            @forelse ($taches as $i => $t)
                <div class="flex flex-wrap gap-3 mt-3" style="align-items:flex-end" wire:key="tache-{{ $i }}">
                    <label class="flex items-center" style="padding-bottom:11px">
                        <input type="checkbox" wire:model="taches.{{ $i }}.creer">
                    </label>
                    <div style="flex:3 1 260px;min-width:0">
                        <input type="text" wire:model="taches.{{ $i }}.titre" class="lb-field" placeholder="Intitulé de la tâche">
                        @error('taches.'.$i.'.titre') <div class="lb-alert lb-alert-danger mt-2">{{ $message }}</div> @enderror
                    </div>
                    <div style="flex:1 1 150px;min-width:0">
                        <input type="date" wire:model="taches.{{ $i }}.echeance" class="lb-field">
                        @error('taches.'.$i.'.echeance') <div class="lb-alert lb-alert-danger mt-2">{{ $message }}</div> @enderror
                    </div>
                    <button type="button" wire:click="retirerTache({{ $i }})" class="lb-iconbtn" title="Retirer" aria-label="Retirer cette tâche" style="margin-bottom:2px">✕</button>
                </div>
            @empty
                <p class="lb-muted" style="font-size:12.5px;margin-top:6px">Aucune action détectée. Tu peux en ajouter une si besoin.</p>
            @endforelse

            {{-- Rattachement opportunité --}}
            @if ($opportunites->isNotEmpty())
                <div class="mt-5" style="max-width:420px">
                    <label class="lb-eyebrow" style="display:block">Rattacher à une opportunité (facultatif)</label>
                    <select wire:model="opportuniteId" class="lb-field" style="margin-top:6px">
                        <option value="">— Aucune —</option>
                        @foreach ($opportunites as $op)
                            <option value="{{ $op->id }}">{{ $op->titre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex flex-wrap gap-2 mt-6">
                <button type="button" wire:click="enregistrer" class="lb-btn lb-btn-primary" style="padding:10px 18px;font-size:13px">
                    Enregistrer le débrief
                </button>
                <a href="{{ route('contacts.fiche', $contact) }}" class="lb-btn lb-btn-ghost" style="padding:10px 18px;font-size:13px" wire:navigate>Annuler</a>
            </div>
        </div>
    @endif
</div>
