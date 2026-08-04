<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'Control') · CounterPOS</title>
    <style>
        :root{color-scheme:dark;--bg:#08111f;--panel:#101d31;--line:#26364f;--muted:#91a1b9;--text:#edf3fb;--accent:#35c8a0;--danger:#ff6b79;--warn:#f2bb52}*{box-sizing:border-box}body{margin:0;background:linear-gradient(135deg,#08111f,#10182a);font:14px/1.5 system-ui,sans-serif;color:var(--text)}a{color:inherit}.shell{display:grid;grid-template-columns:230px 1fr;min-height:100vh}.side{padding:28px 20px;border-right:1px solid var(--line);background:#091322}.brand{font-size:20px;font-weight:800;margin-bottom:30px}.brand span{color:var(--accent)}nav a{display:block;text-decoration:none;padding:11px 13px;margin:5px 0;border-radius:9px;color:var(--muted)}nav a:hover{background:#15243a;color:#fff}.content{padding:32px;max-width:1440px;width:100%}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.top h1{margin:0;font-size:25px}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.card{background:rgba(16,29,49,.94);border:1px solid var(--line);border-radius:13px;padding:18px;margin-bottom:18px}.metric{font-size:28px;font-weight:800;margin-top:6px}.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.between{justify-content:space-between}.btn{display:inline-block;border:0;border-radius:8px;padding:9px 14px;background:var(--accent);color:#04140f;font-weight:700;text-decoration:none;cursor:pointer}.btn.secondary{background:#243650;color:#fff}.btn.danger{background:var(--danger);color:#25070b}.btn.warn{background:var(--warn);color:#261900}input,select,textarea{width:100%;padding:10px 11px;border:1px solid var(--line);border-radius:8px;background:#091524;color:#fff}textarea{min-height:85px}label{display:block;color:var(--muted);margin-bottom:5px}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field.full{grid-column:1/-1}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:11px;border-bottom:1px solid var(--line);vertical-align:top}th{color:var(--muted);font-size:12px;text-transform:uppercase}.badge{display:inline-block;padding:3px 8px;border-radius:99px;background:#243650}.badge.active,.badge.verified{background:#123d34;color:#72e6c5}.badge.suspended,.badge.failed{background:#481d28;color:#ff9dab}.flash,.errors{padding:12px 15px;border-radius:9px;margin-bottom:18px}.flash{background:#113b32;border:1px solid #236b59}.errors{background:#411b26;border:1px solid #793044}.section-title{margin:0 0 14px;font-size:17px}.code{font-family:ui-monospace,monospace}.login{max-width:420px;margin:10vh auto;padding:26px}.login .field{margin:14px 0}.pagination svg{width:18px}@media(max-width:900px){.shell{grid-template-columns:1fr}.side{border-right:0;border-bottom:1px solid var(--line)}.grid{grid-template-columns:repeat(2,1fr)}.fields{grid-template-columns:1fr}.content{padding:20px}}
    </style>
</head>
<body>
@auth('control')
<div class="shell">
    <aside class="side">
        <div class="brand">Counter<span>POS</span> Control</div>
        <nav>
            <a href="{{ route('control.dashboard') }}">Overview</a>
            <a href="{{ route('control.tenants.index') }}">Customers</a>
            <a href="{{ route('control.plans.index') }}">Plans</a>
        </nav>
    </aside>
    <main class="content">
        <header class="top"><h1>@yield('heading', 'Control')</h1><form method="post" action="{{ route('control.logout') }}">@csrf<button class="btn secondary">Sign out</button></form></header>
        @include('control.partials.messages')
        @yield('content')
    </main>
</div>
@else
    @yield('guest-content')
@endauth
</body>
</html>
