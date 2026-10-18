<style>
    /* 
     * Premium SaaS Vibe (Telegram x Stripe x Linear x Notion) 
     * Dark theme overrides for Filament v3
     */

    /* Backgrounds & Typography */
    .dark body, .dark .fi-main {
        background-color: #0f0f11 !important;
        font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
        letter-spacing: -0.01em;
    }
    

    
    /* Cards & Glassmorphism */
    .dark .fi-wi-widget, 
    .dark .fi-ta-content,
    .dark .fi-section {
        background-color: #18181b !important;
        border: 1px solid rgba(255, 255, 255, 0.05) !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -2px rgba(0, 0, 0, 0.2) !important;
        backdrop-filter: blur(10px);
    }

    /* Table Rows (Cards) Hover Lift */
    .dark .fi-ta-record {
        background-color: #18181b !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03) !important;
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.2s ease;
    }
    .dark .fi-ta-record:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.5) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        z-index: 10;
        position: relative;
    }

    /* Status Pills (Soft Neon + Blur) */
    .dark .fi-badge {
        background-color: rgba(255, 255, 255, 0.03) !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        backdrop-filter: blur(6px) !important;
        -webkit-backdrop-filter: blur(6px) !important;
        font-weight: 600 !important;
    }
    .dark .fi-badge-color-warning {
        color: #fbbf24 !important;
        background-color: rgba(245, 158, 11, 0.1) !important;
        border-color: rgba(245, 158, 11, 0.2) !important;
        box-shadow: 0 0 10px rgba(245, 158, 11, 0.1);
    }
    .dark .fi-badge-color-success {
        color: #34d399 !important;
        background-color: rgba(16, 185, 129, 0.1) !important;
        border-color: rgba(16, 185, 129, 0.2) !important;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.1);
    }
    .dark .fi-badge-color-primary {
        color: #818cf8 !important;
        background-color: rgba(99, 102, 241, 0.1) !important;
        border-color: rgba(99, 102, 241, 0.2) !important;
        box-shadow: 0 0 10px rgba(99, 102, 241, 0.1);
    }

    /* Product Grid Image Hover Zoom & Aspect Ratio */
    .product-image-zoom {
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        aspect-ratio: 16/9;
        object-fit: cover;
    }
    .fi-ta-record:hover .product-image-zoom {
        transform: scale(1.03);
    }

    /* Stock Glow States */
    .stock-glow-green {
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.15) !important;
        border-color: rgba(16, 185, 129, 0.3) !important;
    }
    .stock-glow-yellow {
        box-shadow: 0 0 15px rgba(245, 158, 11, 0.15) !important;
        border-color: rgba(245, 158, 11, 0.3) !important;
    }
    .stock-border-red {
        border: 1px solid rgba(239, 68, 68, 0.5) !important;
        background-color: rgba(239, 68, 68, 0.05) !important;
    }



    /* Sidebar Blur (Glassmorphism) */
    .dark .fi-sidebar {
        background-color: rgba(15, 15, 17, 0.6) !important;
        backdrop-filter: blur(16px) !important;
        border-right: 1px solid rgba(255, 255, 255, 0.03) !important;
    }

    /* Sidebar Active Item Glow (Linear Style) */
    .dark .fi-sidebar-item-active > a {
        background: linear-gradient(90deg, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0) 100%) !important;
        border-left: 2px solid #818cf8 !important;
        color: #fff !important;
    }
    
    /* Chart Grids Subtle */
    canvas {
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
    }
    
    /* Chart Tooltips */
    #chartjs-tooltip, .chartjs-tooltip {
        background: rgba(24, 24, 27, 0.8) !important;
        backdrop-filter: blur(8px) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 8px !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important;
        color: #fff !important;
    }
</style>
