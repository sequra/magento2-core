if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef CategoryPaymentMethod
     * @property {string|null} category
     * @property {string|null} product
     * @property {string|null} title
     */

    /**
     * @typedef Category
     * @property {CategoryPaymentMethod[]} paymentMethods
     */

    /**
     * @typedef WidgetLocation
     * @property {string|null} selForTarget
     * @property {string|null} product
     * @property {boolean} displayWidget
     * @property {string|null} widgetStyles
     */

    /**
     * @typedef WidgetSettings
     * @property {boolean} useWidgets
     * @property {string|null} assetsKey
     * @property {boolean} displayWidgetOnProductPage
     * @property {boolean} showInstallmentAmountInProductListing
     * @property {boolean} showInstallmentAmountInCartPage
     * @property {string|null} widgetStyles
     *
     * @property {string|null} productPriceSelector
     * @property {string|null} altProductPriceSelector
     * @property {string|null} altProductPriceTriggerSelector
     * @property {string|null} defaultProductLocationSelector
     * @property {WidgetLocation[]} customLocations
     *
     * @property {string|null} cartPriceSelector
     * @property {string|null} cartLocationSelector
     * @property {string|null} widgetOnCartPage
     *
     * @property {string|null} listingPriceSelector
     * @property {string|null} listingLocationSelector
     * @property {string|null} widgetOnListingPage
     */

    /**
     * Handles widgets settings form logic.
     *
     * @param {{
     * widgetSettings: WidgetSettings,
     * connectionSettings: ConnectionSettings,
     * countrySettings: CountrySettings[],
     * paymentMethods: PaymentMethod[],
     * allAvailablePaymentMethods: Category[],
     * }} data
     * @param {{
     * saveWidgetSettingsUrl: string,
     * getPaymentMethodsUrl: string,
     * getAllPaymentMethodsUrl: string,
     * page: string,
     * appState: string,
     * }} configuration
     * @constructor
     */
    function WidgetSettingsForm(data, configuration) {
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;

        const {
            elementGenerator: generator,
            validationService: validator,
            utilities
        } = SequraFE;

        /** @type WidgetSettings */
        let activeSettings;
        /** @type WidgetSettings */
        let changedSettings;
        /** @type string[] */
        let paymentMethodIds;
        /** @type boolean */
        let isAssetKeyValid = false;
        /** @type {Category[]} */
        let allAvailablePaymentMethods = data.allAvailablePaymentMethods;
        /** @type {CategoryPaymentMethod[]} */
        let payNowPaymentMethods = allAvailablePaymentMethods.pay_now ?? [];
        /** @type {CategoryPaymentMethod[]} */
        let payLaterPaymentMethods = allAvailablePaymentMethods.pay_later ?? [];
        /** @type {CategoryPaymentMethod[]} */
        let partPaymentPaymentMethods = allAvailablePaymentMethods.part_payment ?? [];
        /** @type {CategoryPaymentMethod[]} */
        let partAndLaterPaymentMethods = partPaymentPaymentMethods.concat(payLaterPaymentMethods);

        /** @type WidgetSettings */
        const defaultFormData = {
            useWidgets: false,
            assetsKey: '',
            displayWidgetOnProductPage: false,
            widgetStyles: '{"alignment":"center","amount-font-bold":"true","amount-font-color":"#1C1C1C","amount-font-size":"15","background-color":"white","border-color":"#B1AEBA","border-radius":"","class":"","font-color":"#1C1C1C","link-font-color":"#1C1C1C","link-underline":"true","no-costs-claim":"","size":"M","starting-text":"only","type":"banner"}',
            showInstallmentAmountInProductListing: false,
            showInstallmentAmountInCartPage: false,
            productPriceSelector: '.price-container .price',
            altProductPriceSelector: '[data-price-type="finalPrice"] .price',
            altProductPriceTriggerSelector: '.bundle-actions',
            defaultProductLocationSelector: '.actions .action.primary.tocart',
            customLocations: [
                {
                    "displayWidget": false,
                    "selForTarget": ".cart",
                    "widgetStyles": "",
                    "product": "i1"
                },
                {
                    "displayWidget": true,
                    "selForTarget": "trtrh",
                    "widgetStyles": "",
                    "product": "pp3"
                }
            ],
            cartPriceSelector: '.grand.totals .price',
            cartLocationSelector: '.cart-summary',
            widgetOnCartPage: partAndLaterPaymentMethods.length > 0 ? partAndLaterPaymentMethods[0]['product'] : '',
            listingPriceSelector: '.price-box.price-final_price .price',
            listingLocationSelector: '.price-box.price-final_price',
            widgetOnListingPage: partPaymentPaymentMethods.length > 0 ? partPaymentPaymentMethods[0]['product'] : ''
        };

        /**
         * Handles form rendering.
         */
        this.render = () => {
            if (!activeSettings) {
                activeSettings = utilities.cloneObject(defaultFormData);
                for (let key in activeSettings) {
                    activeSettings[key] = data?.widgetSettings?.[key] ?? defaultFormData[key];
                }
            }

            paymentMethodIds = data.paymentMethods?.map((paymentMethod) => paymentMethod.product);
            isAssetKeyValid = activeSettings.assetsKey && activeSettings.assetsKey.length !== 0;
            changedSettings = utilities.cloneObject(activeSettings)
            initForm();

            disableFooter(true);
            utilities.hideLoader();
        }

        /**
         * Initializes the form structure.
         */
        const initForm = () => {
            const pageContent = document.querySelector('.sq-content');
            pageContent?.append(
                generator.createElement('div', 'sq-content-inner', '', null, [
                    generator.createElement('div', 'sqp-flash-message-wrapper'),
                    generator.createPageHeading({
                        title: `widgets.title.${configuration.appState}`,
                        text: 'widgets.description'
                    }),
                    generator.createRadioGroupField({
                        value: changedSettings.useWidgets,
                        label: 'widgets.usePromotionalComponents.label',
                        options: [
                            {label: 'widgets.usePromotionalComponents.options.yes', value: true},
                            {label: 'widgets.usePromotionalComponents.options.no', value: false}
                        ],
                        onChange: (value) => handleChange('useWidgets', value)
                    })
                ])
            );

            renderAssetsKeyField();
            renderAdditionalSettings();
            renderControls();
            showOrHideRelatedFields('.sq-product-related-field', changedSettings.displayWidgetOnProductPage);
            showOrHideRelatedFields('.sq-cart-related-field', changedSettings.showInstallmentAmountInCartPage);
            showOrHideRelatedFields('.sq-listing-related-field', changedSettings.showInstallmentAmountInProductListing);
        }

        /**
         * Renders the assets key field.
         */
        const renderAssetsKeyField = () => {
            const pageInnerContent = document.querySelector('.sq-content-inner');
            if (changedSettings.useWidgets) {
                pageInnerContent?.append(
                    generator.createTextField({
                        name: 'assets-key-input',
                        value: changedSettings.assetsKey,
                        className: 'sq-text-input',
                        label: 'widgets.assetKey.label',
                        description: 'widgets.assetKey.description',
                        onChange: (value) => handleChange('assetsKey', value)
                    })
                );

                if (changedSettings.assetsKey?.length !== 0) {
                    validator.validateField(
                        document.querySelector('[name="assets-key-input"]'),
                        !isAssetKeyValid,
                        'validation.invalidField'
                    );
                }
            }
        }

        /**
         * Renders additional widget settings.
         */
        const renderAdditionalSettings = () => {
            if (!changedSettings.useWidgets || !isAssetKeyValid) {
                return;
            }

            const pageInnerContent = document.querySelector('.sq-content-inner');

            pageInnerContent?.append(
                generator.createTextArea(
                    {
                        name: 'widget-configurator-input',
                        className: 'sq-text-input sq-text-area',
                        label: 'widgets.configurator.label',
                        description: 'widgets.configurator.description.start',
                        value: changedSettings.widgetStyles,
                        onChange: (value) => handleChange('widgetStyles', value),
                        rows: 10
                    }
                ),
                generator.createToggleField({
                    value: changedSettings.displayWidgetOnProductPage,
                    label: 'widgets.displayOnProductPage.label',
                    description: 'widgets.displayOnProductPage.description',
                    onChange: (value) => handleChange('displayWidgetOnProductPage', value)
                }),
                // Product widget related fields
                generator.createTextField({
                    value: changedSettings.productPriceSelector,
                    name: 'productPriceSelector',
                    className: 'sq-text-input sq-product-related-field',
                    label: 'widgets.productPriceSelector.label',
                    description: 'widgets.productPriceSelector.description',
                    onChange: (value) => handleChange('productPriceSelector', value)
                }),
                generator.createTextField({
                    value: changedSettings.altProductPriceSelector,
                    name: 'altProductPriceSelector',
                    className: 'sq-text-input sq-product-related-field',
                    label: 'widgets.altProductPriceSelector.label',
                    description: 'widgets.altProductPriceSelector.description',
                    onChange: (value) => handleChange('altProductPriceSelector', value)
                }),
                generator.createTextField({
                    value: changedSettings.altProductPriceTriggerSelector,
                    name: 'altProductPriceTriggerSelector',
                    className: 'sq-text-input sq-product-related-field',
                    label: 'widgets.altProductPriceTriggerSelector.label',
                    description: 'widgets.altProductPriceTriggerSelector.description',
                    onChange: (value) => handleChange('altProductPriceTriggerSelector', value)
                }),
                generator.createTextField({
                    value: changedSettings.defaultProductLocationSelector,
                    name: 'defaultProductLocationSelector',
                    className: 'sq-text-input sq-product-related-field',
                    label: 'widgets.defaultProductLocationSelector.label',
                    description: 'widgets.defaultProductLocationSelector.description',
                    onChange: (value) => handleChange('defaultProductLocationSelector', value)
                }),
                generator.createElement('div', 'sq-field-wrapper sq-locations-container sq-product-related-field'),
                // End of product widget related fields
                // Cart widget related fields
                generator.createToggleField({
                    value: changedSettings.showInstallmentAmountInCartPage,
                    label: 'widgets.showInCartPage.label',
                    description: 'widgets.showInCartPage.description',
                    onChange: (value) => handleChange('showInstallmentAmountInCartPage', value)
                }),

                generator.createTextField({
                    value: changedSettings.cartPriceSelector,
                    name: 'cartPriceSelector',
                    className: 'sq-text-input sq-cart-related-field',
                    label: 'widgets.cartPriceSelector.label',
                    description: 'widgets.cartPriceSelector.description',
                    onChange: (value) => handleChange('cartPriceSelector', value)
                }),
                generator.createTextField({
                    value: changedSettings.cartLocationSelector,
                    name: 'cartLocationSelector',
                    className: 'sq-text-input sq-cart-related-field',
                    label: 'widgets.cartDefaultLocationSel.label',
                    description: 'widgets.cartDefaultLocationSel.description',
                    onChange: (value) => handleChange('cartLocationSelector', value)
                }),

                partAndLaterPaymentMethods.length > 0 ? generator.createDropdownField({
                    name: 'widgetOnCartPage',
                    className: 'sqm--table-dropdown sq-cart-related-field',
                    label: 'widgets.widgetOnCartPage.label',
                    description: 'widgets.widgetOnCartPage.description',
                    value: changedSettings.widgetOnCartPage,
                    options: partAndLaterPaymentMethods.map((paymentMethod) => {
                        return {
                            label: paymentMethod.title, value: paymentMethod.product
                        }
                    }),
                    onChange: (value) => handleChange('widgetOnCartPage', value)
                }) : [],
                // End of cart widget related fields
                // Product listing widget related fields
                generator.createToggleField({
                    value: changedSettings.showInstallmentAmountInProductListing,
                    label: 'widgets.showInProductListing.label',
                    description: 'widgets.showInProductListing.description',
                    onChange: (value) => handleChange('showInstallmentAmountInProductListing', value)
                }),

                generator.createTextField({
                    value: changedSettings.listingPriceSelector,
                    name: 'listingPriceSelector',
                    className: 'sq-text-input sq-listing-related-field',
                    label: 'widgets.listingPriceSelector.label',
                    description: 'widgets.listingPriceSelector.description',
                    onChange: (value) => handleChange('listingPriceSelector', value)
                }),
                generator.createTextField({
                    value: changedSettings.listingLocationSelector,
                    name: 'listingLocationSelector',
                    className: 'sq-text-input sq-listing-related-field',
                    label: 'widgets.listingLocationSelector.label',
                    description: 'widgets.listingLocationSelector.description',
                    onChange: (value) => handleChange('listingLocationSelector', value)
                }),
                partPaymentPaymentMethods.length > 0 ? generator.createDropdownField({
                    name: 'widgetOnListingPage',
                    className: 'sqm--table-dropdown sq-listing-related-field',
                    label: 'widgets.widgetOnListingPage.label',
                    description: 'widgets.widgetOnListingPage.description',
                    value: changedSettings.widgetOnListingPage,
                    options: partPaymentPaymentMethods.map((paymentMethod) => {
                        return {
                            label: paymentMethod.title, value: paymentMethod.product
                        }
                    }),
                    onChange: (value) => handleChange('widgetOnListingPage', value)
                }) : [],
                // End of product listing widget related fields
            )

            document.querySelector('.sqp-textarea-field .sqp-field-subtitle').append(
                generator.createButtonLink({
                    className: 'sq-link-button',
                    text: 'widgets.configurator.description.link',
                    href: 'https://live.sequracdn.com/assets/static/simulator.html',
                    openInNewTab: true
                }),
                generator.createElement('span', '', 'widgets.configurator.description.end'),
            )

            renderLocations();
        }

        const showOrHideRelatedFields = (relatedFieldClass, show) => {
            const selector = `.sq-field-wrapper:has(${relatedFieldClass}),.sq-field-wrapper${relatedFieldClass}`;
            const hiddenClass = 'sqs--hidden';
            document.querySelectorAll(selector).forEach((el) => {
                if (show) {
                    el.classList.remove(hiddenClass)
                } else {
                    el.classList.add(hiddenClass)
                }
            });
        }

        const renderLocations = () => {
            new SequraFE.RepeaterFieldsComponent({
                containerSelector: '.sq-locations-container',
                data: changedSettings.customLocations,
                getHeaders: () => [
                    {
                        title: SequraFE.translationService.translate('widgets.locations.headerTitle'),
                        description: SequraFE.translationService.translate('widgets.locations.headerDescription')
                    },
                ],
                getRowContent: (data) => {
                    let displayWidget = true;
                    if (data && 'undefined' !== typeof data.displayWidget) {
                        displayWidget = data.displayWidget;
                    }

                    return `
                    <div class="sq-table__row-field-wrapper sq-table__row-field-wrapper--grow sq-table__row-field-wrapper--space-between">
                       <h3 class="sqp-field-title">${SequraFE.translationService.translate('widgets.displayOnProductPage.label')}
                       <label class="sq-toggle"><input class="sqp-toggle-input" type="checkbox" ${displayWidget ? 'checked' : ''}><span class="sqp-toggle-round"></span></label>
                       </h3>
                       <span class="sqp-field-subtitle">${SequraFE.translationService.translate('widgets.displayOnProductPage.description')}</span>
                    </div>

                     <div class="sq-table__row-field-wrapper sq-table__row-field-wrapper--grow">
                        <label class="sq-table__row-field-label">${SequraFE.translationService.translate('widgets.locations.selector')}</label>
                        <span class="sqp-field-subtitle">${SequraFE.translationService.translate('widgets.locations.leaveEmptyToUseDefault')}</span>
                        <input class="sq-table__row-field" type="text" value="${data && 'undefined' !== typeof data.selForTarget ? data.selForTarget : ''}">
                    </div>
                    <div class="sq-table__row-field-wrapper sq-table__row-field-wrapper--grow">
                    <label class="sq-table__row-field-label">${SequraFE.translationService.translate('widgets.configurator.label')}</label>
                    <span class="sqp-field-subtitle">${SequraFE.translationService.translate('widgets.configurator.description.start')}<a class="sq-link-button" href="https://live.sequracdn.com/assets/static/simulator.html" target="_blank"><span>${SequraFE.translationService.translate('widgets.configurator.description.link')}</span></a><span>${SequraFE.translationService.translate('widgets.configurator.description.end')} ${SequraFE.translationService.translate('widgets.locations.leaveEmptyToUseDefault')}</span></span>
                    <textarea class="sqp-field-component sq-text-input sq-text-area" rows="5">${data && 'undefined' !== typeof data.widgetStyles ? data.widgetStyles : ''}</textarea>
                    </div>
                    `
                },
                getRowHeader: (data) => {
                    let selectedFound = false;
                    return `
                    <div class="sq-table__row-field-wrapper">
                        <label class="sq-table__row-field-label">${SequraFE.translationService.translate('widgets.locations.paymentMethod')}</label>
                        <select class="sq-table__row-field">${partAndLaterPaymentMethods ? partAndLaterPaymentMethods.map((pm, idx) => {
                        let selected = '';
                        if(!selectedFound && data && data.product === pm.product) {
                            selected = ' selected';
                            selectedFound = true;
                        }

                        return `<option key="${idx}" data-product="${pm.product}"${ selected}>${pm.title}</option>`;
                    }).join('') : ''
                    }
                        </select>
                    </div>
                   `
                },
                handleChange: table => {
                    const customLocations = [];
                    table.querySelectorAll('.sq-table__row').forEach(row => {
                        const select = row.querySelector('select');
                        const selForTarget = row.querySelector('input[type="text"]').value;
                        const widgetStyles = row.querySelector('textarea').value;
                        const displayWidget = row.querySelector('input[type="checkbox"]').checked;
                        const dataset = select.selectedIndex === -1 ? null : select.options[select.selectedIndex].dataset;

                        const product = dataset && 'undefined' !== typeof dataset.product ? dataset.product : null;
                        customLocations.push({ selForTarget, product, widgetStyles, displayWidget });
                    });
                    handleChange('customLocations', customLocations)
                },
                addRowText: 'widgets.locations.addRow',
                removeRowText: 'widgets.locations.removeRow',
            });
        }

        /**
         * Renders form controls.
         */
        const renderControls = () => {
            const pageContent = document.querySelector('.sq-content');
            const pageInnerContent = document.querySelector('.sq-content-inner');

            if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                pageInnerContent?.append(
                    generator.createButtonField({
                        className: 'sq-controls sqm--block',
                        buttonType: 'primary',
                        buttonLabel: 'general.continue',
                        onClick: handleSave
                    })
                )

                return;
            }

            pageContent?.append(
                generator.createPageFooter({
                    onSave: handleSave,
                    onCancel: () => {
                        utilities.showLoader();
                        const pageContent = document.querySelector('.sq-content');
                        while (pageContent?.firstChild) {
                            pageContent?.removeChild(pageContent?.firstChild);
                        }

                        this.render();
                    }
                })
            );
        }

        const isCssSelectorValid = selector => {
            try {
                document.querySelector(selector);
                return true;
            } catch {
                return false;
            }
        }

        const isCustomLocationValid = value => {
            try {
                value.forEach(location => {
                    if ('' !== location.selForTarget && !isCssSelectorValid(location.selForTarget)) {
                        throw new Error('Invalid selector');
                    }
                    if ('' !== location.widgetStyles && !isJSONValid(location.widgetStyles)) {
                        throw new Error('Invalid selector');
                    }
                    if (!partAndLaterPaymentMethods.some(pm => pm.product === location.product)) {
                        throw new Error('Invalid payment method');
                    }
                    // Check if exists other location with the same product
                    if (value.filter(l => l.product === location.product).length > 1) {
                        throw new Error('Duplicated entry found');
                    }
                });
                return true;
            } catch {
                return false;
            }
        }

        /**
         * Handles the form input changes.
         *
         * @param name
         * @param value
         */
        const handleChange = (name, value) => {
            changedSettings[name] = value;
            disableFooter(false);

            if (name === 'useWidgets' || name === 'showInstallmentAmountInProductListing') {
                refreshForm();
            }

            if (name === 'widgetStyles') {
                if (!validator.validateJson(
                    document.querySelector('[name="widget-configurator-input"]'),
                    value,
                    'validation.invalidJSON'
                )) {
                    disableFooter(true);
                }
            }

            if (name === 'assetsKey') {
                utilities.showLoader();
                isAssetsKeyValid()
                    .then((isValid) => {
                        isAssetKeyValid = isValid;
                        refreshForm();
                        validator.validateField(
                            document.querySelector('[name="assets-key-input"]'),
                            !isValid,
                            'validation.invalidField'
                        );
                    })
                    .finally(utilities.hideLoader);
            }

            if (name === 'displayWidgetOnProductPage') {
                showOrHideRelatedFields('.sq-product-related-field', value);
            }
            if (name === 'showInstallmentAmountInCartPage') {
                showOrHideRelatedFields('.sq-cart-related-field', value);
            }
            if (name === 'showInstallmentAmountInProductListing') {
                showOrHideRelatedFields('.sq-listing-related-field', value);
            }

            if (['productPriceSelector', 'defaultProductLocationSelector', 'altProductPriceSelector', 'altProductPriceTriggerSelector', 'cartPriceSelector', 'cartLocationSelector', 'listingPriceSelector', 'listingLocationSelector'].includes(name)) {
                const required = ['productPriceSelector', 'defaultProductLocationSelector', 'cartPriceSelector', 'cartLocationSelector', 'listingPriceSelector', 'listingLocationSelector'];
                const isValid = validator.validateCssSelector(
                    document.querySelector(`[name="${name}"]`),
                    required.includes(name),
                    'validation.invalidField'
                );
                disableFooter(!isValid);
            }

            if (name === 'customLocations') {
                const isValid = isCustomLocationValid(value);
                validator.validateField(
                    document.querySelector(`.sq-product-related-field .sq-table`),
                    !isValid,
                    'validation.invalidField'
                );
                disableFooter(!isValid);
            }
        }

        /**
         * Re-renders the form.
         */
        const refreshForm = () => {
            document.querySelector('.sq-content-inner')?.remove();
            configuration.appState !== SequraFE.appStates.ONBOARDING && document.querySelector('.sq-page-footer').remove();
            initForm();
        }

        /**
         * Handles the saving of the form.
         */
        const handleSave = () => {
            if (changedSettings.useWidgets && changedSettings.assetsKey?.length === 0) {
                validator.validateRequiredField(
                    document.querySelector('[name="assets-key-input"]'),
                    'validation.requiredField'
                );

                return;
            }

            if (changedSettings.useWidgets && !isAssetKeyValid) {
                return;
            }

            if (changedSettings.useWidgets) {
                let valid = isJSONValid(changedSettings.widgetStyles);

                validator.validateField(
                    document.querySelector(`[name="widget-configurator-input"]`),
                    !valid,
                    'validation.invalidJSON'
                );

                if (changedSettings.displayWidgetOnProductPage) {
                    for (const name of ['productPriceSelector', 'defaultProductLocationSelector']) {
                        valid = validator.validateCssSelector(
                            document.querySelector(`[name="${name}"]`),
                            true,
                            'validation.invalidField'
                        ) && valid;
                    }
                    for (const name of ['altProductPriceSelector', 'altProductPriceTriggerSelector']) {
                        valid = validator.validateCssSelector(
                            document.querySelector(`[name="${name}"]`),
                            false,
                            'validation.invalidField'
                        ) && valid;
                    }

                    const isValid = isCustomLocationValid(changedSettings.customLocations);
                    valid = isValid && valid;
                    validator.validateField(
                        document.querySelector(`.sq-product-related-field .sq-table`),
                        !isValid,
                        'validation.invalidField'
                    );
                }

                if (changedSettings.showInstallmentAmountInCartPage) {
                    for (const name of ['cartPriceSelector', 'cartLocationSelector']) {
                        valid = validator.validateCssSelector(
                            document.querySelector(`[name="${name}"]`),
                            true,
                            'validation.invalidField'
                        ) && valid;
                    }
                }

                if (changedSettings.showInstallmentAmountInProductListing) {
                    for (const name of ['listingPriceSelector', 'listingLocationSelector']) {
                        valid = validator.validateCssSelector(
                            document.querySelector(`[name="${name}"]`),
                            true,
                            'validation.invalidField'
                        ) && valid;
                    }
                }

                if (!valid) {
                    return;
                }
            }

            utilities.showLoader();
            api.post(configuration.saveWidgetSettingsUrl, changedSettings)
                .then(() => {
                    if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                        const index = SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.WIDGETS)
                        SequraFE.pages.onboarding.length > index + 1 ?
                            window.location.hash = configuration.appState + '-' + SequraFE.pages.onboarding[index + 1] :
                            window.location.hash = SequraFE.appStates.PAYMENT + '-' + SequraFE.appPages.PAYMENT.METHODS;
                    }

                    activeSettings = utilities.cloneObject(changedSettings);
                    SequraFE.state.setData('widgetSettings', activeSettings);

                    disableFooter(true);
                })
                .finally(utilities.hideLoader);
        }

        /**
         * Disables footer form controls.
         *
         * @param disable
         */
        const disableFooter = (disable) => {
            if (configuration.appState !== SequraFE.appStates.ONBOARDING) {
                utilities.disableFooter(disable);
            }
        }

        /**
         * Validates JSON string.
         *
         * @param jsonString
         *
         * @returns {boolean}
         */
        const isJSONValid = (jsonString) => {
            try {
                JSON.parse(jsonString);

                return true;
            } catch (e) {
                return false
            }
        }

        /**
         * Returns a Promise<boolean> for assets key validation.
         *
         * @returns {Promise<boolean>}
         */
        const isAssetsKeyValid = () => {
            const mode = data.connectionSettings.environment;
            const merchantId = data.countrySettings[0].merchantId;
            const assetsKey = changedSettings.assetsKey;
            const methods = paymentMethodIds.filter((id) => id !== 'i1').join('_');

            const validationUrl =
                `https://${mode}.sequracdn.com/scripts/${merchantId}/${assetsKey}/${methods}_cost.json`;

            let customHeader = {
                'Content-Type': 'text/plain'
            };

            return api.get(validationUrl, null, customHeader).then(() => true).catch(() => false)
        }
    }

    SequraFE.WidgetSettingsForm = WidgetSettingsForm;
})();
