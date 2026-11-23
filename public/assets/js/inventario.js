document.addEventListener("DOMContentLoaded", function() {
    // Función para manejar cualquier menú (Dropdowns o Hamburguesa)
    function setupMenu(toggleId, menuId, activeClass = 'show') {
        const toggleBtn = document.getElementById(toggleId);
        const menu = document.getElementById(menuId);

        if (toggleBtn && menu) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Evita que el clic llegue al document
                
                // Cerramos todos los demás menús abiertos antes de abrir este
                document.querySelectorAll('.dropdown-menu, .mobile-menu').forEach(m => {
                    if (m.id !== menuId) {
                        m.classList.remove('show', 'active');
                    }
                });

                // Alternamos la clase correspondiente
                menu.classList.toggle(activeClass);
                console.log(`Cambiando ${menuId} a ${activeClass}`);
            });
        }
    }

    // Inicializamos cada menú con su respectiva clase de activación
    setupMenu('userMenu', 'userDropdown', 'show');       // Menú de usuario
    setupMenu('filterMenu', 'filterDropdown', 'show');   // Botón de Filtros
    setupMenu('menuToggle', 'mobileMenu', 'active');     // Hamburguesa móvil

    // CERRAR AL HACER CLIC FUERA
    document.addEventListener('click', function(e) {
        const mobileMenu = document.getElementById('mobileMenu');
        const filterMenu = document.getElementById('filterDropdown');
        const userMenu = document.getElementById('userDropdown');

        // Si el clic no fue dentro de la hamburguesa ni del menú móvil, cerrar
        if (mobileMenu && !mobileMenu.contains(e.target) && !e.target.closest('#menuToggle')) {
            mobileMenu.classList.remove('active');
        }

        // Si el clic no fue dentro del botón de filtro ni del menú de filtro, cerrar
        if (filterMenu && !filterMenu.contains(e.target) && !e.target.closest('#filterMenu')) {
            filterMenu.classList.remove('show');
        }

        // Si el clic no fue dentro del menú de usuario, cerrar
        if (userMenu && !userMenu.contains(e.target) && !e.target.closest('#userMenu')) {
            userMenu.classList.remove('show');
        }
    });
});