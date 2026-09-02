<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * JWT auth matching the old DRF SimpleJWT contract:
 * login/refresh return {access, refresh}; refresh rotates and blacklists.
 */
class AuthController extends Controller
{
    private const ACCESS_TTL = 15;            // minutes — short by default; JWT_TTL can raise it in dev
    private const REFRESH_TTL = 60 * 24 * 7;  // 7 days

    private function issuePair(User $user): array
    {
        // Env-configurable so a dev session does not expire mid-use (which
        // blanked pages until a refresh); production leaves it unset -> 15 min.
        $accessTtl = (int) env('JWT_TTL', self::ACCESS_TTL);
        $access = auth('api')->setTTL($accessTtl)->login($user);
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

    /** Lock the account+IP after this many failed attempts, for LOCKOUT_SECONDS. */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900; // 15 minutes

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Account lockout: too many failures for this email+IP → refuse for a
        // while, independent of the per-minute throttle. Blunts password
        // guessing without needing a captcha.
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return response()->json([
                'detail' => "Too many failed attempts. Try again in {$minutes} minute(s), or reset your password.",
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            return response()->json(
                ['detail' => 'No active account found with the given credentials'],
                401
            );
        }

        RateLimiter::clear($key); // successful login resets the counter
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

    /**
     * Self-service profile edit. Name is free to change; changing the email
     * requires the current password (it is a login credential). Role and
     * active-state are deliberately NOT editable here — only an admin can
     * change those, via the Users module.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:150'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['sometimes', 'string'],
        ]);

        $changes = [];
        foreach (['first_name', 'last_name'] as $f) {
            if (array_key_exists($f, $data)) {
                $changes[$f] = $data[$f] ?? '';
            }
        }

        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                return response()->json(
                    ['current_password' => ['Enter your current password to change your email.']],
                    400
                );
            }
            $changes['email'] = $data['email'];
        }

        if ($changes) {
            $user->update($changes);
        }

        return response()->json($user->fresh()->toApi());
    }

    /**
     * Request a password reset. Always returns the same response whether or not
     * the email exists, so the endpoint cannot be used to enumerate accounts.
     *
     * No mail provider is wired up, so the token is logged (and, in local dev
     * only, returned) rather than emailed. Send it by mail to go to production.
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $devToken = null;
        $user = User::where('email', $data['email'])->first();
        if ($user && $user->is_active) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );
            Log::info('password reset requested (no mailer wired)', ['email' => $user->email, 'token' => $token]);
            if (! app()->environment('production')) {
                $devToken = $token; // dev/test convenience since email isn't sent; never in production
            }
        }

        return response()->json([
            'detail' => 'If an account exists for that email, a reset link has been sent.',
            'dev_token' => $devToken,
        ]);
    }

    /** Complete a password reset with the emailed token. Tokens expire in 1h. */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        if (! $row || ! Hash::check($data['token'], $row->token)) {
            return response()->json(['detail' => 'This reset link is invalid.'], 400);
        }
        if (Carbon::parse($row->created_at)->addHour()->isPast()) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return response()->json(['detail' => 'This reset link has expired. Request a new one.'], 400);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['detail' => 'This reset link is invalid.'], 400);
        }
        $user->update(['password' => $data['new_password']]);
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return response()->json(['detail' => 'Your password has been reset. You can now sign in.']);
    }
}
