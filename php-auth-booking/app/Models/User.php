<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Requerido por JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Requerido por JWT
    public function getJWTCustomClaims()
    {
        return [
            'iss'  => env('JWT_ISS', 'travelink-laravel'),
            'aud'  => env('JWT_AUD', 'travelink-api'),
        ];
    }

    // Helper methods para verificar roles
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isClient()
    {
        return $this->role === 'client';
    }
}
