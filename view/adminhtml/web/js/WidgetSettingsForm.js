if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef WidgetLabels
     * @property {string|null} message
     * @property {string|null} messageBelowLimit
     */

    /**
     * @typedef WidgetSettings
     * @property {boolean} useWidgets
     * @property {string|null} assetsKey
     * @property {boolean} displayWidgetOnProductPage
     * @property {boolean} showInstallmentAmountInProductListing
     * @property {boolean} showInstallmentAmountInCartPage
     * @property {WidgetLabels|null} widgetLabels
     * @property {string[]|null} widgetStyles
     */

    /**
     * Handles widgets settings form logic.
     *
     * @param {{
     * widgetSettings: WidgetSettings,
     * connectionSettings: ConnectionSettings,
     * countrySettings: CountrySettings[],
     * paymentMethods: PaymentMethod[]
     * }} data
     * @param {{
     * saveWidgetSettingsUrl: string,
     * getPaymentMethodsUrl: string,
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

        const miniWidgetLabels = {
            messages: {
                "ES": "Desde %s/mes",
                "FR": "À partir de %s/mois",
                "IT": "Da %s/mese",
                "PT": "De %s/mês"
            },
            messagesBelowLimit: {
                "ES": "Fracciona a partir de %s",
                "FR": "Fraction de %s",
                "IT": "Frazione da %s",
                "PT": "Fração de %s"
            }
        }

        /** @type WidgetSettings */
        const defaultFormData = {
            useWidgets: false,
            assetsKey: '',
            displayWidgetOnProductPage: false,
            widgetLabels: {
                message: '',
                messageBelowLimit: ''
            },
            widgetStyles: '{"alignment":"center","amount-font-bold":"true","amount-font-color":"#1C1C1C","amount-font-size":"15","background-color":"white","border-color":"#B1AEBA","border-radius":"","class":"","font-color":"#1C1C1C","link-font-color":"#1C1C1C","link-underline":"true","no-costs-claim":"","size":"M","starting-text":"only","type":"banner"}',
            showInstallmentAmountInProductListing: false,
            showInstallmentAmountInCartPage: false,
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
                generator.createToggleField({
                    value: changedSettings.showInstallmentAmountInCartPage,
                    label: 'widgets.showInCartPage.label',
                    description: 'widgets.showInCartPage.description',
                    onChange: (value) => handleChange('showInstallmentAmountInCartPage', value)
                }),
                generator.createToggleField({
                    value: changedSettings.showInstallmentAmountInProductListing,
                    label: 'widgets.showInProductListing.label',
                    description: 'widgets.showInProductListing.description',
                    onChange: (value) => handleChange('showInstallmentAmountInProductListing', value)
                })
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

            renderLabelsConfiguration();
        }

        const renderLabelsConfiguration = () => {
            if (!changedSettings.showInstallmentAmountInProductListing) {
                return;
            }

            const pageInnerContent = document.querySelector('.sq-content-inner');

            if (!changedSettings.widgetLabels.message) {
                changedSettings.widgetLabels.message = miniWidgetLabels.messages.hasOwnProperty(SequraFE.adminLanguage) ?
                    miniWidgetLabels.messages[SequraFE.adminLanguage] : miniWidgetLabels.messages['ES'];
            }

            if (!changedSettings.widgetLabels.messageBelowLimit) {
                changedSettings.widgetLabels.messageBelowLimit = miniWidgetLabels.messagesBelowLimit.hasOwnProperty(SequraFE.adminLanguage) ?
                    miniWidgetLabels.messagesBelowLimit[SequraFE.adminLanguage] : miniWidgetLabels.messagesBelowLimit['ES'];
            }

            pageInnerContent?.append(
                generator.createTextField({
                    name: 'labels-message',
                    value: changedSettings.widgetLabels.message,
                    className: 'sq-text-input',
                    label: 'widgets.teaserMessage.label',
                    description: 'widgets.teaserMessage.description',
                    onChange: (value) => handleLabelChange('message', value)
                }),
                generator.createTextField({
                    name: 'labels-message-below-limit',
                    value: changedSettings.widgetLabels.messageBelowLimit,
                    className: 'sq-text-input',
                    label: 'widgets.messageBelowLimit.label',
                    description: 'widgets.messageBelowLimit.description',
                    onChange: (value) => handleLabelChange('messageBelowLimit', value)
                })
            );
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
        }

        const handleLabelChange = (name, value) => {
            changedSettings['widgetLabels'][name] = value;
            disableFooter(false);
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

                if (changedSettings.showInstallmentAmountInProductListing) {
                    valid = validator.validateRequiredField(
                        document.querySelector('[name="labels-message"]'),
                        'validation.requiredField'
                    ) && valid;

                    valid = validator.validateRequiredField(
                        document.querySelector('[name="labels-message-below-limit"]'),
                        'validation.requiredField'
                    ) && valid;
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
            customHeader = {
                'Content-Type': 'text/plain'
            };
            return api.get(validationUrl, null, customHeader).then(() => true).catch(() => false)
        }
    }

    SequraFE.WidgetSettingsForm = WidgetSettingsForm;
})();
