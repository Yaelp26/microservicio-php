<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            'role'      => 'sometimes|in:admin,client', // Opcional, por defecto será 'client'
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => $request->input('role', 'client'), // Por defecto 'client'
        ]);

        // Enviar email de bienvenida
        try {
            Mail::raw(
                "¡Bienvenido a Travalink, {$user->name}!\n\nTu cuenta ha sido creada exitosamente el " . now()->format('d/m/Y H:i:s') . ".\n\nEmail: {$user->email}\n\nYa puedes iniciar sesión y comenzar a hacer reservaciones.\n\n¡Gracias por registrarte!",
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Bienvenido a Travalink');
                }
            );
            Log::info('Email de bienvenida enviado', ['user_id' => $user->id, 'email' => $user->email]);
        } catch (\Exception $e) {
            Log::error('Error al enviar email de bienvenida', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }

        return response()->json([
            'message' => 'Usuario registrado correctamente.',
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

    // Cambio de contraseña con notificación por correo
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
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

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Se ha enviado una notificación a tu correo.',
        ]);
    }

    // Solicitar recuperación de contraseña
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();
        
        // Generar token único
        $token = Str::random(60);
        
        // Guardar en tabla password_reset_tokens
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        // URL para resetear contraseña (frontend debe implementar esta ruta)
        $resetUrl = env('FRONTEND_URL', 'http://localhost:3000') . "/reset-password/{$token}";

        // Enviar email con link
        try {
            Mail::raw(
                "Hola {$user->name},\n\nRecibimos una solicitud para restablecer tu contraseña.\n\nHaz clic en el siguiente enlace para crear una nueva contraseña:\n{$resetUrl}\n\nEste enlace expira en 1 hora.\n\nSi no solicitaste este cambio, ignora este correo.",
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Recuperación de contraseña - Booking App');
                }
            );
            
            Log::info('Email de recuperación enviado', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Error al enviar email de recuperación', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'error' => 'Error al enviar el correo de recuperación'
            ], 500);
        }

        return response()->json([
            'message' => 'Se ha enviado un correo con instrucciones para restablecer tu contraseña.'
        ]);
    }

    // Restablecer contraseña con token
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Buscar el reset token
        $resetRecord = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'error' => 'Token inválido'
            ], 400);
        }

        // Verificar que el token coincida
        if (!Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'error' => 'Token inválido'
            ], 400);
        }

        // Verificar que no haya expirado (1 hora)
        if (now()->diffInHours($resetRecord->created_at) > 1) {
            return response()->json([
                'error' => 'El token ha expirado. Solicita un nuevo enlace de recuperación.'
            ], 400);
        }

        // Actualizar contraseña
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Eliminar el token usado
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        Log::info('Contraseña restablecida exitosamente', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Contraseña restablecida exitosamente. Ya puedes iniciar sesión.'
        ]);
    }
}
