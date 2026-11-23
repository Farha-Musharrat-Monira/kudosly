/**
 * The Kudosly widget loader.
 *
 * A host page embeds a widget with a single script tag:
 *
 *   <script src="https://app.example.com/widget.js" data-widget="<public id>" async></script>
 *
 * This file finds those tags, fetches the widget fragment from the same
 * origin the script itself was served from, and drops the widget into the
 * host page.
 *
 * Security properties, in order of importance:
 *
 *   1. The fragment URL is built from THIS SCRIPT'S OWN ORIGIN, read from
 *      its own `src` attribute. The host page cannot point the request at
 *      a different origin: doing so would mean loading a different script,
 *      which is not this one.
 *
 *   2. The public id is checked against a strict pattern before it is
 *      used, so a tampered attribute cannot smuggle a path, a query
 *      string or a protocol-relative URL into the request.
 *
 *   3. The response is parsed and only the single element the widget is
 *      expected to contain is imported. Nothing else in the response can
 *      reach the host page.
 *
 *   4. The script defines no globals and touches no cookies or storage,
 *      so the host page cannot call into it and it cannot read anything
 *      about the visitor.
 *
 * The loader is deliberately dependency-free and does not use eval.
 */
(function () {
    'use strict';

    /**
     * The pattern a public identifier must match: 32 lowercase hex
     * characters. Anything else is ignored.
     */
    var PUBLIC_ID_PATTERN = /^[0-9a-f]{32}$/;

    /**
     * The id of the element the widget fragment is expected to start with.
     */
    function rootId(publicId) {
        return 'kw-' + publicId;
    }

    /**
     * Build the fragment URL for a public id.
     *
     * The origin comes from this script's own URL, never from the host
     * page, so the request always goes back to the server that served
     * this file.
     */
    function fragmentUrl(script, publicId) {
        var origin;

        try {
            origin = new URL(script.src, document.currentScript ? document.currentScript.baseURI : undefined).origin;
        } catch (error) {
            return null;
        }

        return origin + '/widget/' + encodeURIComponent(publicId) + '/embed';
    }

    /**
     * Import the widget markup into the host page.
     *
     * The fragment is parsed in an inert document, so nothing runs while
     * it is being read. Only the expected root element is taken from it.
     */
    function mount(container, publicId, html) {
        var parsed = new DOMParser().parseFromString(html, 'text/html');
        var root = parsed.getElementById(rootId(publicId));

        if (!root) {
            return;
        }

        // The adopted node is imported rather than cloned so the script
        // keeps its own document context.
        container.appendChild(document.importNode(root, true));

        // The stylesheet travels with the markup in its own <style>
        // element, which lives inside the root. It is scoped to the
        // widget's id, so it cannot affect anything else on the page.
        var styles = parsed.querySelectorAll('style');

        for (var i = 0; i < styles.length; i++) {
            container.appendChild(document.importNode(styles[i], true));
        }
    }

    /**
     * Load one widget.
     */
    function load(script) {
        var publicId = script.getAttribute('data-widget');

        if (!publicId || !PUBLIC_ID_PATTERN.test(publicId)) {
            return;
        }

        var url = fragmentUrl(script, publicId);

        if (!url) {
            return;
        }

        var container = document.createElement('div');

        container.className = 'kudosly-widget';
        container.setAttribute('data-kudosly-widget', publicId);
        script.parentNode.insertBefore(container, script.nextSibling);

        fetch(url, {
            // Credentials are deliberately not sent. The fragment is public
            // and anonymous, and omitting credentials keeps a visitor's
            // session out of a third-party request entirely.
            credentials: 'omit',
            headers: { 'Accept': 'text/html' },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('The widget could not be loaded.');
                }

                return response.text();
            })
            .then(function (html) {
                mount(container, publicId, html);
            })
            .catch(function () {
                // A widget that cannot be loaded leaves nothing behind. The
                // host page is never left with an empty box.
                container.remove();
            });
    }

    function start() {
        var scripts = document.querySelectorAll('script[data-widget]');

        for (var i = 0; i < scripts.length; i++) {
            load(scripts[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
