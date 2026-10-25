{{-- Admin panel brand (AdminPanelProvider::brandLogo): the owl from config/brand.php + the panel name. --}}
<span style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 1.125rem; white-space: nowrap;">
    <img src="{{ \App\Modules\Core\Support\Brand::logoUrl() }}" alt="" style="height: 2rem; width: 2rem;">
    <span>{{ \App\Modules\Core\Support\Brand::name() }} Admin</span>
</span>
