/**
 * SeQura Express Checkout — login-action mixin.
 *
 * Magento's Magento_Customer/js/action/login always reloads/redirects on success, which
 * would break the seamless guest Express Checkout flow. When (and only when) the
 * `window.__sequraExpressGuestLogin` sentinel is set, this mixin performs the same login
 * POST WITHOUT the reload, refreshes the `customer` section, and signals success via a
 * `sequra:express:login-success` document event. Without the sentinel it delegates to the
 * original action unchanged, so every other login on the site behaves exactly as before.
 */
define(
    [
        'jquery',
        'mage/storage',
        'Magento_Ui/js/model/messageList',
        'Magento_Customer/js/customer-data',
        'mage/translate'
    ],
    function ($, storage, globalMessageList, customerData, $t) {
        'use strict';

        return function (originalAction) {
            // The login pop-up view clears its loading spinner (isLoading(false)) only from a
            // registered login callback — not from the returned promise. The original action
            // fires those callbacks in every branch; our no-reload path must do the same, or the
            // spinner stays up forever and the form is blocked (e.g. after invalid credentials the
            // shopper can't retry). Capture the callbacks and fire them in every branch.
            var loginCallbacks = [];

            function fireLoginCallbacks(loginData) {
                loginCallbacks.forEach(function (callback) {
                    callback(loginData);
                });
            }

            var action = function (loginData, redirectUrl, isGlobal, messageContainer) {
                var customerLoginUrl = 'customer/ajax/login',
                    container = messageContainer || globalMessageList;

                if (!window.__sequraExpressGuestLogin) {
                    return originalAction(loginData, redirectUrl, isGlobal, messageContainer);
                }

                if (loginData.customerLoginUrl) {
                    customerLoginUrl = loginData.customerLoginUrl;
                    delete loginData.customerLoginUrl;
                }

                return storage.post(
                    customerLoginUrl,
                    JSON.stringify(loginData),
                    isGlobal
                ).done(function (response) {
                    if (response.errors) {
                        container.addErrorMessage(response);
                        // Clear the spinner so the shopper can correct and resubmit.
                        fireLoginCallbacks(loginData);

                        return;
                    }

                    fireLoginCallbacks(loginData);
                    customerData.invalidate(['customer']);
                    customerData.reload(['customer'], true);
                    $(document).trigger('sequra:express:login-success');
                }).fail(function () {
                    container.addErrorMessage({
                        'message': $t('Could not authenticate. Please try again later')
                    });
                    fireLoginCallbacks(loginData);
                });
            };

            // Capture callbacks locally (to fire on the no-reload path) and forward them to the
            // original action so the standard login flow keeps working unchanged.
            action.registerLoginCallback = function (callback) {
                loginCallbacks.push(callback);
                originalAction.registerLoginCallback(callback);
            };

            return action;
        };
    }
);
