/**
 * The editor's libraries, split out of `app.js` (P-6, PERF-01 in docs/release/parity/P-06-performance.md): only the
 * wizard and the edit screen load this chunk, when a rich-text editor starts (`resources/js/rich-text-editor.js`).
 */
export { Editor } from '@tiptap/core';
export { default as StarterKit } from '@tiptap/starter-kit';
export { default as Placeholder } from '@tiptap/extension-placeholder';
export { default as Bold } from '@tiptap/extension-bold';
export { default as Italic } from '@tiptap/extension-italic';
export { default as Underline } from '@tiptap/extension-underline';
export { default as BulletList } from '@tiptap/extension-bullet-list';
export { default as OrderedList } from '@tiptap/extension-ordered-list';
export { default as ListItem } from '@tiptap/extension-list-item';
export { default as Link } from '@tiptap/extension-link';
