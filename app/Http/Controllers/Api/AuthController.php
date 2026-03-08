<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Helper: Generate OTP & kirim ke email tertentu (bisa beda dari email user)
     */
    private function generateAndSendOtpToEmail(User $user, string $targetEmail, string $type = 'register'): void
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'otp_code'       => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        Mail::to($targetEmail)->queue(new OtpMail($otp, $user->name, $type));
    }

    /**
     * Helper lama (untuk register) - tetap ada, sekarang memanggil helper baru
     */
    private function generateAndSendOtp(User $user): void
    {
        $this->generateAndSendOtpToEmail($user, $user->email, 'register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'      => ['required', 'confirmed', Password::min(8)],
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'no_hp'         => ['required', 'string', 'max:20'],
            'instansi'      => ['required', 'string', 'max:255'],
            'foto'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in' => 'Jenis kelamin tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'instansi.required' => 'Instansi wajib diisi.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
        ]);

        // Upload foto jika ada
        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('foto-profil', 'public');
        }

        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'no_hp'         => $validated['no_hp'],
            'instansi'      => $validated['instansi'],
            'foto'          => $fotoPath,
            'is_verified'   => 0,
        ]);

        // Kirim OTP ke email
        $this->generateAndSendOtp($user);

        return response()->json([
            'message' => 'Registrasi berhasil. Kode OTP telah dikirim ke email kamu.',
            'email'   => $user->email, // untuk ditampilkan di form verifikasi React
        ], 201);
    }

    /**
     * Verifikasi OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Akun sudah terverifikasi.'], 400);
        }

        // Cek apakah OTP sudah expired
        if (!$user->otp_expires_at || Carbon::now()->isAfter($user->otp_expires_at)) {
            return response()->json(['message' => 'Kode OTP sudah kadaluarsa. Silakan minta kode baru.'], 422);
        }

        // Cek kecocokan OTP
        if ($user->otp_code !== $request->otp_code) {
            return response()->json(['message' => 'Kode OTP tidak valid.'], 422);
        }

        // OTP valid — tandai akun sebagai terverifikasi
        $user->update([
            'is_verified'   => 1,
            'otp_code'      => null,
            'otp_expires_at' => null,
        ]);

        // Langsung berikan token setelah verifikasi
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'    => 'Email berhasil diverifikasi.',
            'user'       => $user->fresh(),
            'token'      => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Kirim ulang OTP
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Akun sudah terverifikasi.'], 400);
        }

        // Cooldown: cegah spam, hanya boleh kirim ulang jika OTP sudah > 1 menit
        if ($user->otp_expires_at && Carbon::now()->isBefore(
            Carbon::parse($user->otp_expires_at)->subMinutes(4)
        )) {
            return response()->json([
                'message' => 'Tunggu 1 menit sebelum meminta kode baru.',
            ], 429);
        }

        $this->generateAndSendOtp($user);

        return response()->json([
            'message' => 'Kode OTP baru telah dikirim ke email kamu.',
        ]);
    }

    /**
     * Batalkan perubahan email
     */
    public function cancelEmailChange(Request $request)
    {
        $user = $request->user();

        $user->update([
            'pending_email'  => null,
            'otp_code'       => null,
            'otp_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Permintaan perubahan email dibatalkan.',
            'user'    => $user->fresh(),
        ]);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $user = Auth::user();

        // Cek verifikasi
        if (!$user->is_verified) {
            // Kirim ulang OTP otomatis
            $this->generateAndSendOtp($user);
            Auth::logout();

            return response()->json([
                'message'           => 'Akun belum diverifikasi. Kode OTP telah dikirim ulang ke email kamu.',
                'email'             => $user->email,
                'requires_verification' => true,
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'    => 'Login berhasil.',
            'user'       => $user,
            'token'      => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Data user yang sedang login
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Update profil user
     * Jika email berubah → simpan ke pending_email & kirim OTP ke email baru
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'jenis_kelamin' => ['sometimes', 'in:Laki-laki,Perempuan'],
            'no_hp'         => ['sometimes', 'string', 'max:20'],
            'instansi'      => ['sometimes', 'string', 'max:255'],
            'foto'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'email'         => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        // Proses upload foto jika ada
        if ($request->hasFile('foto')) {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }
            $validated['foto'] = $request->file('foto')->store('foto-profil', 'public');
        } elseif ($request->input('foto') === null && array_key_exists('foto', $validated)) {
            // User sengaja hapus foto
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }
            $validated['foto'] = null;
        } else {
            // Tidak ada perubahan foto, keluarkan dari array update
            unset($validated['foto']);
        }

        $emailBerubah = isset($validated['email']) && $validated['email'] !== $user->email;

        if ($emailBerubah) {
            $emailBaru = $validated['email'];
            unset($validated['email']); // jangan langsung update email utama

            // Simpan email baru ke pending_email
            $user->update(array_merge($validated, ['pending_email' => $emailBaru]));

            // Generate OTP & kirim ke EMAIL BARU
            $this->generateAndSendOtpToEmail($user, $emailBaru, 'email_change');

            return response()->json([
                'message'       => 'Profil diperbarui. Kode OTP telah dikirim ke email baru kamu untuk konfirmasi.',
                'pending_email' => $emailBaru,
                'email_changed' => true,
            ]);
        }

        // Tidak ada perubahan email → update langsung
        $user->update($validated);

        return response()->json([
            'message'      => 'Profil berhasil diperbarui.',
            'user'         => $user->fresh(),
            'email_changed' => false,
        ]);
    }

    /**
     * Verifikasi OTP untuk konfirmasi email baru
     */
    public function verifyEmailChange(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ]);

        if (!$user->pending_email) {
            return response()->json([
                'message' => 'Tidak ada permintaan perubahan email.',
            ], 400);
        }

        // Cek expired
        if (!$user->otp_expires_at || Carbon::now()->isAfter($user->otp_expires_at)) {
            return response()->json([
                'message' => 'Kode OTP sudah kadaluarsa. Silakan minta kode baru.',
            ], 422);
        }

        // Cek kecocokan OTP
        if ($user->otp_code !== $request->otp_code) {
            return response()->json([
                'message' => 'Kode OTP tidak valid.',
            ], 422);
        }

        // OTP valid → pindahkan pending_email ke email utama
        $user->update([
            'email'          => $user->pending_email,
            'pending_email'  => null,
            'otp_code'       => null,
            'otp_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Email berhasil diperbarui.',
            'user'    => $user->fresh(),
        ]);
    }

    /**
     * Kirim ulang OTP untuk konfirmasi email baru
     */
    public function resendEmailChangeOtp(Request $request)
    {
        $user = $request->user();

        if (!$user->pending_email) {
            return response()->json([
                'message' => 'Tidak ada permintaan perubahan email.',
            ], 400);
        }

        // Cooldown: cegah spam
        if ($user->otp_expires_at && Carbon::now()->isBefore(
            Carbon::parse($user->otp_expires_at)->subMinutes(4)
        )) {
            return response()->json([
                'message' => 'Tunggu 1 menit sebelum meminta kode baru.',
            ], 429);
        }

        $this->generateAndSendOtpToEmail($user, $user->pending_email, 'email_change');

        return response()->json([
            'message' => 'Kode OTP baru telah dikirim ke ' . $user->pending_email,
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
