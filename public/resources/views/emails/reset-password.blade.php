<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Baru - PresensiApp</title>
    <style>
        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f0f4f8;
            color: #2d3748;
        }
        .wrapper {
            max-width: 560px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #1a56db 0%, #0e3a8a 100%);
            padding: 36px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 6px 0 0;
            color: rgba(255,255,255,0.8);
            font-size: 13px;
        }
        .body {
            padding: 36px 40px;
        }
        .greeting {
            font-size: 15px;
            margin-bottom: 16px;
        }
        .info-text {
            font-size: 14px;
            line-height: 1.7;
            color: #4a5568;
            margin-bottom: 24px;
        }
        .password-box {
            background: #f7fafc;
            border: 2px dashed #1a56db;
            border-radius: 10px;
            padding: 20px 24px;
            text-align: center;
            margin: 24px 0;
        }
        .password-box .label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .password-box .password {
            font-size: 28px;
            font-weight: 800;
            color: #1a56db;
            letter-spacing: 6px;
            font-family: 'Courier New', monospace;
        }
        .warning-box {
            background: #fffbeb;
            border-left: 4px solid #f6ad55;
            border-radius: 0 8px 8px 0;
            padding: 14px 16px;
            margin: 20px 0;
            font-size: 13px;
            color: #744210;
        }
        .warning-box strong {
            display: block;
            margin-bottom: 4px;
        }
        .steps {
            margin: 20px 0;
            padding: 0;
        }
        .steps li {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 8px;
            padding-left: 8px;
        }
        .footer {
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 40px;
            text-align: center;
        }
        .footer p {
            margin: 0;
            font-size: 12px;
            color: #a0aec0;
            line-height: 1.6;
        }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        {{-- Header --}}
        <div class="header">
            <h1>🔑 Reset Password</h1>
            <p>PresensiApp — Sistem Absensi Karyawan</p>
        </div>

        {{-- Body --}}
        <div class="body">
            <p class="greeting">
                Halo{{ $userName ? ', <strong>' . e($userName) . '</strong>' : '' }}! 👋
            </p>

            <p class="info-text">
                Kami menerima permintaan reset password untuk akun Anda.
                Berikut adalah <strong>password baru</strong> yang telah dibuat secara otomatis oleh sistem:
            </p>

            {{-- Password Box --}}
            <div class="password-box">
                <div class="label">Password Baru Anda</div>
                <div class="password">{{ $newPassword }}</div>
            </div>

            {{-- Warning --}}
            <div class="warning-box">
                <strong>⚠️ Penting: Segera Ganti Password!</strong>
                Demi keamanan akun Anda, harap ganti password ini setelah berhasil login.
                Jangan bagikan password ini kepada siapapun, termasuk tim IT.
            </div>

            <hr class="divider">

            <p style="font-size:14px; color:#4a5568; margin-bottom:10px;"><strong>Langkah selanjutnya:</strong></p>
            <ol class="steps">
                <li>Buka aplikasi <strong>PresensiApp</strong> di smartphone Anda</li>
                <li>Masukkan Email dan Password baru di atas</li>
                <li>Setelah login berhasil, segera ubah password melalui menu Profil</li>
            </ol>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>
                Email ini dikirim secara otomatis oleh sistem PresensiApp.<br>
                Jika Anda tidak merasa meminta reset password, abaikan email ini.<br>
                Password lama Anda sudah tidak berlaku.
            </p>
            <p style="margin-top: 10px;">
                © {{ date('Y') }} PresensiApp · Jangan balas email ini
            </p>
        </div>
    </div>
</body>
</html>
