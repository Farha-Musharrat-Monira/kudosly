/**
 * Kudosly — dashboard shell helpers.
 *
 * These are plain DOM helpers rather than Alpine components because they must
 * work on pages that do not mount a Livewire component. Livewire bundles its
 * own Alpine runtime, so `x-data` directives are only reliable inside
 * Livewire-rendered markup.
 */

/**
 * Mobile sidebar drawer.
 */
function setupSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    const backdrop = document.getElementById('sidebar-backdrop');

    if (!sidebar || !toggle) {
        return;
    }

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop?.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
    };

    const close = () => {
        sidebar.classList.add('-translate-x-full');
        backdrop?.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    };

    toggle.addEventListener('click', () => {
        sidebar.classList.contains('-translate-x-full') ? open() : close();
    });

    backdrop?.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
            close();
        }
    });
}

/**
 * User dropdown menu in the top bar.
 */
function setupUserMenu() {
    const toggle = document.getElementById('user-menu-toggle');
    const panel = document.getElementById('user-menu-panel');

    if (!toggle || !panel) {
        return;
    }

    const close = () => {
        panel.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();

        const willOpen = panel.classList.contains('hidden');

        panel.classList.toggle('hidden', !willOpen);
        toggle.setAttribute('aria-expanded', String(willOpen));
    });

    document.addEventListener('click', (event) => {
        if (!panel.contains(event.target) && !toggle.contains(event.target)) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}

/**
 * Copy-to-clipboard for the reserved public space URL.
 *
 * Any element with `data-copy-target` copies the value of the referenced
 * element to the clipboard and flashes a confirmation.
 */
function setupClipboard() {
    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-copy-target]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        const source = document.getElementById(trigger.dataset.copyTarget);
        const value = source?.value ?? source?.textContent?.trim() ?? '';

        if (!value) {
            return;
        }

        try {
            await navigator.clipboard.writeText(value.trim());
        } catch (error) {
            // Fall back for browsers that block the clipboard API.
            const helper = document.createElement('textarea');
            helper.value = value.trim();
            helper.setAttribute('readonly', '');
            helper.style.position = 'absolute';
            helper.style.left = '-9999px';
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            document.body.removeChild(helper);
        }

        const original = trigger.dataset.copyLabel ?? trigger.textContent;
        trigger.dataset.copyLabel = original;
        trigger.textContent = 'Copied!';

        window.setTimeout(() => {
            trigger.textContent = trigger.dataset.copyLabel;
        }, 2000);
    });
}

/**
 * Open and close modals that use the `x-modal` component.
 *
 * The event detail must match the modal's `name` prop.
 */
function setupModalTriggers() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-modal-open]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        window.dispatchEvent(
            new CustomEvent('open-modal', { detail: trigger.dataset.modalOpen })
        );
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-modal-close]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        window.dispatchEvent(
            new CustomEvent('close-modal', { detail: trigger.dataset.modalClose })
        );
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setupSidebar();
    setupUserMenu();
    setupClipboard();
    setupModalTriggers();
});
