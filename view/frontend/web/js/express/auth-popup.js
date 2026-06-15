/**
 * SeQura Express Checkout — guest authentication pop-up helper.
 *
 * Opens Magento's native login pop-up and resolves once the customer has logged in (the
 * login-action mixin fires `sequra:express:login-success` after a no-reload login). A
 * module-level pending guard means repeated clicks reuse the same promise, so a single
 * login triggers a single downstream solicit. Rejects when the customer closes the
 * pop-up without logging in (or after a safety timeout), so the next click can open
 * the pop-up again.
 */
define(
    [
        'jquery',
        'Magento_Customer/js/model/authentication-popup',
        'Magento_Customer/js/customer-data'
    ],
    function ($, authenticationPopup, customerData) {
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
                    subscription,
                    $loginBlock;

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
                    if (authenticationPopup.modalWindow) {
                        $(authenticationPopup.modalWindow).off('modalclosed', onModalClosed);
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

                function onModalClosed() {
                    settle(false);
                }

                window.__sequraExpressGuestLogin = true;
                $(document).on('sequra:express:login-success', onSuccess);
                subscription = customerData.get('customer').subscribe(function (updatedCustomer) {
                    if (updatedCustomer && updatedCustomer.firstname) {
                        settle(true);
                    }
                });
                timeoutId = setTimeout(function () {
                    settle(false);
                }, TIMEOUT_MS);

                // Magento only initializes the authentication modal when guest checkout is
                // disabled (view/authentication-popup.js setModalElement), so with guest
                // checkout allowed modalWindow is null and showModal() is a silent no-op.
                // Create the modal ourselves. The modal widget clears the inline
                // display:none only on the element it wraps, so it must be given the
                // knockout-rendered .block-authentication node itself — wrapping the outer
                // #authenticationPopup div opens an empty modal.
                if (!authenticationPopup.modalWindow) {
                    $loginBlock = $('#authenticationPopup .block-authentication');
                    if ($loginBlock.length) {
                        authenticationPopup.createPopUp($loginBlock.get(0));
                    }
                }

                if (!authenticationPopup.modalWindow) {
                    // No modal to show (knockout has not rendered the login block yet) —
                    // fail fast so the next click retries instead of waiting on the timeout.
                    settle(false);

                    return deferred.promise();
                }

                // Closing the pop-up without logging in rejects, clearing the pending guard
                // so the next button click can open the pop-up again.
                $(authenticationPopup.modalWindow).on('modalclosed', onModalClosed);

                authenticationPopup.showModal();

                return deferred.promise();
            }
        };
    }
);
