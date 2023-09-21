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
     * countrySettings: CountrySettings[]
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
            utilities,
            pageControllerFactory,
            components
        } = SequraFE;

        /** @type WidgetSettings */
        let activeSettings;
        /** @type WidgetSettings */
        let changedSettings;
        /** @type string[] */
        let paymentMethodIds;
        /** @type boolean */
        let isAssetKeyValid = false;


        /** @type WidgetSettings */
        const defaultFormData = {
            useWidgets: false,
            assetsKey: '',
            displayWidgetOnProductPage: false,
            widgetLabels: {
                message: '',
                messageBelowLimit: ''
            },
            widgetStyles: [],
            showInstallmentAmountInProductListing: false,
            showInstallmentAmountInCartPage: false,
        };

        /**
         * Handles form rendering.
         */
        this.render = () => {
            utilities.showLoader();

            if (!activeSettings) {
                activeSettings = utilities.cloneObject(defaultFormData);
                for (let key in activeSettings) {
                    activeSettings[key] = data?.widgetSettings?.[key] ?? defaultFormData[key];
                }
            }

            isAssetKeyValid = activeSettings.assetsKey && activeSettings.assetsKey.length !== 0;
            changedSettings = utilities.cloneObject(activeSettings)

            api.get(configuration.getPaymentMethodsUrl.replace(encodeURIComponent('{merchantId}'), data.countrySettings[0].merchantId), () => {
            })
                .then((res) => {
                    paymentMethodIds = res.map((paymentMethod) => paymentMethod.product);
                    initForm();
                })
                .finally(() => {
                    disableFooter(true);
                    utilities.hideLoader();
                })
        }

        /**
         * Initializes the form structure.
         */
        const initForm = () => {
            const pageContent = document.querySelector('.sq-content');
            pageContent.append(
                generator.createElement('div', 'sq-content-inner', '', null, [
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
                pageInnerContent.append(
                    generator.createTextField({
                        name: 'assets-key-input',
                        value: changedSettings.assetsKey,
                        className: 'sq-text-input',
                        label: 'widgets.assetKey.label',
                        description: 'widgets.assetKey.description',
                        onChange: (value) => handleChange('assetsKey', value)
                    })
                );
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
            const modal = components.Modal.create({
                title: 'widgets.configurator.modal.title',
                className: `sq-confirm-modal`,
                content: [
                    generator.createPageHeading(
                        {
                            title: '',
                            text: 'Please go to the following link to configure widget styles'
                        }
                    ),
                    generator.createButtonLink(
                        {
                            text: 'SeQura widget configurator',
                            className: 'sq-link-button',
                            href: 'https://live.sequracdn.com/assets/static/simulator.html'
                        }
                    ),
                    generator.createTextField(
                        {
                            className: 'sq-text-input',
                            label: 'Widget configuration',
                            description: '',
                            value: changedSettings.widgetStyles,
                            onChange: (value) => handleChange('widgetStyles', value)
                        }
                    )
                ],
                footer: true,
                canClose: false,
                buttons: [
                    {
                        label: 'general.cancel',
                        type: 'cancel',
                        size: 'medium',
                        onClick: () => modal.close()
                    },
                    {
                        label: 'general.confirm',
                        type: 'secondary',
                        size: 'medium',
                        onClick: () => modal.close()
                    }
                ]
            });

            pageInnerContent.append(
                generator.createToggleField({
                    value: changedSettings.displayWidgetOnProductPage,
                    label: 'widgets.displayOnProductPage.label',
                    description: 'widgets.displayOnProductPage.description',
                    onChange: (value) => handleChange('displayWidgetOnProductPage', value)
                }),
                generator.createButtonField({
                    className: 'sqm--block',
                    buttonType: 'secondary',
                    buttonLabel: 'widgets.configurator.buttonLabel',
                    onClick: modal.open
                }),

                generator.createToggleField({
                    value: changedSettings.showInstallmentAmountInProductListing,
                    label: 'widgets.showInProductListing.label',
                    description: 'widgets.showInProductListing.description',
                    onChange: (value) => handleChange('showInstallmentAmountInProductListing', value)
                }),
                generator.createToggleField({
                    value: changedSettings.showInstallmentAmountInCartPage,
                    label: 'widgets.showInCartPage.label',
                    description: 'widgets.showInCartPage.description',
                    onChange: (value) => handleChange('showInstallmentAmountInCartPage', value)
                }),
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
            )
        }

        /**
         * Renders form controls.
         */
        const renderControls = () => {
            const pageContent = document.querySelector('.sq-content');
            const pageInnerContent = document.querySelector('.sq-content-inner');

            if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                pageInnerContent.append(
                    generator.createButtonField({
                        className: 'sq-controls sqm--block',
                        buttonType: 'primary',
                        buttonLabel: 'general.continue',
                        onClick: handleSave
                    })
                )

                return;
            }

            pageContent.append(
                generator.createPageFooter({
                    onSave: handleSave,
                    onCancel: () => {
                        const pageContent = document.querySelector('.sq-content');
                        while (pageContent.firstChild) {
                            pageContent.removeChild(pageContent.firstChild);
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

            if (name === 'useWidgets') {
                refreshForm();
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
            document.querySelector('.sq-content-inner').remove();
            configuration.appState !== SequraFE.appStates.ONBOARDING && document.querySelector('.sq-page-footer').remove();
            initForm();
        }

        /**
         * Handles the saving of the form.
         */
        const handleSave = () => {
            if (changedSettings.useWidgets && !isAssetKeyValid) {
                return;
            }

            utilities.showLoader();
            api.post(configuration.saveWidgetSettingsUrl, changedSettings)
                .then(() => {
                    if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                        const index = SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.WIDGETS)
                        pageControllerFactory.getInstance(SequraFE.appStates.ONBOARDING).setDoneStep(index + 1);

                        SequraFE.pages.onboarding.length > index + 1 ?
                            window.location.hash = configuration.appState + '-' + SequraFE.pages.onboarding[index + 1] :
                            SequraFE.state.display();
                    }

                    activeSettings = utilities.cloneObject(changedSettings);
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

            return api.get(validationUrl).then(() => true).catch(() => false)
        }
    }

    SequraFE.WidgetSettingsForm = WidgetSettingsForm;
})();
