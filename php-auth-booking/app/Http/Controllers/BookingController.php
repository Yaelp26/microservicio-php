<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Tymon\JWTAuth\Facades\JWTAuth;

class BookingController extends Controller
{
    // Crear reserva
    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'hotel_id'  => 'required|string',
            'room_type' => 'required|string',
            'check_in'  => 'required|date',
            'check_out' => 'required|date|after_or_equal:check_in',
        ]);

        $reservation = Reservation::create([
            'user_id'   => $user->id,
            'hotel_id'  => $request->hotel_id,
            'room_type' => $request->room_type,
            'check_in'  => $request->check_in,
            'check_out' => $request->check_out,
            'status'    => 'active',
        ]);

        return response()->json([
            'message' => 'Reserva creada',
            'reservation' => $reservation
        ], 201);
    }

    // Listar reservas del usuario
    public function index()
    {
        $user = auth()->user();

        $reservations = Reservation::where('user_id', $user->id)->get();

        return response()->json($reservations);
    }

    // Cancelar reserva
    public function cancel($id)
    {
        $user = auth()->user();

        $reservation = Reservation::where('id', $id)
                                  ->where('user_id', $user->id)
                                  ->first();

        if (!$reservation) {
            return response()->json([
                'error' => 'Reserva no encontrada'
            ], 404);
        }

        $reservation->status = 'cancelled';
        $reservation->save();

        return response()->json([
            'message' => 'Reserva cancelada',
            'reservation' => $reservation
        ]);
    }
}
