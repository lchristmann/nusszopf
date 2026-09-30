/**
 * Guided tour of the public demo (docs/handbuch/demo.md).
 *
 * A small non-modal overlay on the real pages, not a slideshow: each step highlights an element of the actual UI
 * and explains it in a card. The page stays usable, "Esc" or "Tour beenden" ends the tour at any point, and the
 * progress lives in sessionStorage so it carries across the ordinary page loads between steps. It only exists for
 * the shared demo account (`<body data-tour-enabled>`, resources/views/components/layout.blade.php).
 */
const KEY = 'nz-tour';
const CLASS_STEP_TARGET = 'nz-tour-target';

const steps = [
    {
        page: 'mine',
        target: '[data-nz-masonry]',
        title: 'Meine Projekte',
        text: 'Hier liegen Deine Projekte – öffentliche und private Entwürfe. Über das Menü eines Projekts veröffentlichst, bearbeitest oder löschst Du es.',
    },
    {
        page: 'mine',
        target: '[data-test="route_create-project_projects-page"]',
        title: 'Ein Projekt starten',
        text: 'Ein Projekt erzählt von Deiner Idee: Ziel, Beschreibung, Ort, Zeitraum und Team. Ein Assistent führt Dich Schritt für Schritt hindurch.',
    },
    {
        page: 'search',
        target: '[data-test="input_search-input"]',
        title: 'Projekte finden',
        text: 'Die Suche ist für alle offen. Sie durchsucht veröffentlichte Projekte und deren Gesuche; ausgelöst wird sie mit Enter.',
    },
    {
        page: 'search',
        target: '[data-test="btn_disclosure_filter-popover"]',
        title: 'Nach Gesuchen filtern',
        text: 'Mit dem Filter suchst Du gezielt nach dem, was Projekte brauchen: Mitstreiter:innen, Räume, Materialien, Finanzielles oder Sonstiges.',
    },
    {
        page: 'project',
        target: '[data-test="description_project-detail"]',
        title: 'Ein Projekt im Detail',
        text: 'Auf der Projektseite stehen Beschreibung, Team und Motto. Ort und Zeitraum siehst Du oben.',
    },
    {
        page: 'project',
        target: '[data-test="card_request"]',
        title: 'Gesuche',
        text: 'Gesuche zeigen, was dem Projekt noch fehlt. Ein Klick öffnet die Details – und darüber nimmst Du Kontakt auf.',
    },
    {
        page: 'profile',
        target: '#newsletter',
        title: 'Einstellungen und Newsletter',
        text: 'In den Einstellungen verwaltest Du Bild und Account. Die Newsletter-Funktion gehört zur Software: Betreiber:innen nutzen sie für ihre eigene Community. In der Demo ist sie abgeschaltet.',
    },
    {
        page: 'profile',
        target: null,
        title: 'Das war die Tour',
        text: 'Stöbere weiter oder leg selbst los. Und wenn der Nusszopf zu Deiner Gruppe passt: Du kannst ihn selbst betreiben.',
        last: true,
    },
];

function pageUrl(page) {
    return {
        mine: '/user/projects',
        search: '/search',
        profile: '/user/profile',
        project: document.body.dataset.tourProject,
    }[page];
}

function onPage(page) {
    const url = pageUrl(page);
    return Boolean(url) && window.location.pathname === new URL(url, window.location.origin).pathname;
}

const read = () => {
    try {
        const value = parseInt(sessionStorage.getItem(KEY), 10);
        return Number.isInteger(value) ? value : null;
    } catch {
        return null;
    }
};
const write = (value) => {
    try {
        value === null ? sessionStorage.removeItem(KEY) : sessionStorage.setItem(KEY, String(value));
    } catch {
        // No storage (private mode): the tour still works within one page.
    }
};

const visible = (selector) => [...document.querySelectorAll(selector)].find((el) => el.getClientRects().length > 0);

let card;
let ring;
let observer;
let waitTimer;

function end() {
    write(null);
    observer?.disconnect();
    clearTimeout(waitTimer);
    card?.remove();
    ring?.remove();
    card = ring = undefined;
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('resize', place);
    window.removeEventListener('scroll', place, true);
    document.querySelector('[data-tour-start]')?.classList.remove('hidden');
}

function onKey(event) {
    if (event.key === 'Escape') end();
}

let currentTarget;
function place() {
    if (!ring) return;
    if (!currentTarget || !document.contains(currentTarget)) {
        ring.classList.add('hidden');
        return;
    }
    const box = currentTarget.getBoundingClientRect();
    ring.classList.remove('hidden');
    Object.assign(ring.style, {
        top: `${box.top - 6}px`,
        left: `${box.left - 6}px`,
        width: `${box.width + 12}px`,
        height: `${box.height + 12}px`,
    });
}

