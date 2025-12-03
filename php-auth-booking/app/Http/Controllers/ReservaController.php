<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReservaController extends Controller
{
    /**
     * Crear una reserva enviando webhook a .NET
     */
    public function create(Request $request)
    {
        $request->validate([
            'hotel_id' => 'required|integer',
            'habitaciones_ids' => 'required|array',
            'habitaciones_ids.*' => 'integer',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
        ]);

        $user = auth()->user();

        try {
            $dotnetUrl = env('DOTNET_SERVICE_URL', 'http://host.docker.internal:8083');
            $webhookSecret = env('WEBHOOK_SECRET', 'webhook_secret_travelink_2024_secure_key');

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Webhook-Secret' => $webhookSecret,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$dotnetUrl}/api/Reservas", [
                    'hotelId' => $request->hotel_id,
                    'habitacionesIds' => $request->habitaciones_ids,
                    'fechaInicio' => $request->fecha_inicio . 'T00:00:00Z',
                    'fechaFin' => $request->fecha_fin . 'T00:00:00Z',
                    'estadoReserva' => 'activa',
                    'clienteId' => "USER-{$user->id}",
                    'clienteNombre' => $user->name,
                    'clienteEmail' => $user->email,
                ]);

            if ($response->successful()) {
                Log::info('Reserva creada exitosamente en .NET', [
                    'user_id' => $user->id,
                    'dotnet_response' => $response->json(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reserva creada exitosamente',
                    'reserva' => $response->json(),
                ], 201);
            } else {
                Log::error('Error al crear reserva en .NET', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear la reserva',
                    'error' => $response->json() ?? $response->body(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Excepción al enviar webhook a .NET', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la reserva',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
