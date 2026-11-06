@props(['title' => 'المخزن'])
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#087f5b"><title>{{ $title }} | مزارع شوقي</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-ios-background text-slate-950 antialiased">
<header class="border-b border-emerald-900/10 bg-white"><div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-5 py-4"><a href="{{ route('dashboard') }}" class="font-bold text-xl text-emerald-900">مزارع شوقي <span class="text-sm font-normal">/ المخزن</span></a>@if(session('store_unlocked'))<form action="{{ route('logout') }}" method="post">@csrf<button class="text-sm text-slate-500">قفل المخزن</button></form>@endif</div></header>
<main class="mx-auto flex max-w-5xl flex-col gap-5 px-4 pt-6 pb-28">
@if(session('status'))<p class="ios-status" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="rounded-2xl bg-red-50 p-4 text-red-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
{{ $slot }}</main>
@if(session('store_unlocked'))<nav class="ios-tab-bar"><div class="mx-auto grid max-w-lg grid-cols-5">@foreach(['overview'=>['◈','الرئيسية'],'medicines'=>['▦','الأدوية'],'purchases'=>['＋','إضافة وارد'],'movements'=>['⇄','الحركات'],'reports'=>['▥','التقارير']] as $key=>$item)<a href="{{ route('dashboard',['tab'=>$key]) }}" class="ios-tab {{ request('tab','overview') === $key ? 'text-ios-blue' : 'text-slate-500' }}"><span class="text-2xl" aria-hidden="true">{{ $item[0] }}</span><span>{{ $item[1] }}</span></a>@endforeach</div></nav>@endif
</body></html>