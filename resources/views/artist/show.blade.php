{{-- resources/views/artist/show.blade.php --}}
@extends('layouts.app')

@section('title', $artist->name . ' - Albums & Similar Artists')

@section('content')
<div class="container py-4">

  {{-- Header artis --}}
  <div class="row align-items-center mb-4">
    <div class="col-auto">
      @if(!empty($artist->image_url))
        <img src="{{ $artist->image_url }}" alt="{{ $artist->name }}" class="rounded" style="width:120px;height:120px;object-fit:cover;">
      @else
        <div class="bg-secondary rounded" style="width:120px;height:120px;"></div>
      @endif
    </div>
    <div class="col">
      <h1 class="h2 mb-2">{{ $artist->name }}</h1>
      <div class="text-muted mb-2">
        <i class="bi bi-people-fill"></i> {{ number_format($artist->followers ?? 0) }} followers
        @if(!empty($artist->popularity))
          • <i class="bi bi-bar-chart-fill"></i> Popularity: {{ $artist->popularity }}
        @endif
      </div>
      @php
        $genres = is_string($artist->genres) ? json_decode($artist->genres, true) : ($artist->genres ?? []);
      @endphp
      @if(!empty($genres))
        <div class="mb-3">
          @foreach(array_slice($genres, 0, 6) as $g)
            <span class="badge bg-secondary me-1">{{ $g }}</span>
          @endforeach
        </div>
      @endif
      @if(!empty($artist->spotify_url))
        <a class="btn btn-success" href="{{ $artist->spotify_url }}" target="_blank" rel="noopener">
          <i class="bi bi-spotify"></i> Open in Spotify
        </a>
      @endif
    </div>
  </div>

  {{-- Artist Bio Section --}}
  @if(!empty($artist->born_date) || !empty($artist->born_in) || !empty($artist->gender) || !empty($artist->country))
  <section class="mb-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">
          <i class="bi bi-person-badge"></i> Artist Information
        </h5>
        <div class="row g-3">
          @if(!empty($bornFormatted))
          <div class="col-md-6">
            <div class="d-flex align-items-center">
              <i class="bi bi-calendar-event text-primary me-2 fs-5"></i>
              <div>
                <small class="text-muted d-block">Born</small>
                <strong>{{ $bornFormatted }}</strong>
              </div>
            </div>
          </div>
          @endif

          @if(!empty($artist->born_in))
          <div class="col-md-6">
            <div class="d-flex align-items-center">
              <i class="bi bi-geo-alt text-danger me-2 fs-5"></i>
              <div>
                <small class="text-muted d-block">Birth Place</small>
                <strong>{{ $artist->born_in }}</strong>
              </div>
            </div>
          </div>
          @endif

          @if(!empty($artist->gender))
          <div class="col-md-6">
            <div class="d-flex align-items-center">
              <i class="bi bi-gender-ambiguous text-info me-2 fs-5"></i>
              <div>
                <small class="text-muted d-block">Gender</small>
                <strong>{{ ucfirst($artist->gender) }}</strong>
              </div>
            </div>
          </div>
          @endif

          @if(!empty($artist->country))
          <div class="col-md-6">
            <div class="d-flex align-items-center">
              <i class="bi bi-flag text-success me-2 fs-5"></i>
              <div>
                <small class="text-muted d-block">Country</small>
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

  {{-- Social Media Links --}}
  @if(!empty($socialLinks))
  <section class="mb-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">
          <i class="bi bi-share"></i> Social Media & Platforms
        </h5>
        <div class="d-flex flex-wrap gap-2">
          @foreach($socialLinks as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="btn btn-outline-{{ $link['color'] }} btn-sm">
              <i class="bi {{ $link['icon'] }}"></i> {{ $link['name'] }}
            </a>
          @endforeach
        </div>
      </div>
    </div>
  </section>
  @endif

  {{-- Description Section --}}
  @if(!empty($artist->description))
  <section class="mb-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">
          <i class="bi bi-info-circle"></i> About
        </h5>
        <p class="mb-0" style="line-height: 1.7;">{{ $artist->description }}</p>
      </div>
    </div>
  </section>
  @endif

  {{-- Similar Artists Section --}}
  @if(!empty($similar))
  <section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 mb-0">Similar Artists</h2>
      <span class="text-muted small">{{ count($similar) }} artists</span>
    </div>
    
    <div class="row g-3">
      @foreach($similar as $sa)
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
          <div class="card h-100 artist-card border-0 shadow-sm">
            <a href="{{ route('artist.show', ['slug' => $sa['slug'], 'spotifyId' => $sa['spotify_id']]) }}" class="text-decoration-none text-dark">
              @if(!empty($sa['image_url']))
                <img src="{{ $sa['image_url'] }}" class="card-img-top rounded-circle p-3" alt="{{ $sa['name'] }}" style="aspect-ratio:1/1;object-fit:cover;">
              @else
                <div class="bg-light rounded-circle m-3" style="aspect-ratio:1/1;"></div>
              @endif
              <div class="card-body p-2 text-center">
                <div class="fw-semibold mb-1 text-truncate" style="font-size:0.9rem;" title="{{ $sa['name'] }}">
                  {{ $sa['name'] }}
                </div>
                <div class="text-muted" style="font-size:0.75rem;">
                  <i class="bi bi-star-fill text-warning"></i> {{ $sa['popularity'] ?? 0 }}
                </div>
                @if(!empty($sa['genres']))
                  <div class="mt-1">
                    <span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ $sa['genres'][0] ?? 'Music' }}</span>
                  </div>
                @endif
              </div>
            </a>
            <div class="card-footer bg-white border-0 p-2">
              @if(!empty($sa['spotify_url']))
                <a href="{{ $sa['spotify_url'] }}" target="_blank" class="btn btn-sm btn-outline-success w-100" rel="noopener">
                  <i class="bi bi-spotify"></i> Spotify
                </a>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </section>
  @endif

  {{-- Top Tracks Section --}}
  @if(!empty($topTracks) && $topTracks->count())
  <section class="mb-5">
    <h2 class="h4 mb-3">Top Tracks</h2>
    <div class="list-group">
      @foreach($topTracks as $track)
        <div class="list-group-item list-group-item-action">
          <div class="d-flex align-items-center">
            <div class="me-3 text-muted fw-bold" style="min-width:30px;">
              #{{ $track->rank }}
            </div>
            @if($track->album_image_url)
              <img src="{{ $track->album_image_url }}" class="me-3 rounded" style="width:56px;height:56px;object-fit:cover;" alt="{{ $track->title }}">
            @endif
            <div class="flex-grow-1">
              <div class="fw-semibold">{{ $track->title }}</div>
              <small class="text-muted">
                {{ $track->album_name }}
                @if($track->duration_ms)
                  • {{ gmdate('i:s', $track->duration_ms / 1000) }}
                @endif
                @if($track->release_date)
                  • {{ substr($track->release_date, 0, 4) }}
                @endif
              </small>
            </div>
            <div class="text-end me-3">
              <div class="badge bg-warning text-dark">
                <i class="bi bi-star-fill"></i> {{ $track->popularity }}
              </div>
              @if($track->explicit)
                <span class="badge bg-secondary">E</span>
              @endif
            </div>
            @if($track->spotify_url)
              <a href="{{ $track->spotify_url }}" target="_blank" class="btn btn-sm btn-success" rel="noopener">
                <i class="bi bi-play-fill"></i>
              </a>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </section>
  @endif

  {{-- Albums Section --}}
  <section>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 mb-0">Albums & Singles</h2>
      @if(method_exists($albums, 'total'))
        <span class="text-muted small">{{ $albums->total() }} albums</span>
      @endif
    </div>

    @if(method_exists($albums, 'count') && $albums->count())
      <div class="row g-3">
        @foreach($albums as $al)
          <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card h-100 album-card border-0 shadow-sm">
              @if(!empty($al->image_url))
                <img src="{{ $al->image_url }}" class="card-img-top" alt="{{ $al->name }}" style="aspect-ratio:1/1;object-fit:cover;">
              @else
                <div class="bg-light" style="width:100%;aspect-ratio:1/1;"></div>
              @endif
              <div class="card-body p-2">
                <div class="fw-semibold mb-1 text-truncate" style="font-size:0.9rem;" title="{{ $al->name }}">
                  {{ $al->name }}
                </div>
                <div class="text-muted" style="font-size:0.75rem;">
                  {{ ucfirst($al->album_type ?? 'album') }}
                  @if(!empty($al->release_date))
                    • {{ substr($al->release_date, 0, 4) }}
                  @endif
                </div>
                @if(isset($al->total_tracks))
                  <div class="text-muted" style="font-size:0.7rem;">
                    <i class="bi bi-music-note-list"></i> {{ $al->total_tracks }} tracks
                  </div>
                @endif
              </div>
              @if(!empty($al->spotify_url))
                <div class="card-footer bg-white border-0 p-2">
                  <a href="{{ $al->spotify_url }}" target="_blank" class="btn btn-sm btn-outline-success w-100" rel="noopener">
                    <i class="bi bi-play-fill"></i> Play
                  </a>
                </div>
              @endif
            </div>
          </div>
        @endforeach
      </div>

      {{-- Pagination --}}
      @if(method_exists($albums, 'links'))
        <div class="mt-4">
          {{ $albums->links() }}
        </div>
      @endif
    @else
      <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Belum ada album tersedia untuk artis ini.
      </div>
    @endif
  </section>

</div>

@push('styles')
<style>
  .artist-card, .album-card {
    transition: all 0.3s ease;
  }
  
  .artist-card:hover, .album-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
  }
  
  .artist-card .card-img-top {
    transition: transform 0.3s ease;
  }
  
  .artist-card:hover .card-img-top {
    transform: scale(1.08);
  }
  
  .album-card img {
    transition: opacity 0.3s ease;
  }
  
  .album-card:hover img {
    opacity: 0.9;
  }
</style>
@endpush
@endsection
