@if($targetVersion)
    <form method="post" action="{{ route('services.catalog.edit', [$tenantSlug, $targetVersion]) }}">
        @csrf
        <button class="ct-open" type="submit"><i class="ph-duotone ph-pencil-simple" aria-hidden="true"></i> Editar</button>
    </form>
@endif
