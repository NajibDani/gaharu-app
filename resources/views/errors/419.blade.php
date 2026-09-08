<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="2;url={{ route('login') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Telah Berakhir - Mengalihkan ke Login...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .card-expired {
            background: #ffffff;
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.06);
            max-width: 440px;
            width: 100%;
            text-align: center;
            padding: 40px 30px;
        }
        .icon-badge {
            width: 70px;
            height: 70px;
            background: #fff7ed;
            color: #ea580c;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 20px;
            border: 2px solid #fed7aa;
        }
    </style>
</head>
<body>
    <div class="card-expired">
        <div class="icon-badge">
            <i class="bi bi-clock-history"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">Sesi Telah Kedaluwarsa</h4>
        <p class="text-muted small mb-4">
            Halaman web telah lama tidak digunakan sehingga masa aktif sesi berakhir. Anda sedang dialihkan kembali ke menu login...
        </p>
        <div class="spinner-border text-warning mb-4" role="status" style="width: 2rem; height: 2rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div>
            <a href="{{ route('login') }}" class="btn btn-warning fw-semibold px-4 py-2 rounded-pill shadow-sm" style="background:#ea580c; border-color:#ea580c; color:white;">
                <i class="bi bi-box-arrow-in-right me-1"></i> Ke Menu Login Sekarang
            </a>
        </div>
    </div>

    <script>
        setTimeout(function() {
            window.location.href = "{{ route('login') }}";
        }, 1200);
    </script>
</body>
</html>