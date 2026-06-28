<!-- Navbar -->
<style>
    #navbar.navbar-compact .nav-label {
        display: none;
    }

    #navbar.navbar-compact .nav-icon {
        margin-right: 0;
    }
</style>

<nav id="navbar" class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-50">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16" data-navbar-inner>
            <!-- Logo -->
            <div class="flex items-center gap-3 shrink-0">
                <div class="w-10 h-10 bg-gradient-to-br from-gray-800 to-gray-600 rounded-xl flex items-center justify-center shadow-md">
                    <i class="fas fa-dove text-white text-lg"></i>
                </div>
            </div>

            <!-- Menú Desktop -->
            <div class="hidden md:flex items-center space-x-1 shrink-0" data-navbar-menu>
                <a href="{{ route('dashboard') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('dashboard') 
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-home nav-icon mr-2"></i>
                    <span class="nav-label">Dashboard</span>
                </a>
                
                <a href="{{ route('inventario.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('inventario.*') 
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-boxes nav-icon mr-2"></i>
                    <span class="nav-label">Inventario</span>
                </a>
                
                <a href="{{ route('movimientos.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('movimientos.*') 
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-exchange-alt nav-icon mr-2"></i>
                    <span class="nav-label">Movimientos</span>
                </a>
                
                <a href="{{ route('ventas.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('ventas.*') && !request()->routeIs('ventas.pagos.*')
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-cash-register nav-icon mr-2"></i>
                    <span class="nav-label">Ventas</span>
                </a>

                <a href="{{ route('cortes.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('cortes.*') 
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-calculator nav-icon mr-2"></i>
                    <span class="nav-label">Cortes</span>
                </a>
                
                <a href="{{ route('apartados.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('apartados.*')
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-handshake nav-icon mr-2"></i>
                    <span class="nav-label">Apartados</span>
                </a>
                
                <a href="{{ route('envios.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('envios.*') 
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-shipping-fast nav-icon mr-2"></i>
                    <span class="nav-label">Envíos</span>
                </a>
                
                <a href="{{ route('clientes.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('clientes.*') 
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-user-friends nav-icon mr-2"></i>
                    <span class="nav-label">Clientes</span>
                </a>
                
                <a href="{{ route('usuarios.index') }}" 
                   class="px-3 lg:px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-150 whitespace-nowrap
                          {{ request()->routeIs('usuarios.*') 
                              ? 'bg-gray-800 text-white' 
                              : 'text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas fa-users nav-icon mr-2"></i>
                    <span class="nav-label">Usuarios</span>
                </a>
            </div>

            <!-- Usuario Desktop -->
            <div class="hidden md:flex items-center gap-3 shrink-0" data-navbar-user>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-800">{{ session('username', 'Usuario') }}</p>
                    <p class="text-xs text-gray-500">
                        @if(session('roles'))
                            {{ session('roles')[0]['ROL'] ?? 'Usuario' }}
                        @else
                            Usuario
                        @endif
                    </p>
                </div>
                <div class="relative">
                    <button 
                        id="userMenuBtn"
                        class="w-10 h-10 bg-gradient-to-br from-gray-800 to-gray-600 rounded-full flex items-center justify-center text-white text-sm font-semibold shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                        title="Menú de usuario"
                    >
                        {{ strtoupper(substr(session('username', 'U'), 0, 2)) }}
                    </button>
                    
                    <!-- Menú desplegable de usuario -->
                    <div id="userDropdown" class="hidden absolute top-full right-0 mt-2 w-64 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden z-50">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <p class="text-sm font-semibold text-gray-800">{{ session('username', 'Usuario') }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                @if(session('roles'))
                                    {{ session('roles')[0]['ROL'] ?? 'Usuario' }}
                                @else
                                    Usuario
                                @endif
                            </p>
                            @if(session('codCongregante'))
                                <p class="text-xs text-gray-400 mt-2">
                                    <i class="fas fa-id-badge mr-1"></i>
                                    ID: {{ session('codCongregante') }}
                                </p>
                            @endif
                        </div>
                        <div class="py-1">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" 
                                        class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors duration-150 flex items-center gap-3">
                                    <i class="fas fa-sign-out-alt w-5"></i>
                                    <span class="font-medium">Cerrar Sesión</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón menú móvil -->
            <button 
                id="mobileMenuBtn" 
                class="md:hidden p-2 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors"
                aria-label="Abrir menú"
            >
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
    </div>

    <!-- Menú Móvil (desplegable) -->
    <div id="mobileMenu" class="hidden md:hidden border-t border-gray-200 bg-white">
        <div class="px-4 py-3 space-y-1">
            <a href="{{ route('dashboard') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('dashboard') 
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-home w-6"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            
            <a href="{{ route('inventario.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('inventario.*') 
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-boxes w-6"></i>
                <span class="ml-3">Inventario</span>
            </a>
            
            <a href="{{ route('movimientos.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('movimientos.*') 
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-exchange-alt w-6"></i>
                <span class="ml-3">Movimientos</span>
            </a>
            
            <a href="{{ route('ventas.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('ventas.*') && !request()->routeIs('ventas.pagos.*')
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-cash-register w-6"></i>
                <span class="ml-3">Ventas</span>
            </a>

            <a href="{{ route('cortes.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('cortes.*') 
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-calculator w-6"></i>
                <span class="ml-3">Cortes</span>
            </a>
            
            <a href="{{ route('apartados.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('apartados.*')
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-handshake w-6"></i>
                <span class="ml-3">Apartados</span>
            </a>
            
            <a href="{{ route('envios.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('envios.*') 
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-shipping-fast w-6"></i>
                <span class="ml-3">Envíos</span>
            </a>
            
            <a href="{{ route('clientes.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('clientes.*') 
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-user-friends w-6"></i>
                <span class="ml-3">Clientes</span>
            </a>
            
            <a href="{{ route('usuarios.index') }}" 
               class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('usuarios.*') 
                          ? 'bg-gray-800 text-white' 
                          : 'text-gray-700 hover:bg-gray-100' }}">
                <i class="fas fa-users w-6"></i>
                <span class="ml-3">Usuarios</span>
            </a>

            <!-- Usuario en móvil -->
            <div class="px-4 py-3 mt-4 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-gray-800 to-gray-600 rounded-full flex items-center justify-center text-white text-sm font-semibold shadow-md">
                        {{ strtoupper(substr(session('username', 'U'), 0, 2)) }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">{{ session('username', 'Usuario') }}</p>
                        <p class="text-xs text-gray-500">
                            @if(session('roles'))
                                {{ session('roles')[0]['ROL'] ?? 'Usuario' }}
                            @else
                                Usuario
                            @endif
                        </p>
                    </div>
                </div>
                
                <!-- Botón de cerrar sesión móvil -->
                <form action="{{ route('logout') }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" 
                            class="w-full flex items-center justify-center px-4 py-3 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors duration-150">
                        <i class="fas fa-sign-out-alt w-6"></i>
                        <span class="ml-3">Cerrar Sesión</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.getElementById('navbar');
    const navbarInner = navbar?.querySelector('[data-navbar-inner]');
    const navbarMenu = navbar?.querySelector('[data-navbar-menu]');
    let navbarMeasureQueued = false;

    function ajustarNavbar() {
        if (!navbar || !navbarInner || !navbarMenu) return;
        if (window.innerWidth < 768) {
            navbar.classList.remove('navbar-compact');
            return;
        }

        if (navbarMeasureQueued) return;
        navbarMeasureQueued = true;

        requestAnimationFrame(() => {
            navbar.classList.remove('navbar-compact');

            requestAnimationFrame(() => {
                const sePasa = navbarInner.scrollWidth > navbarInner.clientWidth + 1;

                navbar.classList.toggle('navbar-compact', sePasa);
                navbarMeasureQueued = false;
            });
        });
    }

    ajustarNavbar();
    window.addEventListener('resize', ajustarNavbar);
    window.addEventListener('load', ajustarNavbar);

    if (document.fonts?.ready) {
        document.fonts.ready.then(ajustarNavbar);
    }

    if ('ResizeObserver' in window && navbarInner) {
        new ResizeObserver(ajustarNavbar).observe(navbarInner);
    }

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            
            // Cambiar icono
            const icon = this.querySelector('i');
            if (mobileMenu.classList.contains('hidden')) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
        });

        // Cerrar menú al hacer click en un enlace
        const menuLinks = mobileMenu.querySelectorAll('a');
        menuLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.add('hidden');
                const icon = mobileMenuBtn.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            });
        });

        // Cerrar menú al hacer resize a desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                mobileMenu.classList.add('hidden');
                const icon = mobileMenuBtn.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }

    // Menú desplegable de usuario (Desktop)
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });

        // Cerrar dropdown al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!userDropdown.classList.contains('hidden') && 
                !userDropdown.contains(e.target) && 
                e.target !== userMenuBtn) {
                userDropdown.classList.add('hidden');
            }
        });
    }
});
</script>
