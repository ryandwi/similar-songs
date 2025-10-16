{{-- resources/views/artist/show.blade.php --}}
@extends('layouts.app')

@section('title', $artist->name . ' - Similar Artists')

@section('content')
    <div class="container py-4">
        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb " class="mb-3" style="background-color: #303136">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-decoration-none" href="{{ url('/') }}">
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a class="text-decoration-none" href="{{ route('artist.index') }}">Similar Artists</a>
                </li>
                <li class="breadcrumb-item text-white-50" aria-current="page">
                    <strong>{{ $artist->name }}</strong>
                </li>
            </ol>
        </nav>
        {{-- Header artis --}}
        <div class="row align-items-center mb-5 rounded">
            {{-- Artist Image --}}
            <div class="col-12 col-md-auto text-center text-md-start mb-3 mb-md-0">
                @if (!empty($artist->image_url))
                    <img src="{{ $artist->image_url }}" alt="{{ $artist->name }}" class="img-fluid rounded"
                        style="width:100%; max-width:300px; height:auto; aspect-ratio:1/1; object-fit:cover;">
                @else
                    <div class="bg-secondary mx-auto mx-md-0 rounded"
                        style="width:100%; max-width:200px; aspect-ratio:1/1;"></div>
                @endif
            </div>

            {{-- Artist Info --}}
            <div class="col-12 col-md">
                <h1 class="mb-2 text-center text-md-start">
                    Similar Artists to
                    <a class="text-decoration-none d-block d-sm-inline" href="">
                        {{ $artist->name }}
                    </a>
                </h1>

                {{-- Followers & Popularity --}}
                <div class="mb-2 text-center text-white-50 text-md-start">
                    <i class="bi bi-people-fill"></i>
                    <span class="fw-semibold">{{ number_format($artist->followers ?? 0) }}</span> followers
                    @if (!empty($artist->popularity))
                        <span class="d-none d-sm-inline">•</span>
                        <span class="d-block d-sm-inline">
                            <i class="bi bi-bar-chart-fill"></i>
                            <span class="fw-semibold">{{ $artist->popularity }}</span> Popularity
                        </span>
                    @endif
                </div>

                {{-- Genres --}}
                @php
                    $genres = is_string($artist->genres) ? json_decode($artist->genres, true) : $artist->genres ?? [];
                @endphp
                @if (!empty($genres))
                    <div class="mb-3">
                        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                            @foreach (array_slice($genres, 0, 6) as $g)
                                <a class="text-decoration-none" href="#">
                                    <span class="badge bg-light text-dark border px-3 py-2" style="font-size:0.85rem;">
                                        {{ ucwords(str_replace('-', ' ', $g)) }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Social Links (Optional) --}}
                @if (!empty($socialLinks))
                    <div class="d-flex gap-2 justify-content-center justify-content-md-start flex-wrap">
                        @foreach ($socialLinks as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                class="btn btn-sm btn-outline-{{ $link['color'] }}" title="{{ $link['name'] }}">
                                <i class="bi {{ $link['icon'] }}"></i>
                                <span class="d-none d-lg-inline ms-1">{{ $link['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>


        {{-- Artist Bio Section --}}
        @if (!empty($artist->born_date) || !empty($artist->born_in) || !empty($artist->gender) || !empty($artist->country))
            <section class="mb-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-person-badge"></i> Artist Information
                        </h5>
                        <div class="row g-3">
                            @if (!empty($bornFormatted))
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar-event text-primary me-2 fs-5"></i>
                                        <div>
                                            <small class="d-block">Born</small>
                                            <strong>{{ $bornFormatted }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($artist->born_in))
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-geo-alt text-danger me-2 fs-5"></i>
                                        <div>
                                            <small class="d-block">Birth Place</small>
                                            <strong>{{ $artist->born_in }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($artist->gender))
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-gender-ambiguous text-info me-2 fs-5"></i>
                                        <div>
                                            <small class="d-block">Gender</small>
                                            <strong>{{ ucfirst($artist->gender) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($artist->country))
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-flag text-success me-2 fs-5"></i>
                                        <div>
                                            <small class="d-block">Country</small>
                                            <strong>{{ strtoupper($artist->country) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif
        {{-- Similar Artists Section --}}
        @if (!empty($similar))
            <section class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="">
                        <h2>Artists Similar to {{ $artist->name }}</h2>
                        <div class="">{{ $seoText }}</div>
                    </div>
                </div>

                <div class="row g-3">
                    @foreach ($similar as $sa)
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                            <div class="card h-100 artist-card border-0 bg-transparent">
                                <a href="{{ route('artist.show', ['slug' => $sa['slug'], 'spotifyId' => $sa['spotify_id']]) }}"
                                    class="text-decoration-none text-dark">
                                    @if (!empty($sa['image_url']))
                                        <img src="{{ $sa['image_url'] }}" class="card-img-top rounded-circle p-3"
                                            alt="{{ $sa['name'] }}" style="aspect-ratio:1/1;object-fit:cover;">
                                    @else
                                        <div class="bg-light rounded-circle m-3" style="aspect-ratio:1/1;"></div>
                                    @endif
                                    <div class="card-body p-2 text-center">
                                        <div class="fw-semibold mb-1 text-truncate text-white" style="font-size:0.9rem;"
                                            title="{{ $sa['name'] }}">
                                            {{ $sa['name'] }}
                                        </div>
                                        <div class="text-white-50" style="font-size:0.75rem;">
                                            <i class="bi bi-star-fill text-warning"></i>
                                            {{ number_format($sa['followers']) ?? 0 }} Followers
                                        </div>
                                        <div class="text-white-50" style="font-size:0.75rem;">
                                            <i class="bi bi-bar-chart-fill text-warning"></i> {{ $sa['popularity'] ?? 0 }}%
                                            Similarity
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Top Tracks Section --}}
        @if (!empty($topTracks) && $topTracks->count())
            <section class="mb-5">
                <h2 class="text-white">Top Tracks</h2>
                <div class="mb-3 text-white">{{ $topTrackText }}</div>
                <ul class="list-group bg-dark rounded-3">
                    @foreach ($topTracks as $track)
                        <li
                            class="list-group-item d-flex flex-column flex-sm-row align-items-sm-center gap-3 gap-sm-2 bg-dark text-white border-secondary">
                            @if ($track->album_image_url)
                                <img src="{{ $track->album_image_url }}" alt="{{ $track->title }}"
                                    class="rounded flex-shrink-0" style="width:48px; height:48px; object-fit:cover;">
                            @endif
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-semibold text-truncate d-flex align-items-center gap-1">
                                    <span>{{ $track->title }}</span>
                                </div>
                                <small class="text-white-50 d-block text-truncate" style="max-width: 100%;">
                                    {{ $track->album_name }} •
                                    {{ gmdate('i:s', $track->duration_ms / 1000) }} •
                                    {{ substr($track->release_date ?? '', 0, 4) }}

                                </small>
                            </div>
                            <span class="fw-bold text-white flex-shrink-0">#{{ $track->rank }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif


        @if (method_exists($albums, 'count') && $albums->count())
            <section>
                <div class="d-flex flex-column justify-content-between mb-5">
                    <h2 class="mb-0">Albums & Singles</h2>
                    <div class="">{{ $albumText }}</div>
                </div>
                <div class="row g-3">
                    @foreach ($albums as $al)
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                            <div class="card h-100 album-card border-0 bg-transparent">
                                @if (!empty($al->image_url))
                                    <img src="{{ $al->image_url }}" class="card-img-top" alt="{{ $al->name }}"
                                        style="aspect-ratio:1/1;object-fit:cover;">
                                @else
                                    <div class="bg-transparent" style="width:100%;aspect-ratio:1/1;"></div>
                                @endif
                                <div class="card-body p-2">
                                    <div class="fw-semibold mb-1 text-truncate text-white" style="font-size:0.9rem;"
                                        title="{{ $al->name }}">
                                        {{ $al->name }}
                                    </div>
                                    <div class="text-white-50" style="font-size:0.75rem;">
                                        {{ ucfirst($al->album_type ?? 'album') }}
                                        @if (!empty($al->release_date))
                                            • {{ substr($al->release_date, 0, 4) }}
                                        @endif
                                    </div>
                                    @if (isset($al->total_tracks))
                                        <div class="text-white-50" style="font-size:0.7rem;">
                                            <i class="bi bi-music-note-list"></i> {{ $al->total_tracks }} tracks
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif



    </div>
@endsection
