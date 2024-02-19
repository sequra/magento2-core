define([
    "Magento_PageBuilder/js/content-type/preview",
    "Magento_PageBuilder/js/content-type-toolbar",
    "Magento_PageBuilder/js/events",
    "Magento_PageBuilder/js/content-type-menu/hide-show-option",
    "Magento_PageBuilder/js/uploader",
    "Magento_PageBuilder/js/wysiwyg/factory",
    "Magento_PageBuilder/js/config",
    'jquery'
], function (
    PreviewBase,
    Toolbar,
    events,
    hideShowOption,
    Uploader,
    WysiwygFactory,
    Config,
    $
) {
    "use strict";

    /**
     * Sequra content type preview class
     *
     * @param parent
     * @param config
     * @param stageId
     * @constructor
     */
    function Preview(parent, config, stageId) {
        let me = this;
        PreviewBase.call(this, parent, config, stageId);

        let initializeWidgets = function () {
            let sequraConfigParams = !window.sequraConfigParams.merchant
                ? null : {
                    merchant: window.sequraConfigParams.merchant,
                    assetKey: window.sequraConfigParams.assetKey,
                    products: window.sequraConfigParams.products.map(product => product.id),
                    scriptUri: window.sequraConfigParams.scriptUri,
                    decimalSeparator: window.sequraConfigParams.decimalSeparator,
                    thousandSeparator: window.sequraConfigParams.thousandSeparator,
                    locale: window.sequraConfigParams.locale,
                    currency: window.sequraConfigParams.currency,
                }

            if (typeof Sequra !== "undefined") {
                Sequra.refreshComponents?.();
            } else {
                if (sequraConfigParams !== null) {
                    (
                        function (i, s, o, g, r, a, m) {
                            i["SequraConfiguration"] = g;
                            i["SequraOnLoad"] = [];
                            i[r] = {};
                            i[r][a] = function (callback) {
                                i["SequraOnLoad"].push(callback);
                            };
                            (a = s.createElement(o)),
                                (m = s.getElementsByTagName(o)[0]);
                            a.async = 1;
                            a.src = g.scriptUri;
                            m.parentNode.insertBefore(a, m);
                        }
                    )
                    (window, document, "script", sequraConfigParams, "Sequra", "onLoad");
                }
            }

            events.on("sequra_core:renderAfter", function (args) {
                let errors = document.getElementsByClassName('sequra-widgets-error');

                if (sequraConfigParams === null) {
                    [...errors].forEach((el) => {
                        el.style.display = "block";
                    });
                } else {
                    let sequraDiv = document.getElementById(args.id).firstElementChild,
                        childNodes = sequraDiv.querySelectorAll('.sequra-promotion-widget'),
                        enabledMethods = sequraDiv.getAttribute('data-payment-method').split(',');

                    if (enabledMethods[0] !== '') {
                        childNodes.forEach((node) => {
                            node.remove();
                        });

                        enabledMethods.forEach((method) => {
                            let newElement = document.createElement('div');
                            newElement.classList.add('sequra-promotion-widget');
                            newElement.style.minWidth = '277px';
                            newElement.style.height = 'min-content';
                            newElement.style.paddingBottom = '20px';
                            newElement.setAttribute('data-amount', "15000");
                            newElement.setAttribute('data-product', method);

                            let campaign = '';

                            window.sequraConfigParams.products.map((product) => {
                                if (product.id === method) {
                                    campaign = product.campaign;
                                }
                            });

                            newElement.setAttribute('data-campaign', campaign);

                            sequraDiv.appendChild(newElement);
                        })
                    }
                    if (enabledMethods[0] === '' && childNodes.length === 0) {
                        let select = $('select[name="store_id"]')[0],
                            rendered = false;

                        [...select.selectedOptions].every((option) => {
                            if ((option.value === '0' && window.sequraConfigParams.enabledStores.length > 0) ||
                                window.sequraConfigParams.enabledStores.includes(option.value)) {
                                let newElement = document.createElement('div'),
                                    enabledMethodsForStore = option.value === '0' ?
                                        window.sequraConfigParams.methodsPerStore[Object.keys(window.sequraConfigParams.methodsPerStore)[0]] :
                                        window.sequraConfigParams.methodsPerStore[select.selectedOptions[0].value];

                                newElement.classList.add('sequra-promotion-widget');
                                newElement.style.minWidth = '277px';
                                newElement.style.height = 'min-content';
                                newElement.style.paddingBottom = '20px';
                                newElement.setAttribute('data-amount', "15000");
                                newElement.setAttribute('data-product', enabledMethodsForStore[0].product);
                                newElement.setAttribute('data-campaign', enabledMethodsForStore[0].campaign);

                                sequraDiv.setAttribute('data-payment-method', enabledMethodsForStore[0].product);
                                sequraDiv.appendChild(newElement);
                                rendered = true;

                                return false;
                            }
                        });

                        if (!rendered) {
                            [...errors].forEach((el) => {
                                el.style.display = "block";
                            });
                        }
                    }
                }

                if (typeof Sequra !== "undefined") {
                    Sequra.refreshComponents?.();
                }
            });
        }

        if (window.sequraConfigParams === undefined) {
            let spinner = document.getElementsByClassName('sequra-spinner')[0];
            if (spinner) {
                spinner.style.display = 'block';
            }

            $.ajax({
                    url: this.config.additional_data.previewConfig.widgetConfig.sequraUrl
                }
            ).done(function (data) {
                window.sequraConfigParams = data.widgetConfig;
                initializeWidgets();
                handleClickEvent();
                me.bindEvents();
                if (spinner) {
                    spinner.style.display = 'none';
                }
            });
        } else {
            initializeWidgets();
        }
    }

    var $super = PreviewBase.prototype;

    Preview.prototype = Object.create(PreviewBase.prototype);

    let handleDefaultMethod = function (sequraWidgetElement) {
        let select = $('select[name="store_id"]')[0],
            enabledMethodsForStore = select.selectedOptions[0].value === '0' ?
                window.sequraConfigParams.methodsPerStore[Object.keys(window.sequraConfigParams.methodsPerStore)[0]] :
                window.sequraConfigParams.methodsPerStore[select.selectedOptions[0].value],
            widgetMethods = sequraWidgetElement.getAttribute('data-payment-method').split(',');


        if (widgetMethods && widgetMethods[0] !== '') {
            widgetMethods.forEach((method) => {
                if (!enabledMethodsForStore.includes(method)) {
                    method = enabledMethodsForStore[0].product
                }

                let children = sequraWidgetElement.querySelector('[data-product="' + method + '"]');

                if (!children) {
                    let newElement = document.createElement('div');

                    newElement.classList.add('sequra-promotion-widget');
                    newElement.style.minWidth = '277px';
                    newElement.style.height = 'min-content';
                    newElement.style.paddingBottom = '20px';
                    newElement.setAttribute('data-amount', "15000");
                    newElement.setAttribute('data-product', method);
                    let campaign = '';

                    window.sequraConfigParams.products.map((product) => {
                        if (product.id === method) {
                            campaign = product.campaign;
                        }
                    });

                    newElement.setAttribute('data-campaign', campaign);
                    sequraWidgetElement.appendChild(newElement);
                }
            })
            sequraWidgetElement.setAttribute('data-payment-method', widgetMethods);
            Sequra.refreshComponents?.();

            return;
        }

        let newElement = document.createElement('div');

        newElement.classList.add('sequra-promotion-widget');
        newElement.style.minWidth = '277px';
        newElement.style.height = 'min-content';
        newElement.style.paddingBottom = '20px';
        newElement.setAttribute('data-amount', "15000");
        newElement.setAttribute('data-product', enabledMethodsForStore[0].product);
        newElement.setAttribute('data-campaign', enabledMethodsForStore[0].campaign);

        sequraWidgetElement.setAttribute('data-payment-method', enabledMethodsForStore[0].product);
        sequraWidgetElement.appendChild(newElement);

        Sequra.refreshComponents?.();
    };
    let handleClickEvent = function () {
        let select = $('select[name="store_id"]')[0],
            storeConfigured = false;

        if (window.sequraConfigParams.enabledStores !== undefined) {
            [...select.selectedOptions].forEach((option) => {
                if ((option.value === '0' && window.sequraConfigParams.enabledStores.length > 0) ||
                    window.sequraConfigParams.enabledStores.includes(option.value)) {
                    storeConfigured = true;
                }
            });
        }

        let errors = document.getElementsByClassName('sequra-widgets-error'),
            widgets = document.getElementsByClassName('sequra-promotion-widget'),
            availableMethodsForStore = select.selectedOptions[0].value === '0' ?
                window.sequraConfigParams.methodsPerStore[Object.keys(window.sequraConfigParams.methodsPerStore)[0]] :
                window.sequraConfigParams.methodsPerStore[select.selectedOptions[0].value];

        availableMethodsForStore = availableMethodsForStore !== undefined ? availableMethodsForStore.map(a => a.product) : [];

        if (!storeConfigured) {
            [...errors].forEach((el) => {
                el.style.display = "block";
            });

            [...widgets].forEach((el) => {
                el.style.display = "none";
            })
        } else {
            if (errors.length > 0 && widgets.length === 0) {
                [...errors].forEach((el) => {
                    handleDefaultMethod(el.parentElement);
                });
            }

            [...errors].forEach((el) => {
                el.style.display = "none";
            });

            let rendered = false;

            [...widgets].forEach((el) => {
                let enabledMethodsForStore = el.parentElement.getAttribute('data-payment-method').split(','),
                    parent = el.parentElement;

                if (enabledMethodsForStore[0] === '') {
                    handleDefaultMethod(parent);
                    rendered = true;
                } else {
                    let children = parent.getElementsByClassName('sequra-promotion-widget');

                    [...children].forEach((child) => {
                        if (!enabledMethodsForStore.includes(child.getAttribute('data-product')) ||
                            !availableMethodsForStore.includes(child.getAttribute('data-product'))) {
                            child.remove();
                        }
                    })

                    enabledMethodsForStore.forEach((method) => {
                        let widgetExists = false;
                        [...children].forEach((child) => {
                            if (method === child.getAttribute('data-product') && availableMethodsForStore.includes(method)) {
                                widgetExists = true;
                                child.style.display = "block";
                                rendered = true;
                            }
                        })

                        if (!widgetExists && availableMethodsForStore.includes(method)) {
                            let newElement = document.createElement('div');

                            newElement.classList.add('sequra-promotion-widget');
                            newElement.style.minWidth = '277px';
                            newElement.style.height = 'min-content';
                            newElement.style.paddingBottom = '20px';
                            newElement.setAttribute('data-amount', "15000");
                            newElement.setAttribute('data-product', method);
                            let campaign = '';

                            window.sequraConfigParams.products.map((product) => {
                                if (product.id === method) {
                                    campaign = product.campaign;
                                }
                            });

                            newElement.setAttribute('data-campaign', campaign);
                            parent.appendChild(newElement);
                            rendered = true;
                        }
                    })
                }
            });

            if (!rendered) {
                [...errors].forEach((el) => {
                    handleDefaultMethod(el.parentElement);
                });
            }

            Sequra.refreshComponents?.();
        }
    }

    /**
     * Bind any events required for the content type to function
     */
    Preview.prototype.bindEvents = function () {
        let select = $('select[name="store_id"]')[0];

        PreviewBase.prototype.bindEvents.call(this);

        $('.pagebuilder-wysiwyg-overlay')[0].on('click', handleClickEvent);

        $('.action-default')[1].on('click', handleClickEvent);

        select.on('change', handleClickEvent);
    };

    /**
     * Determine if the WYSIWYG editor is supported
     *
     * @returns {boolean}
     */
    Preview.prototype.isWysiwygSupported = function () {
        return Config.getConfig("can_use_inline_editing_on_stage");
    };

    /**
     * Init the WYSIWYG component
     *
     * @param {HTMLElement} element
     */
    Preview.prototype.initWysiwyg = function (element) {
        var self = this;
        var config = this.config.additional_data.wysiwygConfig.wysiwygConfigData;

        this.element = element;
        element.id = this.contentType.id + "-editor";

        config.adapter.settings.fixed_toolbar_container =
            "#" + this.contentType.id + " .quote-description-text-content";

        WysiwygFactory(
            this.contentType.id,
            element.id,
            this.config.name,
            config,
            this.contentType.dataStore,
            "description",
            this.contentType.stageId
        ).then(function (wysiwyg) {
            self.wysiwyg = wysiwyg;
        });
    };

    /**
     * Stop event to prevent execution of action when editing text area
     *
     * @returns {boolean}
     */
    Preview.prototype.stopEvent = function () {
        event.stopPropagation();
        return true;
    };

    /**
     * Modify the options returned by the content type
     *
     * @returns {*}
     */
    Preview.prototype.retrieveOptions = function () {
        var options = $super.retrieveOptions.call(this, arguments);

        return options;
    };

    Preview.prototype.openEdit = function openEdit() {
        window.currentWidgetId = this.contentType.id;

        return this.edit.open();
    }

    return Preview;
});
