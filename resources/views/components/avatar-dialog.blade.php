@props(['open', 'close'])

{{--
    AvatarDialog.js + Cropper.organism.js (Confirmed): pick an image, crop it
    to a round area with rotate/zoom-in/zoom-out controls, "Speichern"/
    "Abbrechen". Client-side crop/compress via `cropperjs`
    (resources/js/avatar-cropper.js — the smallest package that covers the
    historical rotate/zoom feature set, CLAUDE.md's "smallest client-side
    solution"), uploaded through Livewire's own temporary-upload mechanism,
    then validated and re-encoded server-side (App\Support\AvatarUploader) —
    the historical implementation trusted the browser's own crop/compress
    step; this one does not (`.claude/rules/05-engineering-quality.md`).
--}}
<x-dialog
    data-test="avatar-dialog"
    label="Avatar bearbeiten"
    class="relative text-steel-700 bg-steel-200"
    x-data="nzAvatarCropper()"
    x-show="{{ $open }}"
    x-cloak
    x-trap.noscroll="{{ $open }}"
    x-on:avatar-saved.window="{{ $close }}"
>
    <x-button
        variant="clean"
        size="baseClean"
        class="absolute top-0 right-0 p-1 m-3"
        aria-label="Schließen"
        x-on:click="reset(); {{ $close }}"
    ><x-icon name="x" /></x-button>

    <div class="-mx-6 -mt-10 overflow-hidden sm:-mx-8 sm:rounded-t-md nz-avatar-cropper">
        <div x-show="!hasImage" class="flex items-center justify-center w-full h-96 md:h-128 bg-steel-700">
            <label>
                <input
                    x-ref="fileInput"
                    type="file"
                    accept="image/png, image/jpeg"
                    class="sr-only"
                    x-on:change="pickFile($event)"
                />
                <div class="flex transition-transform duration-150 ease-out transform scale-100 cursor-pointer text-steel-100 hover:scale-105" aria-hidden="true">
                    <x-icon name="upload" class="mr-3" />
                    <x-text variant="textSm">Bild auswählen</x-text>
                </div>
            </label>
        </div>
        <div x-show="hasImage" class="relative w-full h-96 md:h-128">
            {{-- `w-full h-full` (not just `max-w-full`): cropperjs sizes its
                 generated crop canvas to this element's own rendered box, not
                 its parent's — it must already fill the container before
                 `new Cropper()` runs, or the crop area renders at the image's
                 own intrinsic size instead. --}}
            <img x-ref="cropperImage" class="block w-full h-full" alt="" />
        </div>
    </div>

    <div class="my-5 space-x-5 text-center">
        <x-button x-bind:disabled="!hasImage" x-on:click="rotate()" variant="clean" size="circle" class="bg-steel-300"><x-icon name="rotate-cw" /></x-button>
        <x-button x-bind:disabled="!hasImage" x-on:click="zoomIn()" variant="clean" size="circle" class="bg-steel-300"><x-icon name="zoom-in" /></x-button>
        <x-button x-bind:disabled="!hasImage" x-on:click="zoomOut()" variant="clean" size="circle" class="bg-steel-300"><x-icon name="zoom-out" /></x-button>
    </div>
    <div class="mt-5 space-x-5 text-center">
        <x-button data-test="btn_save_avatar-dialog" x-bind:disabled="!hasImage || uploading" x-on:click="save()" class="bg-steel-300">Speichern</x-button>
        <x-button x-on:click="reset(); {{ $close }}">Abbrechen</x-button>
    </div>
</x-dialog>
