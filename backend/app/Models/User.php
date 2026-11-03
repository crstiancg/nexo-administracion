<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password', 'active', 'sede_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Roles y permisos se crean con el guard de Passport; sin esto spatie
     * buscaría los del guard "web" al chequear desde un request de la API.
     */
    protected string $guard_name = 'api';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /** El usuario reservado (config permisos.usuario_oculto). */
    public function esOculto(): bool
    {
        $oculto = config('permisos.usuario_oculto');

        return $oculto !== null && $oculto !== '' && $this->getKey() === (int) $oculto;
    }

    /**
     * Los usuarios que `$quien` puede ver en la administración: todos menos
     * el reservado, salvo que sea él mismo.
     *
     * @param  Builder<User>  $query
     */
    public function scopeVisiblesPara(Builder $query, ?User $quien): void
    {
        $oculto = config('permisos.usuario_oculto');
        if ($oculto === null || $oculto === '' || $quien?->getKey() === (int) $oculto) {
            return;
        }

        $query->whereKeyNot((int) $oculto);
    }

    /**
     * La sede en la que opera (POS, caja, inventario). Sin sede asignada no
     * se mueve stock ni dinero: un 409 que el front muestra tal cual.
     */
    public function sedeOperativa(): int
    {
        if (! $this->sede_id) {
            throw ValidationException::withMessages([
                'sede' => 'Tu usuario no tiene una sede asignada: pedile a un administrador que te asigne una.',
            ])->status(409);
        }

        return $this->sede_id;
    }

    /**
     * Normaliza el username al guardarlo, así la búsqueda del login puede
     * comparar en minúsculas sin depender del collation de la base.
     */
    protected function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = mb_strtolower(trim($value));
    }

    protected function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : mb_strtolower(trim($value));
    }

    /**
     * El password grant de Passport busca sólo por email por defecto; acá el
     * campo "Usuario" del login acepta username o email. Un usuario inactivo se
     * trata igual que uno inexistente para no revelar qué cuentas existen.
     */
    public function findForPassport(string $username): ?self
    {
        $login = mb_strtolower(trim($username));

        return $this->where(fn ($query) => $query->where('username', $login)->orWhere('email', $login))
            ->where('active', true)
            ->first();
    }
}
