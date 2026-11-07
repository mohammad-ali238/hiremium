import './bootstrap';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

/*
| Restricted rich-text editor for job descriptions (see <x-ui.rich-editor>).
| Quill is configured with an explicit format allow-list — bold, italic, h2/h3,
| bullet + numbered lists, blockquote — so nothing else can even be created
| (no links, images, colours, code…). The server re-sanitizes anyway.
*/
// Quill instances are kept OUT of Alpine's reactive scope. If the instance is
// stored on the x-data object, Alpine wraps it in a Proxy; Quill's internal
// identity checks (WeakMap lookups, node === blot.domNode) then fail and it
// throws "Cannot read properties of null (reading 'offset')" on focus/typing.
const quillInstances = new WeakMap();

window.hiremiumRichEditor = ({ editor = null, carrier = null, min = 0, max = null, model = null } = {}) => ({
    model,
    editorId: editor,
    carrierId: carrier,
    count: 0,
    get quill() { return quillInstances.get(document.getElementById(this.editorId)) ?? null; },
    min,
    max,
    get status() {
        if (this.count === 0) return 'empty';
        if (this.min && this.count < this.min) return 'short';
        if (this.max && this.count > this.max) return 'long';
        return 'ok';
    },
    init() {
        // Build the editor AFTER Alpine has finished initialising this subtree and
        // Livewire has completed its first render. Constructing Quill synchronously
        // inside init() (mid-Alpine-boot) produced a selection model that broke on
        // first focus ("Cannot read properties of null (reading 'offset')").
        this.$nextTick(() => requestAnimationFrame(() => this.mount()));
    },
    mount() {
        // IMPORTANT: hand Quill the RAW element, never `this.$refs.*` / `this.$root`
        // (Alpine returns those through its reactive Proxy).
        const el = document.getElementById(this.editorId);
        const carrier = document.getElementById(this.carrierId);   // hidden <input wire:model> (sibling, Livewire-owned)
        if (! el || quillInstances.has(el)) return;

        const quill = new Quill(el, {
            theme: 'snow',
            placeholder: el.dataset.placeholder || '',
            formats: ['bold', 'italic', 'header', 'list', 'blockquote'],
            modules: {
                toolbar: [
                    ['bold', 'italic'],
                    [{ header: 2 }, { header: 3 }],
                    [{ list: 'bullet' }, { list: 'ordered' }],
                    ['blockquote'],
                    ['clean'],
                ],
                clipboard: { matchVisual: false },
            },
        });
        quillInstances.set(el, quill);

        // Edit mode: read the saved HTML from the Livewire component ($wire) — the
        // hidden carrier's value is only populated later by Livewire.
        const initial = (this.model && this.$wire ? this.$wire.get(this.model) : null) ?? carrier?.value ?? '';
        if (initial) {
            quill.setContents(quill.clipboard.convert({ html: initial }), 'api');
            quill.setSelection(null, 'api');
            if (carrier) carrier.value = initial;
        }
        this.recount();

        // Editor → Livewire: write HTML into the carrier and fire `input`,
        // which is the event wire:model listens for. Quill's DOM is untouched.
        quill.on('text-change', (delta, old, source) => {
            this.recount();
            if (source !== 'user' || ! carrier) return;
            const empty = quill.getText().trim() === '';
            const html = empty ? '' : quill.getSemanticHTML();
            if (carrier.value === html) return;
            carrier.value = html;
            carrier.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // Livewire → editor (rare: form reset after save, record switch).
        document.addEventListener('livewire:updated', () => this.pullFromCarrier());
        el.closest('form')?.addEventListener('reset', () => this.pullFromCarrier());
    },
    pullFromCarrier() {
        const carrier = document.getElementById(this.carrierId);
        if (! carrier) return;
        const current = this.quill.getText().trim() === '' ? '' : this.quill.getSemanticHTML();
        if ((carrier.value || '') === current) return;
        if (this.quill.hasFocus()) return;                 // never yank the doc mid-typing
        this.setHTML(carrier.value || '');
        this.recount();
    },
    // Quill 2's supported way to replace content: convert HTML → Delta, then
    // setContents. (dangerouslyPasteHTML at index 0 leaves a stale trailing block
    // + selection that later throws "Cannot read properties of null (reading 'offset')".)
    setHTML(html) {
        const delta = html ? this.quill.clipboard.convert({ html }) : [];
        this.quill.setContents(delta, 'api');
        this.quill.setSelection(null, 'api');
    },
    recount() {
        this.count = this.quill ? this.quill.getText().trim().replace(/\s+/g, ' ').length : 0;
    },
});


/*
| Dashboard JS bundle
| --------------------------------------------------------------------------
| Livewire + Alpine are bundled HERE (livewire.esm) and started by us at the
| end of this file, after our stores and components are registered. The layout
| uses @livewireScriptConfig (not @livewireScripts) so Livewire does not also
| auto-load and start Alpine on its own — which previously fired `alpine:init`
| before this deferred module ran, leaving $store.toast/$store.confirm and the
| rich-editor component undefined.
*/
// Register stores/components BEFORE starting Livewire (which starts Alpine),
// then start it ourselves — see @livewireScriptConfig in the dashboard layout.
(() => {
    // Toasts — <x-ui.toast/> renders $store.toast.items.
    // Fire with: window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }))
    // or Livewire: $this->dispatch('toast', type: 'success', message: 'Saved');
    Alpine.store('toast', {
        items: [],
        push(detail = {}) {
            const id = Date.now() + Math.random();
            this.items.push({ id, type: detail.type || 'info', message: detail.message || '' });
            setTimeout(() => this.remove(id), detail.duration || 4000);
        },
        remove(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    // Confirm dialog — <x-ui.confirm/> renders this. Returns a Promise<boolean>:
    // $store.confirm.ask({ title, text, danger: true }).then(ok => ok && $wire.delete(id))
    Alpine.store('confirm', {
        open: false,
        title: '',
        text: '',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        danger: false,
        _resolve: null,
        ask(opts = {}) {
            this.title = opts.title || 'Are you sure?';
            this.text = opts.text || '';
            this.confirmText = opts.confirmText || 'Confirm';
            this.cancelText = opts.cancelText || 'Cancel';
            this.danger = !!opts.danger;
            this.open = true;
            return new Promise((resolve) => {
                this._resolve = resolve;
            });
        },
        confirm() {
            this.open = false;
            if (this._resolve) this._resolve(true);
        },
        cancel() {
            this.open = false;
            if (this._resolve) this._resolve(false);
        },
    });
})();

// Rich editor is an Alpine component so x-data="hiremiumRichEditor(...)" resolves before Alpine boots.
Alpine.data('hiremiumRichEditor', window.hiremiumRichEditor);

Livewire.start();

