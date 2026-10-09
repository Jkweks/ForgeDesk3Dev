{{--
  Primary navigation list, driven by config/navigation.php. Included twice (top
  navbar and sidebar); Tabler shows whichever matches the user's navbar-position.

  @param string $mode  'top' (dropdowns close on outside click) or 'side' (stay open)
  @param string $set   'new' (config/navigation.php) or 'classic' (config/navigation_classic.php, the
                       original grouping). Both sets are rendered; CSS shows the one the user chose
                       (html[data-bs-nav-menu=classic]).
--}}
@php
  $mode = $mode ?? 'top';
  $set = $set ?? 'new';
  $navConfig = config($set === 'classic' ? 'navigation_classic' : 'navigation');
  $autoClose = $mode === 'side' ? 'false' : 'outside';
  $isAdmin = Auth::user() && Auth::user()->isAdmin();
@endphp
<div data-nav-set="{{ $set }}">
<ul class="navbar-nav">
  @foreach ($navConfig as $section)
    @php
      $active = ! empty($section['active']) && Request::is(...$section['active']);
      $hasItems = ! empty($section['items']);
      $slug = \Illuminate\Support\Str::slug($section['label']);
    @endphp
    {{-- `permission` may be a list (visible with ANY of them): applyNavigationPermissions() splits on spaces. --}}
    <li class="nav-item {{ $hasItems ? 'dropdown' : '' }} {{ $active ? 'active' : '' }}" data-nav-permission="{{ implode(' ', (array) $section['permission']) }}" @if (! empty($section['action_permission'])) data-permission="{{ $section['action_permission'] }}" @endif>
      @if ($hasItems)
        <a class="nav-link dropdown-toggle" href="#nav-{{ $set }}-{{ $mode }}-{{ $slug }}" data-bs-toggle="dropdown" data-bs-auto-close="{{ $autoClose }}" role="button" aria-expanded="false">
          <span class="nav-link-icon"><i class="ti ti-{{ $section['icon'] }} icon"></i></span>
          <span class="nav-link-title">{{ $section['label'] }}</span>
        </a>
        <div class="dropdown-menu">
          @foreach ($section['items'] as $item)
            @if (! empty($item['divider']))
              <div class="dropdown-divider"></div>
              @continue
            @endif
            @if (! empty($item['header']))
              <h6 class="dropdown-header">{{ $item['header'] }}</h6>
              @continue
            @endif
            @if (! empty($item['admin_only']) && ! $isAdmin)
              @continue
            @endif
            <a class="dropdown-item {{ ! empty($item['active']) && Request::is(...$item['active']) ? 'active' : '' }}"
               href="{{ $item['href'] }}"
               @if (! empty($item['permission'])) data-permission="{{ $item['permission'] }}" @endif
               @if (! empty($item['nav'])) data-nav-permission="{{ $item['nav'] }}" @endif
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
</div>
