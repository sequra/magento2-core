/**
 * SeQura Express Checkout — shared helpers for the CDN-library-rendered button.
 *
 * The library (sequra-checkout.min.js) mounts an iframe button into every
 * `.sequra-express-checkout-button` element and owns the click: it fetches the element's
 * `data-url` (GET, raw HTML) and opens the identification form. Express needs no Magento login —
 * the solicit endpoints run on whatever the session cart holds — so the only outcome handled here
 * is 422 (not eligible), which surfaces through the single global
 * `SequraConfiguration.expressCheckout.onError` callback, shared by every button on the page
 * (product, cart, mini-cart). The failing button is resolved from the error's `uid` (the mounted
 * iframe id), so each surface gets its own inline message.
 *
 * Dependency: the library itself is injected by the global `sequra.widget.initializer` block
 * (Sequra_Core/js/widget-initializer, present on every storefront page via default.xml). That
 * loader is feature-neutral — it loads whenever seQura is active, so the Express button no longer
 * depends on promotional widgets being configured. If the library is absent these helpers no-op
 * (they guard on `window.Sequra` / `window.SequraWidgetFacade`), leaving an inert mount.
 */
define(
    [
        'jquery',
        'Sequra_Core/js/express/identification-form'
    ],
    function ($, identificationForm) {
        'use strict';

        // HTTP status the solicit endpoints return when the request is not eligible.
        var HTTP_NOT_ELIGIBLE = 422,
            attached = false;

        /**
         * Resolves the mount element of the button that raised the error.
         *
         * @param {Object} ctx The library onError payload ({uid, error, status}).
         * @returns {jQuery}
         */
        function resolveMount(ctx) {
            var iframe = ctx && ctx.uid ? document.getElementById(ctx.uid) : null,
                $mount = iframe ? $(iframe).closest('.sequra-express-checkout-button') : $();

            return $mount.length ? $mount : $('.sequra-express-checkout-button').first();
        }

        /**
         * Global solicit-error gate: 422 shows the inline "not available" message. The status
         * arrives in ctx.status; the message parse covers older bundles.
         *
         * @param {Object} ctx The library onError payload.
         */
        function onError(ctx) {
            var match = ctx && ctx.error ? String(ctx.error).match(/\((\d+)\)/) : null,
                status = ctx && ctx.status ? ctx.status : (match ? parseInt(match[1], 10) : null);

            if (status === HTTP_NOT_ELIGIBLE) {
                identificationForm.showUnavailable(resolveMount(ctx));
            }
        }

        return {
            /**
             * Registers the shared onError callback (once per page).
             */
            attachErrorHandler: function () {
                if (attached || !window.SequraWidgetFacade || !window.SequraWidgetFacade.expressCheckout) {
                    return;
                }

                window.SequraWidgetFacade.expressCheckout.onError = onError;
                attached = true;
            },

            /**
             * Asks the library to (re)mount every express button on the page. Needed for
             * buttons injected after the boot scan (e.g. the mini-cart customer-data
             * section); a no-op until the library has loaded, whose own boot scan covers
             * the elements present at that point.
             *
             * `Sequra.onLoad` only defers while the loader shim is in place: the library drains
             * SequraOnLoad once on load and never revisits it, so a callback queued afterwards
             * would never run. Once the real library is there, call it directly.
             */
            refreshButtons: function () {
                var sequra = window.Sequra;

                if (!sequra) {
                    return;
                }

                if (typeof sequra.refreshComponents === 'function') {
                    sequra.refreshComponents({scope: '.sequra-express-checkout-button'});

                    return;
                }

                if (typeof sequra.onLoad === 'function') {
                    sequra.onLoad(function () {
                        window.Sequra.refreshComponents({scope: '.sequra-express-checkout-button'});
                    });
                }
            }
        };
    }
);
