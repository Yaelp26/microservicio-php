<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    // Registro de usuario
    public function register(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|unique:users,email',
            'password'  => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'user'    => $user
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'No se pudo generar el token'
            ], 500);
        }

        return response()->json([
            'message' => 'Login exitoso',
            'token'   => $token,
            'user'    => auth()->user()
        ]);
    }

    // Cambio de contraseña con notificación por correo y webhook
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $user = auth()->user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => 'La contraseña actual es incorrecta'
            ], 400);
        }

        // Actualizar contraseña
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Enviar notificación por correo
        try {
            Mail::raw(
                "Hola {$user->name},\n\nTu contraseña ha sido cambiada exitosamente el " . now()->format('d/m/Y H:i:s') . ".\n\nSi no fuiste tú, contacta al soporte inmediatamente.",
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Cambio de contraseña - Booking App');
                }
            );
            Log::info('Email de cambio de contraseña enviado', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Error al enviar email', ['error' => $e->getMessage()]);
        }

        // Enviar webhook a servicio externo
        try {
            $webhookUrl = env('WEBHOOK_URL');
            if ($webhookUrl) {
                Http::timeout(5)->post($webhookUrl, [
                    'event'      => 'password_changed',
                    'user_id'    => $user->id,
                    'user_email' => $user->email,
                    'user_name'  => $user->name,
                    'timestamp'  => now()->toIso8601String(),
                ]);
                Log::info('Webhook enviado exitosamente', ['user_id' => $user->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar webhook', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Se ha enviado una notificación a tu correo.',
        ]);
    }
}