function go(index) {
    if (index >= steps.length) return end();
    const next = steps[index];
    write(index);
    if (!onPage(next.page)) {
        window.location.href = pageUrl(next.page);
        return;
    }
    show(index);
}

function show(index) {
    const step = steps[index];
    document.querySelector('[data-tour-start]')?.classList.add('hidden');

    if (!ring) {
        ring = document.createElement('div');
        ring.setAttribute('aria-hidden', 'true');
        ring.className = 'hidden fixed z-[90] rounded-lg pointer-events-none ring-4 ring-lilac-400';
        ring.style.boxShadow = '0 0 0 9999px rgba(55, 71, 79, 0.35)';
        document.body.appendChild(ring);
        document.addEventListener('keydown', onKey);
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
    }

    card?.remove();
    card = document.createElement('div');
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-labelledby', 'nz-tour-title');
    card.setAttribute('aria-describedby', 'nz-tour-text');
    card.setAttribute('data-test', 'tour-card');
    card.className =
        'fixed z-[95] inset-x-3 bottom-3 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-96 p-5 bg-white border-2 rounded-lg shadow-lg border-steel-700 text-steel-700';

    const progress = document.createElement('p');
    progress.className = 'nz-text-xs text-steel-500';
    progress.textContent = `Schritt ${index + 1} von ${steps.length}`;

    const title = document.createElement('h2');
    title.id = 'nz-tour-title';
    title.className = 'mt-1 nz-title-sm';
    title.textContent = step.title;

    const text = document.createElement('p');
    text.id = 'nz-tour-text';
    text.className = 'mt-2 nz-text-sm';
    text.textContent = step.text;

    const actions = document.createElement('div');
    actions.className = 'flex flex-wrap items-center gap-3 mt-4';

    const button = (label, className, handler) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = `${className} cursor-pointer py-1 px-3 font-medium text-lg outline-none focus:outline-none`;
        el.textContent = label;
        el.addEventListener('click', handler);
        return el;
    };

    if (index > 0) actions.appendChild(button('Zurück', 'nz-btn-steel', () => go(index - 1)));
    const nextButton = button(step.last ? 'Fertig' : 'Weiter', 'nz-btn-lilac bg-lilac-200', () => go(index + 1));
    nextButton.setAttribute('data-test', 'tour-next');
    actions.appendChild(nextButton);

    if (step.last) {
        const link = document.createElement('a');
        link.href = document.body.dataset.tourInstall;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'nz-text-sm border-b-2 nz-link-current';
        link.textContent = 'Selbst betreiben';
        actions.appendChild(link);
    } else {
        const stop = button('Tour beenden', 'nz-text-sm underline', end);
        stop.setAttribute('data-test', 'tour-end');
        stop.className = 'nz-text-sm underline cursor-pointer outline-none focus:outline-none';
        actions.appendChild(stop);
    }

    card.append(progress, title, text, actions);
    document.body.appendChild(card);
    nextButton.focus({ preventScroll: true });

    focusTarget(step);
}

// Results on some pages arrive after load (Livewire's `wire:init`), so the target is waited for, briefly; a step whose
// element never shows up still explains itself, just without a highlight.
function focusTarget(step) {
    currentTarget = undefined;
    observer?.disconnect();
    clearTimeout(waitTimer);
    place();
    if (!step.target) return;

    const attempt = () => {
        const el = visible(step.target);
        if (!el) return false;
        currentTarget = el;
        el.scrollIntoView({
            block: 'center',
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
        place();
        setTimeout(place, 400);
        return true;
    };

    if (attempt()) return;
    observer = new MutationObserver(() => {
        if (attempt()) observer.disconnect();
    });
    observer.observe(document.body, { childList: true, subtree: true });
    waitTimer = setTimeout(() => observer.disconnect(), 5000);
}

function start() {
    write(0);
    go(0);
}

document.addEventListener('DOMContentLoaded', () => {
    if (!('tourEnabled' in document.body.dataset)) return;

    const url = new URL(window.location.href);
    if (url.searchParams.has('tour')) {
        url.searchParams.delete('tour');
        history.replaceState(null, '', url);
        write(0);
    }

    document.querySelector('[data-tour-start]')?.addEventListener('click', start);

    const index = read();
    if (index === null) return;
    if (steps[index] && onPage(steps[index].page)) show(index);
    else end();
});
