@props(['url'])
<tr>
<td class="header" align="center">
<a href="{{ $url }}" class="header-link" target="_blank" rel="noopener">
<img src="{{ \App\Modules\Core\Support\Brand::emailLogoUrl() }}" class="logo" width="56" height="56" alt="" border="0">
<br>
<span class="header-name">{{ $slot }}</span>
</a>
</td>
</tr>
