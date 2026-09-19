import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Bold from '@tiptap/extension-bold';
import Italic from '@tiptap/extension-italic';
import Underline from '@tiptap/extension-underline';
import BulletList from '@tiptap/extension-bullet-list';
import OrderedList from '@tiptap/extension-ordered-list';
import ListItem from '@tiptap/extension-list-item';
import Link from '@tiptap/extension-link';

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
    window.Alpine.data('nzRichText', ({ property, placeholder, label }) => {
        // Kept out of Alpine's reactive proxy: ProseMirror must see the raw object.
        let editor = null;

        return {
            tick: 0,

            init() {
                const wire = this.$wire;

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
                    ].concat(this.marksAndLists()).concat([
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
                        wire.$call('blurred', property);
                    },
                });
            },

            destroy() {
                editor?.destroy();
                editor = null;
            },

            // Bold/italic/underline/lists/link, with package-default hotkeys and
            // markdown input/paste rules stripped (the historical editor had none).
            marksAndLists() {
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
