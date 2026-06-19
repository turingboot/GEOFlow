/**
 * Admin Tailwind config (v3.4) — compiles the admin backend's utility CSS to a
 * static file, replacing the runtime Play CDN (no more FOUC / sidebar flicker).
 * Mirrors the token overrides that used to live inline in admin.layouts.app:
 * blue -> Tavix blue, gray -> slate, Inter, rounder radii, soft shadows.
 *
 * Recompile after changing admin Blade classes:
 *   npx tailwindcss@3.4.17 -c tailwind.admin.config.js \
 *     -i resources/css/admin.tailwind.css -o public/assets/css/admin-tailwind.css --minify
 *
 * @type {import('tailwindcss').Config}
 */
module.exports = {
    content: ['./resources/views/admin/**/*.blade.php'],
    theme: {
        extend: {
            colors: {
                blue: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a', 950: '#172554' },
                gray: { 50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155', 800: '#1e293b', 900: '#0f172a', 950: '#020617' },
            },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', 'sans-serif'],
            },
            borderRadius: { DEFAULT: '0.5rem', md: '0.625rem', lg: '0.75rem', xl: '1rem', '2xl': '1.25rem' },
            boxShadow: {
                sm: '0 1px 2px 0 rgb(15 23 42 / 0.04)',
                DEFAULT: '0 1px 3px 0 rgb(15 23 42 / 0.06), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
                md: '0 6px 16px -6px rgb(15 23 42 / 0.12)',
                lg: '0 12px 28px -10px rgb(15 23 42 / 0.16)',
            },
        },
    },
    plugins: [],
};
