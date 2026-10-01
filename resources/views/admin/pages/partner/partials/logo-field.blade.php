<div class="fv-row w-100 flex-md-root">
    <label class="form-label">Logo</label>
    <input name="logo" class="form-control mb-2 input" tabindex="0" type="file"
        onchange="fileExAllowedWithSize(this,'{{ CommonHelper::appSettings('file_image_extensions_allowed') }}','{{ CommonHelper::appSettings('file_image_max_size') }}')">
    @include('admin.partials.form.input-error-message', [
        'key' => 'logo',
    ])
    @if (isset($item) && $item->profile_photo)
        <p><a
                href="{{ route('download.web', ['path' => $item->profile_photo, 'name' => 'Logo of ' . $item->name]) }}">Download</a>
        </p>
    @endif
</div>
