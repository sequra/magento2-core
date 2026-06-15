/**
 * SeQura Express Checkout — cart page / mini-cart mount initializer.
 *
 * The button itself is rendered by the shared CDN library into the
 * `.sequra-express-checkout-button` mount element; the library owns the click and fetches the
 * element's data-url (the cart solicit endpoint). This initializer only registers the shared
 * solicit-error gate (401 guest → login pop-up, 422 → inline message) and asks the library to
 * mount buttons injected after its boot scan — the mini-cart markup arrives via the `cart`
 * customer-data section and re-renders on every section refresh, re-running this initializer
 * through its data-mage-init attribute.
 */
define(
    [
        'Sequra_Core/js/express/library-button'
    ],
    function (libraryButton) {
        'use strict';

        return function () {
            libraryButton.attachErrorHandler();
            libraryButton.refreshButtons();
        };
    }
);
