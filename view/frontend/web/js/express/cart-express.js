/**
 * SeQura Express Checkout — cart page / mini-cart mount initializer.
 *
 * The button itself is rendered by the shared CDN library into the
 * `.sequra-express-checkout-button` mount element; the library owns the click and fetches the
 * element's data-url (the cart solicit endpoint). This initializer registers the shared
 * solicit-error gate (422 → inline message) and asks the library to mount buttons injected after
 * its boot scan — the mini-cart markup arrives via the `cart` customer-data section and re-renders
 * on every section refresh, re-running this initializer through its data-mage-init attribute.
 *
 * The mini-cart markup is part of the cached `cart` section, so it can be stale after the merchant
 * disables Express Checkout in the portal (the section is only re-fetched on a cart change or
 * login). For the mini-cart we therefore re-check availability live against a non-cached endpoint
 * and tear the button down when it is no longer available. The cart page is server-rendered fresh
 * on every load and needs no re-check.
 */
define(
    [
        'jquery',
        'Sequra_Core/js/express/library-button'
    ],
    function ($, libraryButton) {
        'use strict';

        // Render states returned by the availability endpoint (mirrors AvailabilityEvaluator).
        const STATE_BUTTON = 'button',
            STATE_MESSAGE = 'message';

        /**
         * Re-checks mini-cart availability against the non-cached endpoint and reconciles the
         * cached button with the live answer: still available → leave it; "not available for your
         * account" → replace it with the inline message; otherwise → remove it. A transport error
         * leaves the button untouched (fail open).
         *
         * @param {jQuery} $wrapper The mini-cart express wrapper element.
         * @param {String} url The availability endpoint URL.
         */
        function recheckMiniCartAvailability($wrapper, url) {
            $.ajax({
                url: url,
                dataType: 'json',
                cache: false
            }).done(function (data) {
                var state = data && data.state ? data.state : '';

                if (state === STATE_BUTTON) {
                    return;
                }

                if (state === STATE_MESSAGE) {
                    $wrapper.empty().append(
                        $('<span/>', {
                            'class': 'sequra-express-checkout-unavailable',
                            text: data.message || ''
                        })
                    );

                    return;
                }

                $wrapper.remove();
            });
        }

        return function (config, element) {
            const $wrapper = $(element),
                availabilityUrl = $wrapper.data('availabilityUrl');

            libraryButton.attachErrorHandler();
            libraryButton.refreshButtons();

            if (availabilityUrl && $wrapper.hasClass('sequra-express-checkout--minicart')) {
                recheckMiniCartAvailability($wrapper, availabilityUrl);
            }
        };
    }
);
