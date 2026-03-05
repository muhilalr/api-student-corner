<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 480px;
      margin: 40px auto;
      background: #fff;
      border-radius: 8px;
      padding: 32px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .otp-code {
      font-size: 36px;
      font-weight: bold;
      letter-spacing: 10px;
      color: #4F46E5;
      text-align: center;
      margin: 24px 0;
      background: #f0f0ff;
      padding: 16px;
      border-radius: 8px;
    }

    .badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 99px;
      font-size: 12px;
      font-weight: bold;
    }

    .badge-register {
      background: #dcfce7;
      color: #166534;
    }

    .badge-change {
      background: #fef9c3;
      color: #854d0e;
    }

    .footer {
      font-size: 12px;
      color: #999;
      text-align: center;
      margin-top: 24px;
    }
  </style>
</head>

<body>
  <div class="container">
    @if ($type === 'email_change')
      <span class="badge badge-change">Perubahan Email</span>
      <h2>Halo, {{ $userName }}!</h2>
      <p>Kamu baru saja meminta perubahan email akun. Gunakan kode OTP berikut untuk mengonfirmasi email baru kamu:</p>
    @else
      <span class="badge badge-register">Verifikasi Akun</span>
      <h2>Selamat datang, {{ $userName }}!</h2>
      <p>Terima kasih sudah mendaftar. Gunakan kode OTP berikut untuk memverifikasi akun kamu:</p>
    @endif

    <div class="otp-code">{{ $otpCode }}</div>

    <p>Kode ini berlaku selama <strong>5 menit</strong>.</p>

    @if ($type === 'email_change')
      <p>⚠️ Jika kamu tidak merasa melakukan perubahan email, segera amankan akun kamu.</p>
    @else
      <p>Jika kamu tidak merasa mendaftar, abaikan email ini.</p>
    @endif

    <div class="footer">
      &copy; {{ date('Y') }} {{ config('app.name') }}
    </div>
  </div>
</body>

</html>
