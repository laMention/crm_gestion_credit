<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Models\Agence;
use App\Models\Compte;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\Credit;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class AuthService
{
    private const NB_MAX_TENTATIVE = 5;

    private const DECOMPTE_SECONDE_CONNEXION = 60;

    private static ?string $dummyPasswordHash = null;

    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * @return array{
     *     token: string,
     *     token_type: string,
     *     user: array<string, mixed>
     * }
     *
     * @throws AuthenticationException
     * @throws ThrottleRequestsException
     */
    public function login(
        string $email,
        string $password,
        string $ipAddress,
        string $deviceName = 'CRM API'
    ): array {
        $limiterKey = $this->limiterKey($email, $ipAddress);

        if ($this->rateLimiter->tooManyAttempts($limiterKey, self::NB_MAX_TENTATIVE)) {
            $retryAfter = $this->rateLimiter->availableIn($limiterKey);

            throw new ThrottleRequestsException(
                'Trop de tentatives de connexion. Réessayez dans une minute.',
                null,
                ['Retry-After' => (string) $retryAfter]
            );
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        $storedPassword = $user?->password
            ?? (self::$dummyPasswordHash ??= Hash::make(Str::random(40)));
        $passwordMatches = Hash::check($password, $storedPassword);

        if (! $user || ! $user->actif || ! $passwordMatches) {
            $this->rateLimiter->hit($limiterKey, self::DECOMPTE_SECONDE_CONNEXION);

            throw new AuthenticationException('Informations de connexion invalides.');
        }

        $this->rateLimiter->clear($limiterKey);
        $user->forceFill(['derniere_connexion_le' => now()])->save();
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userProfile($user),
        ];
    }

    /**
     * @return array{
     *     uuid: string,
     *     nom: string,
     *     prenom: string,
     *     email: string,
     *     role: string,
     *     agence: array{uuid: string, code: string, nom: string, ville: string}|null
     * }
     */
    public function userProfile(User $user): array
    {
        $user->loadMissing('agence:id,uuid,code_agence,nom_agence,ville_agence');

        return [
            'uuid' => $user->uuid,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'email' => $user->email,
            'role' => $user->role,
            'agence' => $user->agence ? [
                'uuid' => $user->agence->uuid,
                'code' => $user->agence->code_agence,
                'nom' => $user->agence->nom_agence,
                'ville' => $user->agence->ville_agence,
            ] : null,
        ];
    }

    private function limiterKey(string $email, string $ipAddress): string
    {
        return 'auth-login:'.hash('sha256', mb_strtolower($email).'|'.$ipAddress);
    }
}
