/**
 * SeQura Express Checkout — shared helpers for the CDN-library-rendered button.
 *
 * The library (sequra-checkout.min.js) mounts an iframe button into every
 * `.sequra-express-checkout-button` element and owns the click: it fetches the element's
 * `data-url` (GET, raw HTML) and opens the identification form. Login gating is server-driven —
 * the solicit endpoints answer 401 for guests and 422 for ineligible customers — and surfaces
 * here through the single global `SequraConfiguration.expressCheckout.onError` callback, shared
 * by every button on the page (product, cart, mini-cart). The failing button is resolved from
 * the error's `uid` (the mounted iframe id), so each surface gets its own pop-up retry or
 * inline message.
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
        'Sequra_Core/js/express/auth-popup',
        'Sequra_Core/js/express/identification-form',
        'Magento_Checkout/js/model/error-processor'
    ],
    function ($, authPopup, identificationForm, errorProcessor) {
        'use strict';

        // HTTP status the solicit endpoints return for a guest caller.
        var HTTP_GUEST = 401,
            // HTTP status the solicit endpoints return when the request is not eligible.
            HTTP_NOT_ELIGIBLE = 422,
            attached = false;

        /**
         * Mounts the blocking spinner overlay shown while the post-login solicit runs.
         *
         * Reuses the CSS classes the CDN bundle injects for its own click overlay, so the
         * post-login wait looks identical to a logged in click. The close (×) button is the
         * same escape hatch the library offers: it abandons the solicit display.
         *
         * @param {Function} onCancel Called when the shopper closes the overlay.
         * @returns {jQuery} The overlay element.
         */
        function createSpinnerOverlay(onCancel) {
            var $overlay = $('<div/>', {'class': 'Sequra__ExpressCheckoutOverlay'})
                .append($('<div/>', {'class': 'Sequra__ExpressCheckoutSpinner'}))
                .append(
                    $('<button/>', {
                        type: 'button',
                        'class': 'Sequra__ExpressCheckoutClose',
                        'aria-label': 'Close',
                        html: '&times;'
                    }).on('click', function () {
                        $overlay.remove();
                        onCancel();
                    })
                );

            return $overlay.appendTo('body');
        }

        /**
         * Fetches the mount's data-url and shows the identification form.
         *
         * Used by the post-login retry; the end state is identical to the library-owned
         * click flow (same endpoint, same form display, same spinner overlay).
         *
         * @param {jQuery} $mount The `.sequra-express-checkout-button` mount element.
         */
        function fetchAndShowForm($mount) {
            var cancelled = false,
                $overlay = createSpinnerOverlay(function () {
                    cancelled = true;
                });

            $.ajax({
                url: $mount.attr('data-url'),
                dataType: 'html'
            }).done(function (html) {
                if (cancelled) {
                    return;
                }

                identificationForm.showIdentificationForm(html, function () {
                    $overlay.remove();
                });
            }).fail(function (response) {
                $overlay.remove();

                if (cancelled) {
                    return;
                }

                if (response && response.status === HTTP_NOT_ELIGIBLE) {
                    identificationForm.showUnavailable($mount);

                    return;
                }

                errorProcessor.process(response);
            });
        }

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
         * Global solicit-error gate: 401 (guest) opens the login pop-up and retries after a
         * successful login, 422 shows the inline message. The status arrives in ctx.status;
         * the message parse covers older bundles.
         *
         * @param {Object} ctx The library onError payload.
         */
        function onError(ctx) {
            var match = ctx && ctx.error ? String(ctx.error).match(/\((\d+)\)/) : null,
                status = ctx && ctx.status ? ctx.status : (match ? parseInt(match[1], 10) : null),
                $mount = resolveMount(ctx);

            if (status === HTTP_GUEST) {
                authPopup.open().then(function () {
                    fetchAndShowForm($mount);
                }, function () {
                    // Pop-up closed without logging in — nothing to do.
                });

                return;
            }

            if (status === HTTP_NOT_ELIGIBLE) {
                identificationForm.showUnavailable($mount);
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

            fetchAndShowForm: fetchAndShowForm,

            /**
             * Asks the library to (re)mount every express button on the page. Needed for
             * buttons injected after the boot scan (e.g. the mini-cart customer-data
             * section); a no-op until the library has loaded, whose own boot scan covers
             * the elements present at that point.
             */
            refreshButtons: function () {
                if (window.Sequra && typeof window.Sequra.onLoad === 'function') {
                    window.Sequra.onLoad(function () {
                        window.Sequra.refreshComponents({scope: '.sequra-express-checkout-button'});
                    });
                }
            }
        };
    }
);
