{{-- resources/views/artist/show.blade.php --}}
@extends('layouts.app')

@section('title', $artist->name . ' - Albums & Similar Artists')

@section('content')
<div class="container py-4">

  {{-- Header artis --}}
  <div class="row align-items-center mb-4 bg-white p-4">
    <div class="col-auto">
      @if(!empty($artist->image_url))
        <img src="{{ $artist->image_url }}" alt="{{ $artist->name }}" style="width:300px;height:300px;object-fit:cover;">
      @else
        <div class="bg-secondary" style="width:120px;height:120px;"></div>
      @endif
    </div>
    <div class="col">
      <h1 class="h2 mb-2">Similar Artists to <a class="text-decoration-none" href="">{{ $artist->name }}</a></h1>
      <div class="text-muted mb-2">
        <i class="bi bi-people-fill"></i> {{ number_format($artist->followers ?? 0) }} followers
        @if(!empty($artist->popularity))
          • <i class="bi bi-bar-chart-fill"></i> {{ $artist->popularity }} Popularity
        @endif
      </div>
      @php
        $genres = is_string($artist->genres) ? json_decode($artist->genres, true) : ($artist->genres ?? []);
      @endphp
      @if(!empty($genres))
        <div class="mb-3">
          Genres: 
          @foreach(array_slice($genres, 0, 6) as $g)
            <a class=" text-decoration-none rounded" href=""><span class="border p-2">{{ $g }}</span></a>
          @endforeach
        </div>
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
  {{-- Similar Artists Section --}}
  @if(!empty($similar))
  <section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="">
        <h2 class="h4 mb-0">Artists Similar to {{ $artist->name }}</h2>
        <div class="">{{ $seoText }}</div>
      </div>
    </div>
    
    <div class="row g-3">
      @foreach($similar as $sa)
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
          <div class="card h-100 artist-card border-0 bg-transparent">
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
                  <i class="bi bi-star-fill text-warning"></i> {{ number_format($sa['followers']) ?? 0 }}% Followers
                </div>
                <div class="text-muted" style="font-size:0.75rem;">
                  <i class="bi bi-bar-chart-fill text-warning"></i> {{ $sa['popularity'] ?? 0 }}% Similarity
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
 @if(!empty($topTracks) && $topTracks->count())
<section class="mb-5">
  <h2 class="h4 mb-3">Top Tracks</h2>
  <div class="table-responsive">
    <table class="table table-hover" id="topTracksTable">
      <thead>
        <tr>
          <th style="cursor:pointer;" data-column="rank">
            Rank <i class="bi bi-chevron-expand"></i>
          </th>
          <th>Cover</th>
          <th style="cursor:pointer;" data-column="title">
            Title <i class="bi bi-chevron-expand"></i>
          </th>
          <th style="cursor:pointer;" data-column="album">
            Album <i class="bi bi-chevron-expand"></i>
          </th>
          <th style="cursor:pointer;" data-column="duration">
            Duration <i class="bi bi-chevron-expand"></i>
          </th>
          <th style="cursor:pointer;" data-column="year">
            Year <i class="bi bi-chevron-expand"></i>
          </th>
          <th style="cursor:pointer;" data-column="popularity">
            Popularity <i class="bi bi-chevron-expand"></i>
          </th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @foreach($topTracks as $track)
        <tr>
          <td class="fw-bold text-muted">#{{ $track->rank }}</td>
          <td>
            @if($track->album_image_url)
              <img src="{{ $track->album_image_url }}" class="rounded" style="width:48px;height:48px;object-fit:cover;" alt="{{ $track->title }}">
            @endif
          </td>
          <td>
            <div class="fw-semibold">{{ $track->title }}</div>
            @if($track->explicit)
              <span class="badge bg-secondary">E</span>
            @endif
          </td>
          <td>{{ $track->album_name }}</td>
          <td data-duration="{{ $track->duration_ms ?? 0 }}">
            @if($track->duration_ms)
              {{ gmdate('i:s', $track->duration_ms / 1000) }}
            @endif
          </td>
          <td data-year="{{ $track->release_date ? substr($track->release_date, 0, 4) : '' }}">
            @if($track->release_date)
              {{ substr($track->release_date, 0, 4) }}
            @endif
          </td>
          <td data-popularity="{{ $track->popularity }}">
            <span class="badge bg-warning text-dark">
              <i class="bi bi-star-fill"></i> {{ $track->popularity }}
            </span>
          </td>
          <td>
            @if($track->spotify_url)
              <a href="{{ $track->spotify_url }}" target="_blank" class="btn btn-sm btn-success" rel="noopener">
                <i class="bi bi-play-fill"></i>
              </a>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const table = document.getElementById('topTracksTable');
  const headers = table.querySelectorAll('th[data-column]');
  let sortDirection = {};

  headers.forEach(header => {
    const column = header.dataset.column;
    sortDirection[column] = 'asc';

    header.addEventListener('click', function() {
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      
      // Toggle direction
      sortDirection[column] = sortDirection[column] === 'asc' ? 'desc' : 'asc';
      
      // Update icons
      headers.forEach(h => {
        const icon = h.querySelector('i');
        if (h === header) {
          icon.className = sortDirection[column] === 'asc' ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        } else {
          icon.className = 'bi bi-chevron-expand';
        }
      });

      // Sort rows
      rows.sort((a, b) => {
        let aVal, bVal;

        switch(column) {
          case 'rank':
            aVal = parseInt(a.cells[0].textContent.replace('#', ''));
            bVal = parseInt(b.cells[0].textContent.replace('#', ''));
            break;
          case 'title':
            aVal = a.cells[2].textContent.trim().toLowerCase();
            bVal = b.cells[2].textContent.trim().toLowerCase();
            break;
          case 'album':
            aVal = a.cells[3].textContent.trim().toLowerCase();
            bVal = b.cells[3].textContent.trim().toLowerCase();
            break;
          case 'duration':
            aVal = parseInt(a.cells[4].dataset.duration || 0);
            bVal = parseInt(b.cells[4].dataset.duration || 0);
            break;
          case 'year':
            aVal = parseInt(a.cells[5].dataset.year || 0);
            bVal = parseInt(b.cells[5].dataset.year || 0);
            break;
          case 'popularity':
            aVal = parseInt(a.cells[6].dataset.popularity || 0);
            bVal = parseInt(b.cells[6].dataset.popularity || 0);
            break;
        }

        if (aVal < bVal) return sortDirection[column] === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection[column] === 'asc' ? 1 : -1;
        return 0;
      });

      // Re-append sorted rows
      rows.forEach(row => tbody.appendChild(row));
    });
  });
});
</script>
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
  /* .artist-card, .album-card {
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
  } */
</style>
@endpush
@endsection
