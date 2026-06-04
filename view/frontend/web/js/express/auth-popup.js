/**
 * SeQura Express Checkout — guest authentication pop-up helper.
 *
 * Opens Magento's native login pop-up and resolves once the customer has logged in (the
 * login-action mixin fires `sequra:express:login-success` after a no-reload login). A
 * module-level pending guard means repeated clicks reuse the same promise, so a single
 * login triggers a single downstream solicit. Rejects after a safety timeout if the
 * customer abandons the pop-up.
 */
define(
    [
        'jquery',
        'Magento_Customer/js/model/authentication-popup',
        'Magento_Customer/js/model/customer'
    ],
    function ($, authenticationPopup, customer) {
        'use strict';

        var pending = null,
            // 5 minutes — abandon the wait if the customer never completes the login form.
            TIMEOUT_MS = 300000;

        return {
            /**
             * Opens the login pop-up and returns a promise resolved on successful login.
             *
             * @returns {jQuery.Deferred}
             */
            open: function () {
                var deferred,
                    timeoutId,
                    subscription;

                if (pending) {
                    return pending.promise();
                }

                deferred = $.Deferred();
                pending = deferred;

                function settle(success) {
                    if (!pending) {
                        return;
                    }
                    pending = null;
                    clearTimeout(timeoutId);
                    $(document).off('sequra:express:login-success', onSuccess);
                    if (subscription) {
                        subscription.dispose();
                    }
                    delete window.__sequraExpressGuestLogin;

                    if (success) {
                        // Close the login modal so it doesn't sit open over the identification form.
                        if (authenticationPopup.modalWindow) {
                            $(authenticationPopup.modalWindow).modal('closeModal');
                        }
                        deferred.resolve();
                    } else {
                        deferred.reject();
                    }
                }

                function onSuccess() {
                    settle(true);
                }

                window.__sequraExpressGuestLogin = true;
                $(document).on('sequra:express:login-success', onSuccess);
                subscription = customer.isLoggedIn.subscribe(function (loggedIn) {
                    if (loggedIn) {
                        settle(true);
                    }
                });
                timeoutId = setTimeout(function () {
                    settle(false);
                }, TIMEOUT_MS);

                authenticationPopup.showModal();

                return deferred.promise();
            }
        };
    }
);
