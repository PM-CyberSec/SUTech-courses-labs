const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // Sidebar state persistence is optional.
        }
    },
};

const whenReady = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
        return;
    }

    callback();
};

const watchMedia = (query, callback) => {
    if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', callback);
        return;
    }

    query.addListener(callback);
};

window.dldsInitializeSidebar = () => {
    if (window.__dldsSidebarInitialized) {
        window.dldsSyncSidebar?.();
        return;
    }

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('toggleBtn');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (!sidebar || !sidebarToggle) {
        return;
    }

    window.__dldsSidebarInitialized = true;

    const mobileMedia = window.matchMedia('(max-width: 639px)');
    const tabletMedia = window.matchMedia('(min-width: 640px) and (max-width: 1024px)');

    const syncSidebarState = () => {
        const expanded = sidebar.classList.contains('expanded');
        const open = document.body.dataset.sidebarOpen === '1';

        document.body.classList.remove('sidebar-open', 'sidebar-expanded', 'sidebar-collapsed');

        if (mobileMedia.matches) {
            if (open) {
                document.body.classList.add('sidebar-open');
            }
        } else if (expanded) {
            document.body.classList.add('sidebar-expanded');
        } else {
            document.body.classList.add('sidebar-collapsed');
        }

        sidebarToggle.setAttribute('aria-expanded', mobileMedia.matches ? String(open) : String(expanded));
        mobileSidebarToggle?.setAttribute('aria-expanded', String(open));
    };

    const rememberDesktopState = (expanded) => {
        if (!mobileMedia.matches && !tabletMedia.matches) {
            storage.set('dlds_sidebar_collapsed', expanded ? '0' : '1');
        }
    };

    const setSidebarExpanded = (expanded, remember = false) => {
        sidebar.classList.toggle('expanded', expanded);
        sidebar.classList.toggle('collapsed', !expanded);
        document.body.dataset.sidebarOpen = mobileMedia.matches && expanded ? '1' : '0';

        if (remember) {
            rememberDesktopState(expanded);
        }

        syncSidebarState();
    };

    const openSidebar = (event) => {
        event?.preventDefault();
        setSidebarExpanded(true);
    };

    const closeSidebar = () => setSidebarExpanded(false);

    const toggleSidebar = (event) => {
        event?.preventDefault();

        if (mobileMedia.matches) {
            setSidebarExpanded(document.body.dataset.sidebarOpen !== '1');
            return;
        }

        setSidebarExpanded(!sidebar.classList.contains('expanded'), true);
    };

    const syncOnResize = () => {
        if (mobileMedia.matches || tabletMedia.matches) {
            setSidebarExpanded(false);
            return;
        }

        setSidebarExpanded(storage.get('dlds_sidebar_collapsed') !== '1');
    };

    sidebarToggle.addEventListener('click', toggleSidebar);
    mobileSidebarToggle?.addEventListener('click', openSidebar);
    sidebarBackdrop?.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileMedia.matches) {
                closeSidebar();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    watchMedia(mobileMedia, syncOnResize);
    watchMedia(tabletMedia, syncOnResize);

    window.dldsSyncSidebar = syncSidebarState;
    window.dldsSetSidebarExpanded = setSidebarExpanded;
    window.dldsToggleSidebar = toggleSidebar;

    syncOnResize();
};

whenReady(window.dldsInitializeSidebar);
