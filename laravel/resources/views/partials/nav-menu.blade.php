{{--
  Primary navigation list, driven by config/navigation.php. Included twice (top
  navbar and sidebar); Tabler shows whichever matches the user's navbar-position.

  @param string $mode  'top' (dropdowns close on outside click) or 'side' (stay open)
--}}
@php
  $mode = $mode ?? 'top';
  $autoClose = $mode === 'side' ? 'false' : 'outside';
  $isAdmin = Auth::user() && Auth::user()->isAdmin();
@endphp
<ul class="navbar-nav">
  @foreach (config('navigation') as $section)
    @php
      $active = ! empty($section['active']) && Request::is(...$section['active']);
      $hasItems = ! empty($section['items']);
      $slug = \Illuminate\Support\Str::slug($section['label']);
    @endphp
    <li class="nav-item {{ $hasItems ? 'dropdown' : '' }} {{ $active ? 'active' : '' }}" data-nav-permission="{{ $section['permission'] }}">
      @if ($hasItems)
        <a class="nav-link dropdown-toggle" href="#nav-{{ $mode }}-{{ $slug }}" data-bs-toggle="dropdown" data-bs-auto-close="{{ $autoClose }}" role="button" aria-expanded="false">
          <span class="nav-link-icon"><i class="ti ti-{{ $section['icon'] }} icon"></i></span>
          <span class="nav-link-title">{{ $section['label'] }}</span>
        </a>
        <div class="dropdown-menu">
          @foreach ($section['items'] as $item)
            @if (! empty($item['divider']))
              <div class="dropdown-divider"></div>
              @continue
            @endif
            @if (! empty($item['admin_only']) && ! $isAdmin)
              @continue
            @endif
            <a class="dropdown-item {{ ! empty($item['active']) && Request::is(...$item['active']) ? 'active' : '' }}"
               href="{{ $item['href'] }}"
               @if (! empty($item['permission'])) data-permission="{{ $item['permission'] }}" @endif
               @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif>
              {{ $item['label'] }}
              @if (! empty($item['badge']))<span class="badge {{ $item['badge'][1] }} ms-auto">{{ $item['badge'][0] }}</span>@endif
              @if (! empty($item['external']))<i class="ti ti-external-link ms-1 small"></i>@endif
            </a>
          @endforeach
        </div>
      @else
        <a class="nav-link" href="{{ $section['href'] }}" @if (! empty($section['current']) ? Request::is(...$section['current']) : $active) aria-current="page" @endif>
          <span class="nav-link-icon"><i class="ti ti-{{ $section['icon'] }} icon"></i></span>
          <span class="nav-link-title">{{ $section['label'] }}</span>
        </a>
      @endif
    </li>
  @endforeach
</ul>
