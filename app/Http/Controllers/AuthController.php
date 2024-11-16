<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show the registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle the registration form submission.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function register(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'age' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
        ]);

        // Create a new user instance
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'age' => $request->age,
            'location' => $request->location,
            'password' => Hash::make($request->password),
            'is_admin' => false,
            'active' => true,
        ]);

        // Log registration activity
        activity()
            ->causedBy($user)
            ->withProperties(['email' => $user->email])
            ->log('User Registered');

        // Log the user in
        Auth::login($user);

        // Redirect to the user dashboard with a success message
        return redirect()->route('user.dashboard')
                         ->with('success', 'Registrasi berhasil. Selamat datang!');
    }

    /**
     * Show the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle the login form submission.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Attempt to authenticate the user
        if (!Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            // Log failed login attempt
            activity()
                ->withProperties(['email' => $request->email])
                ->log('Failed Login Attempt');

            // Throw validation exception with error message
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi salah.'],
            ]);
        }

        // Retrieve the authenticated user
        $user = Auth::user();

        // Check if the user's account is active
        if (!$user->active) {
            // Log inactive user login attempt
            activity()
                ->causedBy($user)
                ->log('Inactive User Login Attempt');

            // Log the user out
            Auth::logout();

            // Redirect back with an error message
            return redirect()->back()
                             ->withErrors(['email' => 'Akun Anda dinonaktifkan.'])
                             ->withInput($request->only('email'));
        }

        // Regenerate the session to prevent session fixation
        $request->session()->regenerate();

        // Log successful login
        activity()
            ->causedBy($user)
            ->log('User Logged In');

        // Redirect to the intended page or user dashboard with a success message
        return redirect()->intended(route('user.dashboard'))
                         ->with('success', 'Login berhasil. Selamat datang kembali!');
    }

    /**
     * Handle the logout action.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        // Retrieve the authenticated user
        $user = Auth::user();

        // Log logout activity
        activity()
            ->causedBy($user)
            ->log('User Logged Out');

        // Log the user out
        Auth::logout();

        // Invalidate the session
        $request->session()->invalidate();

        // Regenerate the CSRF token
        $request->session()->regenerateToken();

        // Redirect to the login page with a success message
        return redirect()->route('login')
                         ->with('success', 'Logout berhasil.');
    }

      /**
     * Redirect the user to the social provider's authentication page.
     *
     * @param  string  $provider
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from the social provider.
     *
     * @param  string  $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            // Handle errors
            return redirect('/login')->withErrors('Unable to login using ' . $provider . '. Please try again.');
        }

        // Find or create a user
        $user = User::firstOrCreate(
            ['provider_id' => $socialUser->getId(), 'provider_name' => $provider],
            [
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                // Additional fields...
                'password' => Hash::make(Str::random(24)),
                'is_admin' => false,
                'active' => true,
            ]
        );

        // Log the user in
        Auth::login($user);

        // Redirect to dashboard
        return redirect()->route('user.dashboard')->with('success', 'Login successful!');
    }
}