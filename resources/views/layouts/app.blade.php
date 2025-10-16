{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Music App')</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">
  @stack('styles')
</head>
<body>
  <nav class="navbar navbar-expand-lg shadow-sm" style="background-color: #303136">
    <div class="container">
      <a class="navbar-brand text-white" href="/">MusicApp</a>
    </div>
  </nav>

  <main class="py-4">
    @yield('content')
  </main>

  <footer class="text-center text-white py-3 mt-auto" style="background-color: #303136">
    <div class="container">
      <p class="mb-0">© {{ date('Y') }} MusicApp.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
