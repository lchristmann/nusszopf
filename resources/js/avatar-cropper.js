/**
 * The historical `AvatarDialog`/`Cropper` (`react-easy-crop`): pick an image,
 * crop it to a round 1:1 area with rotate/zoom-in/zoom-out controls, then
 * upload the result (`ui-library/stories/organisms/Cropper/Cropper.organism.js`).
 * `cropperjs` is the smallest client-side package that covers the same
 * rotate/zoom/crop feature set without reimplementing canvas crop math by
 * hand (`CLAUDE.md`, "smallest client-side solution").
 *
 * The crop box itself is fixed (not draggable/resizable) matching the
 * historical UI, which only ever let the *image* move under a fixed circular
 * mask, never the crop area itself.
 *
 * cropperjs is a separate chunk, fetched when a picture is picked, so only the profile's avatar dialog ever downloads
 * it (P-6, PERF-01 in docs/release/parity/P-06-performance.md). Its stylesheet stays in `app.css`.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('nzAvatarCropper', () => ({
        hasImage: false,
        // True once cropperjs is built on the picture. `hasImage` is true as soon as the file has been read, which is
        // earlier: the library's chunk is still to be fetched, and `save()` does nothing without a cropper. Saving
        // is offered only when it can work (P-16, P16-07).
        ready: false,
        uploading: false,
        cropper: null,
        cropperLibrary: null,

        pickFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            this.cropperLibrary ??= import('cropperjs').then((module) => module.default);
            const reader = new FileReader();
            reader.onload = () => {
                this.hasImage = true;
                // `$nextTick` alone is not enough here: it resolves once
                // Alpine has applied the `x-show` DOM change, but the browser
                // has not necessarily *painted* that layout change yet.
                // cropperjs measures the container synchronously at
                // construction time and never re-measures on its own, so
                // constructing it before a real layout/paint pass has
                // happened makes it size its canvas against the old
                // (`display: none`, zero-size) box — two animation frames is
                // the standard way to wait for that pass to land.
                this.$nextTick(() => {
                    requestAnimationFrame(() => requestAnimationFrame(() => this.startCropper(reader.result)));
                });
            };
            reader.readAsDataURL(file);
        },

        async startCropper(dataUrl) {
            const img = this.$refs.cropperImage;
            this.ready = false;
            const Cropper = await this.cropperLibrary;

            this.cropper?.destroy();
            this.cropper = null;

            img.onload = () => {
                this.cropper = new Cropper(img, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    cropBoxMovable: false,
                    cropBoxResizable: false,
                    toggleDragModeOnDblclick: false,
                    minCropBoxWidth: 100,
                    background: false,
                    ready: () => {
                        this.ready = true;
                    },
                });
            };
            img.src = dataUrl;
        },

        rotate() {
            this.cropper?.rotate(90);
        },

        zoomIn() {
            this.cropper?.zoom(0.1);
        },

        zoomOut() {
            this.cropper?.zoom(-0.1);
        },

        reset() {
            this.cropper?.destroy();
            this.cropper = null;
            this.hasImage = false;
            this.ready = false;
            this.uploading = false;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        save() {
            if (!this.cropper || this.uploading) return;

            this.uploading = true;
            window.nzToast('loading', 'Bild wird gespeichert.');

            // The historical size (150×150). The server re-encodes it at the historical quality, so this is nearly
            // lossless, like the historical crop canvas before compressorjs (P-6, PERF-02).
            const canvas = this.cropper.getCroppedCanvas({
                width: 150,
                height: 150,
                imageSmoothingQuality: 'high',
            });

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        this.uploading = false;
                        window.nzToast('error', 'Bild konnte nicht gespeichert werden.');
                        return;
                    }

                    const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });

                    this.$wire.upload(
                        'avatarUpload',
                        file,
                        // The server validates/re-encodes and dispatches its own
                        // `toast` + (on success only) `avatar-saved` browser event
                        // (App\Livewire\Profile\Profile::saveAvatar) — this only
                        // resets the dialog's local crop state either way.
                        () => this.$wire.saveAvatar().then(() => this.reset()),
                        () => {
                            this.uploading = false;
                            window.nzToast('error', 'Bild konnte nicht gespeichert werden.');
                        }
                    );
                },
                'image/jpeg',
                0.92
            );
        },
    }));
});
