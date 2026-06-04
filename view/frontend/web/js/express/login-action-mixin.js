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

                        return;
                    }

                    customerData.invalidate(['customer']);
                    customerData.reload(['customer'], true);
                    $(document).trigger('sequra:express:login-success');
                }).fail(function () {
                    container.addErrorMessage({
                        'message': $t('Could not authenticate. Please try again later')
                    });
                });
            };

            // Preserve the public API of the original action.
            action.registerLoginCallback = originalAction.registerLoginCallback;

            return action;
        };
    }
);
