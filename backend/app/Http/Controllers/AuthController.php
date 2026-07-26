<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * JWT auth matching the old DRF SimpleJWT contract:
 * login/refresh return {access, refresh}; refresh rotates and blacklists.
 */
class AuthController extends Controller
{
    private const ACCESS_TTL = 15;            // minutes
    private const REFRESH_TTL = 60 * 24 * 7;  // 7 days

    private function issuePair(User $user): array
    {
        $access = auth('api')->setTTL(self::ACCESS_TTL)->login($user);
        $refresh = auth('api')->claims(['typ' => 'refresh'])
            ->setTTL(self::REFRESH_TTL)->login($user);

        return ['access' => $access, 'refresh' => $refresh];
    }

    /**
     * Self-service registration.
     *
     * Gated behind the `self_registration` feature flag, which ships OFF.
     * An employee account can read the entire ERP — every customer, supplier,
     * price, stock level, journal entry, cheque and bank account — so leaving
     * this open to the internet would hand a company's whole book of business
     * to anyone who could reach the login page. Companies that genuinely want
     * open sign-up can switch it on in Administration → Modules.
     */
    public function register(Request $request)
    {
        if (! FeatureFlag::enabled('self_registration')) {
            return response()->json(
                ['detail' => 'Self-registration is disabled. Ask an administrator for an account.'],
                403
            );
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_EMPLOYEE,
            'is_active' => true,
        ]);

        return response()->json($this->issuePair($user), 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            return response()->json(
                ['detail' => 'No active account found with the given credentials'],
                401
            );
        }

        return response()->json($this->issuePair($user));
    }

    public function refresh(Request $request)
    {
        $token = (string) $request->input('refresh', '');

        try {
            JWTAuth::setToken($token);
            if (JWTAuth::getPayload()->get('typ') !== 'refresh') {
                throw new JWTException('Not a refresh token');
            }
            /** @var User $user */
            $user = JWTAuth::authenticate();
            // Without this a deactivated user could refresh forever, keeping
            // full access long after being offboarded.
            if (! $user || ! $user->is_active) {
                throw new JWTException('Account is not active');
            }
            JWTAuth::invalidate(); // rotation: blacklist the used refresh token
        } catch (JWTException) {
            return response()->json(['detail' => 'Token is invalid or expired'], 401);
        }

        return response()->json($this->issuePair($user));
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::setToken((string) $request->input('refresh', ''));
            JWTAuth::invalidate();
        } catch (JWTException) {
            // Already invalid — logout is idempotent.
        }

        return response()->json(['detail' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->toApi());
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(
                ['current_password' => ['Current password is incorrect.']],
                400
            );
        }

        $user->update(['password' => $data['new_password']]);

        return response()->json(['detail' => 'Password updated.']);
    }
}
