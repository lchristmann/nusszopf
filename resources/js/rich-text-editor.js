/**
 * The historical project rich-text editor (`RichTextEditor.organism.js`),
 * rebuilt on TipTap and configured down to exactly its six-tool toolbar:
 * bold, italic, underline, unordered list, ordered list, link
 * (docs/rewrite/architecture-decisions.md, "Rich-text editor replacement for
 * Slate"). Nothing else the package offers is enabled — no headings, quotes,
 * code, strike-through, rules, hard breaks; and, matching Slate (which
 * defined no hotkeys), no formatting keyboard shortcuts or markdown-style
 * input rules either. Enter/Backspace/undo/redo keep their editing behavior.
 *
 * The document (ProseMirror JSON) is written straight into the Livewire
 * property named by `property` on every change; it is only sent to the
 * server with the next request, as with the historical single Formik form.
 * The server re-normalizes it (App\Support\RichText) — this file is a UI, not
 * a trust boundary.
 *
 * TipTap itself is a separate chunk (`lazy/tiptap.js`), fetched when the first editor starts, so pages without an
 * editor do not download it (P-6, PERF-01). Until it has arrived the toolbar does nothing.
 */

// `protocolAndDomainRE` etc. from the historical `withLinks` util: pasted text
// that is a URL becomes a link.
function isUrl(string) {
    if (typeof string !== 'string') return false;
    const match = string.match(/^(?:\w+:)?\/\/(\S+)$/);
    if (!match || !match[1]) return false;
    return /^localhost[:?\d]*(?:[^:?\d]\S*)?$/.test(match[1]) || /^[^\s.]+\.\S{2,}$/.test(match[1]);
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('nzRichText', ({ property, placeholder, label, blurAction = 'blurred' }) => {
        // Kept out of Alpine's reactive proxy: ProseMirror must see the raw object.
        let editor = null;

        return {
            tick: 0,
            // Declared here so that they stay this editor's own: Alpine writes a property the data object lacks
            // onto an enclosing scope, where every other editor on the page would see it.
            destroyed: false,
            errorObserver: null,

            async init() {
                const wire = this.$wire;
                const { Editor, StarterKit, Placeholder, ...tiptap } = await import('./lazy/tiptap.js');
                if (this.destroyed) return;

                editor = new Editor({
                    element: this.$refs.editor,
                    content: JSON.parse(JSON.stringify(wire.$get(property))),
                    extensions: [
                        StarterKit.configure({
                            blockquote: false,
                            code: false,
                            codeBlock: false,
                            dropcursor: false,
                            gapcursor: false,
                            hardBreak: false,
                            heading: false,
                            horizontalRule: false,
                            listKeymap: false,
                            strike: false,
                            trailingNode: false,
                            bold: false,
                            italic: false,
                            underline: false,
                            bulletList: false,
                            orderedList: false,
                            listItem: false,
                            link: false,
                        }),
                    ].concat(this.marksAndLists(tiptap)).concat([
                        Placeholder.configure({ placeholder }),
                    ]),
                    editorProps: {
                        attributes: {
                            role: 'textbox',
                            'aria-multiline': 'true',
                            'aria-label': label,
                            class: 'nz-rte-content px-4 py-3 min-h-48 focus:outline-none',
                        },
                        handlePaste: (view, event) => {
                            const text = event.clipboardData?.getData('text/plain');
                            if (text && isUrl(text)) {
                                event.preventDefault();
                                this.insertLink(text);
                                return true;
                            }
                            return false;
                        },
                    },
                    onUpdate: ({ editor: e }) => {
                        wire.$set(property, e.getJSON(), false);
                        this.$el.dispatchEvent(new CustomEvent('nz-rte-change', { bubbles: true }));
                    },
                    onTransaction: () => {
                        this.tick++;
                    },
                    onBlur: () => {
                        wire.$call(blurAction, property);
                    },
                });

                // Decision A-7: tie the field's validation message (`x-input-error for=property`, rendered next
                // to this wire:ignore'd editor) to the textbox, and mark it invalid while the message shows.
                const errorId = `error-${property.replace(/[.[\]]/g, '-')}`;
                const textbox = editor.view.dom;
                textbox.setAttribute('aria-describedby', errorId);
                const syncInvalid = () => {
                    if (document.getElementById(errorId)) textbox.setAttribute('aria-invalid', 'true');
                    else textbox.removeAttribute('aria-invalid');
                };
                syncInvalid();
                this.errorObserver = new MutationObserver(syncInvalid);
                this.errorObserver.observe(this.$el.parentElement, { childList: true, subtree: true });
            },

            destroy() {
                this.destroyed = true;
                this.errorObserver?.disconnect();
                editor?.destroy();
                editor = null;
            },

            // Bold/italic/underline/lists/link, with package-default hotkeys and
            // markdown input/paste rules stripped (the historical editor had none).
            marksAndLists({ Bold, Italic, Underline, BulletList, OrderedList, ListItem, Link }) {
                const plain = (extension) =>
                    extension.extend({
                        addKeyboardShortcuts() {
                            return {};
                        },
                        addInputRules() {
                            return [];
                        },
                        addPasteRules() {
                            return [];
                        },
                    });

                return [
                    plain(Bold),
                    plain(Italic),
                    plain(Underline),
                    plain(BulletList),
                    plain(OrderedList),
                    // Enter/Backspace handling stays; content is restricted to one
                    // paragraph so lists cannot nest (Slate's model had no nesting).
                    ListItem.extend({ content: 'paragraph' }),
                    Link.configure({ openOnClick: false, autolink: false, linkOnPaste: false }).extend({
                        addKeyboardShortcuts() {
                            return {};
                        },
                        addInputRules() {
                            return [];
                        },
                        addPasteRules() {
                            return [];
                        },
                    }),
                ];
            },

            isActive(name) {
                this.tick; // re-evaluate on every transaction
                return editor ? editor.isActive(name) : false;
            },

            toggle(name) {
                if (!editor) return;
                const chain = editor.chain().focus();
                ({
                    bold: () => chain.toggleBold(),
                    italic: () => chain.toggleItalic(),
                    underline: () => chain.toggleUnderline(),
                    bulletList: () => chain.toggleBulletList(),
                    orderedList: () => chain.toggleOrderedList(),
                })[name]().run();
            },

            promptLink() {
                if (!editor) return;
                const url = window.prompt('Gib die URL des Links ein.');
                if (!url) return;
                this.insertLink(url);
            },

            insertLink(url) {
                // Historical `wrapLink`: an existing link is replaced; a collapsed
                // selection inserts the URL as the link text.
                const { empty } = editor.state.selection;
                if (empty) {
                    editor
                        .chain()
                        .focus()
                        .insertContent({ type: 'text', text: url, marks: [{ type: 'link', attrs: { href: url } }] })
                        .run();
                } else {
                    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
                }
            },
        };
    });
});
