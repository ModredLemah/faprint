// assets/js/config.js

// 1. Global API Base
const API_BASE = 'http://localhost/faprint/api';

// 2. Global Session Helper
const getSession = ( ) => JSON.parse(localStorage.getItem('fa_session') || '{}');

// 3. Global Auth Check (Add to the top of protected pages)
const checkAuth = (requiredRole = null) => {
    const session = getSession();
    if (!session.id) {
        window.location.href = 'fa_print_login.html';
        return null;
    }
    if (requiredRole && session.role !== requiredRole) {
        window.location.href = 'fa_print_landing.html';
        return null;
    }
    return session;
};

// 4. Global Theme Helper
const initTheme = () => {
    const saved = localStorage.getItem('fa_theme') || 'dark';
    if (saved === 'light') document.documentElement.setAttribute('data-theme', 'light');
};