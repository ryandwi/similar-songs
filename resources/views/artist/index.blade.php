@extends('layouts.app')

@section('title', 'Discover Artists - Browse Music Artists & Similar Recommendations')

@section('head')
<meta name="description" content="Explore thousands of music artists across all genres. Find similar artists, discover new music, and browse complete artist profiles with albums, tracks, and biographical information.">
@endsection

@section('content')
<div class="container py-4">

    {{-- Hero Section --}}
    <div class="row mb-4">
        <div class="col-lg-8 mx-auto text-center">
            <h1 class="display-5 fw-bold mb-3">Discover Music Artists</h1>
            <p class="lead text-muted">
                Browse {{ number_format($totalArtists) }} artists with {{ number_format($totalFollowers) }} total followers.
                Find similar artists and explore new music across all genres.
            </p>
        </div>
    </div>

    {{-- Search & Filter Card --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('artist.index') }}" class="row g-3">

                {{-- Search Input --}}
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small fw-semibold mb-1">Search Artist</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Search by name..."
                               value="{{ $search }}">
                    </div>
                </div>

                {{-- Genre Filter --}}
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold mb-1">Genre</label>
                    <select name="genre" class="form-select">
                        <option value="">All Genres</option>
                        @foreach($allGenres->take(100) as $g)
                            <option value="{{ $g }}" {{ $genre === $g ? 'selected' : '' }}>
                                {{ ucwords(str_replace('-', ' ', $g)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sort --}}
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-semibold mb-1">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="popularity" {{ $sort === 'popularity' ? 'selected' : '' }}>Most Popular</option>
                        <option value="followers" {{ $sort === 'followers' ? 'selected' : '' }}>Most Followers</option>
                        <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Name A-Z</option>
                        <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
                        <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
                    </select>
                </div>

                {{-- Per Page --}}
                <div class="col-lg-1 col-md-6">
                    <label class="form-label small fw-semibold mb-1">Show</label>
                    <select name="per_page" class="form-select">
                        <option value="24" {{ $perPage == 24 ? 'selected' : '' }}>24</option>
                        <option value="48" {{ $perPage == 48 ? 'selected' : '' }}>48</option>
                        <option value="96" {{ $perPage == 96 ? 'selected' : '' }}>96</option>
                    </select>
                </div>

                {{-- Buttons --}}
                <div class="col-lg-2 col-md-12">
                    <label class="form-label small fw-semibold mb-1 d-none d-lg-block">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-funnel"></i> Apply
                        </button>
                        @if(!empty($search) || !empty($genre) || $sort !== 'popularity')
                        <a href="{{ route('artist.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Results Info --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted">
            Showing <strong>{{ $artists->firstItem() ?? 0 }}</strong> to
            <strong>{{ $artists->lastItem() ?? 0 }}</strong> of
            <strong>{{ number_format($artists->total()) }}</strong> artists
        </div>

        @if(!empty($search))
        <div class="text-muted small">
            <i class="bi bi-info-circle"></i> Searching for: <strong>"{{ $search }}"</strong>
        </div>
        @endif
    </div>

    {{-- Artists Grid --}}
    <div class="row g-3 mb-4">
        @forelse($artists as $artist)
        @php
            $artistSlug = Str::slug($artist->name, '-');
            $genres = is_string($artist->genres) ? json_decode($artist->genres, true) : [];
        @endphp
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card h-100 border-0 shadow-sm artist-card">
                <a href="{{ route('artist.show', ['slug' => $artistSlug, 'spotifyId' => $artist->spotify_id]) }}"
                   class="text-decoration-none text-dark">

                    {{-- Artist Image --}}
                    @if(!empty($artist->image_url))
                    <img src="{{ $artist->image_url }}"
                         class="card-img-top rounded-circle p-3"
                         alt="{{ $artist->name }}"
                         loading="lazy"
                         style="aspect-ratio:1/1;object-fit:cover;">
                    @else
                    <div class="bg-light rounded-circle m-3 d-flex align-items-center justify-content-center"
                         style="aspect-ratio:1/1;">
                        <i class="bi bi-person-circle text-secondary" style="font-size:3rem;"></i>
                    </div>
                    @endif

                    {{-- Artist Info --}}
                    <div class="card-body p-2 text-center">
                        <h6 class="fw-semibold mb-1 text-truncate"
                            style="font-size:0.9rem;"
                            title="{{ $artist->name }}">
                            {{ $artist->name }}
                        </h6>

                        {{-- Genre Badge --}}
                        @if(!empty($genres) && isset($genres[0]))
                        <div class="mb-1">
                            <span class="badge bg-light text-dark border" style="font-size:0.7rem;">
                                {{ ucwords($genres[0]) }}
                            </span>
                        </div>
                        @endif

                        {{-- Stats --}}
                        <div class="small text-muted">
                            @if(!empty($artist->followers))
                            <div style="font-size:0.75rem;">
                                <i class="bi bi-people-fill"></i> {{ number_format($artist->followers) }}
                            </div>
                            @endif

                            @if(!empty($artist->popularity))
                            <div style="font-size:0.75rem;">
                                <i class="bi bi-bar-chart-fill text-warning"></i> {{ $artist->popularity }}%
                            </div>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                <h5>No Artists Found</h5>
                <p class="mb-3">We couldn't find any artists matching your criteria.</p>
                <a href="{{ route('artist.index') }}" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Reset Filters
                </a>
            </div>
        </div>
        @endforelse
    </div>
</div>


@endsection
