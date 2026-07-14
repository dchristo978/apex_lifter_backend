<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { margin: 0; padding: 24px; color: #111; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .subtitle { color: #666; font-size: 12px; margin-bottom: 16px; }
        .grid { width: 100%; }
        .card {
            display: inline-block;
            width: 32%;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            margin: 0 0.4% 10px;
            text-align: center;
            vertical-align: top;
            page-break-inside: avoid;
        }
        .card .qr { width: 120px; height: 120px; margin: 0 auto; }
        .card .qr svg { width: 120px; height: 120px; }
        .name { font-size: 12px; font-weight: bold; margin-top: 6px; }
        .meta { font-size: 10px; color: #777; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <h1>{{ $gym->name }} — QR Alat</h1>
    <div class="subtitle">{{ $gym->address }} · Scan untuk check-in & lihat cara pakai alat</div>

    <div class="grid">
        @foreach ($machines as $machine)
            <div class="card">
                <div class="qr">{!! $machine['svg'] !!}</div>
                <div class="name">{{ $machine['name'] }}</div>
                <div class="meta">{{ $machine['category'] }} · {{ $machine['brand'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
