<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use App\Services\Reservations\TraitementReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reçoit les réservations envoyées par le site (FluentBooking). Pas de session :
 * l'appel est authentifié par le jeton secret présent dans l'adresse.
 */
class WebhookReservationController extends Controller
{
    public function __invoke(Request $request, string $jeton, TraitementReservation $traitement): JsonResponse
    {
        $attendu = (string) Parametre::instance()->webhook_jeton;

        // Adresse inconnue tant qu'aucun jeton n'a été généré, ou si le jeton est faux.
        abort_unless($attendu !== '' && hash_equals($attendu, $jeton), 404);

        $seance = $request->query('seance');

        $ligne = $traitement->traiter(
            $request->except(['seance']),
            is_numeric($seance) ? (int) $seance : null,
        );

        // Toujours 200 une fois le jeton validé : le site n'a pas à réessayer.
        return response()->json(['ok' => true, 'statut' => $ligne->statut]);
    }
}
