if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef ConnectionSettings
     * @property {'live' | 'sandbox'} environment
     * @property {string} username
     * @property {string} password
     * @property {boolean} sendStatisticalData
     */

    /**
     * Handles connection settings form logic.
     *
     * @param {{
     * connectionSettings: ConnectionSettings,
     * countrySettings: CountrySettings[]
     * }} data
     * @param {{
     * saveConnectionDataUrl: string,
     * validateConnectionDataUrl: string,
     * disconnectUrl: string,
     * page: string,
     * appState: string
     * }} configuration
     * @constructor
     */
    function ConnectionSettingsForm(data, configuration) {
        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;

        const {
            elementGenerator: generator,
            validationService: validator,
            utilities,
            components
        } = SequraFE;

        let navigateToOnboarding = false;
        /** @type ConnectionSettings */
        let activeSettings;
        /** @type ConnectionSettings */
        let changedSettings;
        /** @type ConnectionSettings */
        const defaultFormData = {
            environment: 'sandbox',
            username: '',
            password: '',
            sendStatisticalData: false
        };

        /**
         * Handles form rendering.
         */
        this.render = () => {
            if (!activeSettings) {
                activeSettings = utilities.cloneObject(defaultFormData);
                for (let key in activeSettings) {
                    activeSettings[key] = data.connectionSettings?.[key] ?? defaultFormData[key];
                }
            }

            changedSettings = utilities.cloneObject(activeSettings);

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
                generator.createElement('div', 'sq-content-inner sqv--connect', '', null, [
                    generator.createElement('div', 'sqp-flash-message-wrapper'),
                    generator.createPageHeading({
                        title: `connection.title.${configuration.appState}`,
                        text: `connection.description.${configuration.appState}`
                    }),
                    generator.createRadioGroupField({
                        value: changedSettings.environment,
                        label: 'connection.environment.label',
                        options: [
                            {label: 'connection.environment.options.live', value: 'live'},
                            {label: 'connection.environment.options.sandbox', value: 'sandbox'}
                        ],
                        onChange: (value) => handleChange('environment', value)
                    }),
                    generator.createTextField({
                        name: 'username-input',
                        value: changedSettings.username,
                        className: 'sq-text-input',
                        label: 'connection.username.label',
                        description: 'connection.username.description',
                        onChange: (value) => handleChange('username', value)
                    }),
                    generator.createPasswordField({
                        name: 'password-input',
                        value: changedSettings.password,
                        className: 'sq-password-input',
                        label: 'connection.password.label',
                        description: 'connection.password.description',
                        onChange: (value) => handleChange('password', value)
                    }),
                ])
            );

            document.querySelector('.sqp-description').append(
                generator.createButtonLink({
                    className: 'sq-link-button',
                    text: 'connection.description.endLink',
                    href: 'https://en.sequra.com/',
                    openInNewTab: true
                })
            )

            renderByAppState();
        }

        /**
         * Renders form parts that are dependant of application state.
         */
        const renderByAppState = () => {
            const pageContent = document.querySelector('.sq-content');
            const pageInnerContent = document.querySelector('.sq-content-inner');

            if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                pageInnerContent?.append(
                    SequraFE.isPromotional ? [] : generator.createCheckboxField({
                        className: 'sq-statistics',
                        value: changedSettings.sendStatisticalData,
                        description: 'connection.sendStatisticalData.description.text',
                        onChange: (value) => handleChange('sendStatisticalData', value)
                    }),
                    generator.createButtonField({
                        className: 'sqm--block',
                        buttonType: 'primary',
                        buttonLabel: 'general.continue',
                        onClick: handleSave
                    })
                );

                !SequraFE.isPromotional && document.querySelector('.sq-statistics .sqp-field-subtitle').append(
                    generator.createButtonLink({
                        className: 'sq-info-button',
                        text: 'connection.sendStatisticalData.description.endLink',
                        href: 'https://en.sequra.com/',
                        openInNewTab: true
                    })
                );

                return;
            }

            pageInnerContent?.append(
                generator.createButtonField({
                    className: 'sqm--block',
                    buttonType: 'danger',
                    buttonSize: 'medium',
                    buttonLabel: 'general.disconnect',
                    onClick: handleDisconnect
                })
            );

            pageContent?.append(
                generator.createPageFooter({
                    onCancel: () => {
                        const pageContent = document.querySelector('.sq-content');
                        while (pageContent?.firstChild) {
                            pageContent?.removeChild(pageContent.firstChild);
                        }

                        this.render();
                    },
                    onSave: handleSave
                })
            );
        }

        /**
         * Validates form inputs.
         *
         * @returns {boolean}
         */
        const isFormValid = () => {
            let errorCount = 0;

            !validator.validateRequiredField(
                document.querySelector('[name="username-input"]'),
                'validation.requiredField'
            ) && errorCount++;

            !validator.validateRequiredField(
                document.querySelector('[name="password-input"]'),
                'validation.requiredField'
            ) && errorCount++;

            return errorCount === 0;
        }

        /**
         * Handles form input changes.
         *
         * @param name
         * @param value
         */
        const handleChange = (name, value) => {
            if (['username', 'password'].includes(name)) {
                validator.validateRequiredField(
                    document.querySelector(`[name="${name}-input"]`),
                    'validation.requiredField'
                );
            }

            changedSettings[name] = value;
            disableFooter(false);
        }

        /**
         * Handles form saving.
         */
        const handleSave = () => {
            if (!isFormValid()) {
                return;
            }

            if (!hasChange() && configuration.appState !== SequraFE.appStates.ONBOARDING) {
                disableFooter(true);

                return;
            }

            if (hasChange() && activeSettings.environment !== changedSettings.environment) {
                showConfirmModal().then((confirmed) => {
                    if (!confirmed) {
                        return;
                    }

                    utilities.showLoader();

                    const merchantId = configuration.appState === SequraFE.appStates.ONBOARDING ? 'test' : data.countrySettings[0]?.merchantId;
                    api.post(configuration.validateConnectionDataUrl, {...changedSettings, merchantId: merchantId})
                        .then((result) => areCredentialsValid(result) ? saveChangedData() : handleValidationError())
                })
            } else {
                utilities.showLoader();

                const merchantId = configuration.appState === SequraFE.appStates.ONBOARDING ? 'test' : data.countrySettings[0]?.merchantId;
                api.post(configuration.validateConnectionDataUrl, {...changedSettings, merchantId: merchantId})
                    .then((result) => areCredentialsValid(result) ? saveChangedData() : handleValidationError());
            }
        }

        const hasChange = () => {
            return activeSettings.environment !== changedSettings.environment ||
                activeSettings.password !== changedSettings.password ||
                activeSettings.username !== changedSettings.username ||
                activeSettings.sendStatisticalData !== changedSettings.sendStatisticalData
        }

        /**
         * Returns true if username and password are valid.
         *
         * @param {{isValid: boolean, reason: string|null}} result
         */
        const areCredentialsValid = (result) => {
            if (!result.isValid && result.reason.includes('merchantId')) {
                navigateToOnboarding = true;
            }

            return result.isValid || result.reason.includes('merchantId');
        }

        /**
         * Handle connection validation error.
         */
        const handleValidationError = () => {
            SequraFE.responseService.errorHandler({errorCode: 'general.errors.connection.invalidUsernameOrPassword'}).catch(() => {
            });

            utilities.hideLoader();
        }

        const saveChangedData = () => {
            utilities.showLoader();
            api.post(configuration.saveConnectionDataUrl, changedSettings)
                .then(() => {
                    if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                        if (activeSettings.username.length !== 0) {
                            SequraFE.state.setCredentialsChanged();
                        }

                        const index = SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.CONNECT)
                        SequraFE.pages.onboarding.length > index + 1 ?
                            window.location.hash = configuration.appState + '-' + SequraFE.pages.onboarding[index + 1] :
                            window.location.hash = SequraFE.appStates.PAYMENT + '-' + SequraFE.appPages.PAYMENT.METHODS;
                    }

                    activeSettings = utilities.cloneObject(changedSettings);
                    SequraFE.state.setData('connectionSettings', activeSettings);

                    disableFooter(true);

                    if (configuration.appState === SequraFE.appStates.SETTINGS && navigateToOnboarding) {
                        SequraFE.state.setCredentialsChanged();
                        SequraFE.state.goToState(SequraFE.appStates.ONBOARDING);
                    } else {
                        utilities.hideLoader();
                    }
                });
        }

        /**
         * Handles the disconnect button click.
         */
        const handleDisconnect = () => {
            showDisconnectModal().then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                utilities.showLoader();
                api.post(configuration.disconnectUrl, null)
                    .then(() => SequraFE.state.display())
                    .finally(utilities.hideLoader);
            })
        }

        /**
         * Disables the form footer controls.
         *
         * @param disable
         */
        const disableFooter = (disable) => {
            if (configuration.appState !== SequraFE.appStates.ONBOARDING) {
                utilities.disableFooter(disable);
            }
        }

        /**
         * Shows the disconnect modal dialog.
         *
         * @returns {Promise}
         */
        const showDisconnectModal = () => {
            return new Promise((resolve) => {
                const modal = components.Modal.create({
                    title: `connection.disconnect.title`,
                    className: `sq-modal sqv--connection-modal`,
                    content: [generator.createElement('p', '', `connection.disconnect.message`)],
                    footer: true,
                    buttons: [
                        {
                            type: 'secondary',
                            label: 'general.cancel',
                            onClick: () => {
                                modal.close();
                                resolve(false);
                            }
                        },
                        {
                            type: 'primary',
                            label: 'general.confirm',
                            onClick: () => {
                                modal.close();
                                resolve(true);
                            }
                        }
                    ]
                });

                modal.open();
            });
        }

        /**
         * Shows the confirmation modal dialog.
         *
         * @returns {Promise}
         */
        const showConfirmModal = () => {
            return new Promise((resolve) => {
                const modal = components.Modal.create({
                    title: `connection.modal.title`,
                    className: `sq-modal sqv--connection-modal`,
                    content: [generator.createElement('p', '', `connection.modal.message`)],
                    footer: true,
                    buttons: [
                        {
                            type: 'secondary',
                            label: 'general.cancel',
                            onClick: () => {
                                modal.close();
                                resolve(false);
                            }
                        },
                        {
                            type: 'primary',
                            label: 'general.confirm',
                            onClick: () => {
                                modal.close();
                                resolve(true);
                            }
                        }
                    ]
                });

                modal.open();
            });
        };
    }

    SequraFE.ConnectionSettingsForm = ConnectionSettingsForm;
})();
