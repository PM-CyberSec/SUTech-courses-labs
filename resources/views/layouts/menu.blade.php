<nav class="navbar navbar-expand-sm navbar-future fixed-top">
  <div class="container-fluid">
    <ul class="navbar-nav me-auto">
      <li class="nav-item">
        <a class="nav-link {{ Request::is('home') ? 'active' : '' }}" href="/home">Home</a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ Request::routeIs('products.index') ? 'active' : '' }}" href="{{ route('products.index') }}">Products</a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ Request::routeIs('sandbox*') ? 'active' : '' }}" href="{{ route('sandbox') }}">Sandbox</a>
      </li>
      @auth
        <li class="nav-item">
          <a class="nav-link {{ Request::routeIs('products.create') ? 'active' : '' }}" href="{{ route('products.create') }}">Add product</a>
        </li>
      @endauth
    </ul>
    <ul class="navbar-nav ms-auto">
      @auth
        <li class="nav-item d-flex align-items-center">
          <span class="nav-link py-0">{{ auth()->user()->name }}</span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('do_logout') }}">Logout</a>
        </li>
      @else
        <li class="nav-item">
          <a class="nav-link" href="{{ route('login') }}">Login</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('register') }}">Register</a>
        </li>
      @endauth
      
    </ul>
  </div>
</nav>