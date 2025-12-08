if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef ConnectionsData
     * @property {string} username
     * @property {string} password
     * @property {string} merchantId
     * @property {string} [deployment]
     */

    /**
     * @typedef ConnectionSettings
     * @property {'live' | 'sandbox'} environment
     * @property {boolean} sendStatisticalData
     * @property {ConnectionsData[]} connectionData
     */

    /**
     * Handles connection settings form logic.
     *
     * @param {{
     * connectionSettings: ConnectionSettings,
     * countrySettings: CountrySettings[]
     * }} data
     * @param {{
     * connectUrl: string,
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
        let activeSettings = null;
        /** @type ConnectionSettings */
        let changedSettings = null;

        const allDeployments = SequraFE.state.getData('deploymentsSettings') || [];

        const activeDeployments = Array.isArray(data.activeDeploymentsIds)
            ? allDeployments.filter(deployment => data.activeDeploymentsIds.includes(deployment.id))
            : allDeployments.filter(deployment => deployment.active);


        /** @type ConnectionSettings */
        const defaultFormData = {
            environment: 'sandbox',
            sendStatisticalData: true,
            connectionData: activeDeployments.map(deployment => ({
                username: '',
                password: '',
                merchantId: '',
                deployment: deployment.id
            }))
        };

        let notConnectedDeployments = data.notConnectedDeployments || [];

        let activeDeploymentId = (activeDeployments || [])[0]?.id || null;

        let manageButton = null;

        const getSettingsForActiveDeployment = (settings) => {
            return settings.connectionData.find(c => c.deployment === activeDeploymentId) || {};
        };

        const updateFormFields = () => {
            const usernameInput = document.querySelector('[name="username-input"]');
            if (usernameInput) usernameInput.value = getSettingsForActiveDeployment(changedSettings).username ?? '';

            const passwordInput = document.querySelector('[name="password-input"]');
            if (passwordInput) passwordInput.value = getSettingsForActiveDeployment(changedSettings).password ?? '';
        };

        const updateDeploymentMenuActiveState = () => {
            const menuWrapper = document.querySelector('.sqp-menu-items-deployments');
            if (!menuWrapper) return;

            const items = menuWrapper.querySelectorAll('.sqp-menu-item');
            items.forEach(item => {
                if (item.textContent === (activeDeployments.find(d => d.id === activeDeploymentId)?.name || '')) {
                    item.classList.add('sqs--active');
                } else {
                    item.classList.remove('sqs--active');
                }
            });
        };

        const initSettings = (connectionSettings = data.connectionSettings) => {
            const isEmptyArray = Array.isArray(connectionSettings) && connectionSettings.length === 0;

            activeSettings = (!connectionSettings || isEmptyArray)
                ? utilities.cloneObject(defaultFormData)
                : connectionSettings;

            changedSettings = utilities.cloneObject(activeSettings);
        };


        /**
         * Handles form rendering.
         */
        this.render = () => {
            if (activeDeployments.length === 0) return;

            if (!activeDeploymentId) {
                activeDeploymentId = activeDeployments[0].id;
            }

            initSettings();
            initForm();

            if (!notConnectedDeployments || notConnectedDeployments.length === 0) {
                hideMenageButton();
            }

            disableFooter(true);
            utilities.hideLoader();
        }

        const hideMenageButton = () => {
            const button = document.querySelector('.sq-field-wrapper.sqm--deployment button');
            const buttonWrapper = document.querySelector('.sq-field-wrapper.sqm--deployment');

            if (button) {
                button.style.display = 'none';
                buttonWrapper.style.width= 'auto';
            }
        }

        /**
         * Initializes the form structure.
         */
        const initForm = () => {
            const pageContent = document.querySelector('.sq-content');
            const contentInner = generator.createElement('div', 'sq-content-inner sqv--connect', '', null, []);
            contentInner.append(generator.createElement('div', 'sqp-flash-message-wrapper'));
            const headingWrapper = generator.createElement('div', 'sq-heading-wrapper', '', null, []);
            headingWrapper.append(generator.createPageHeading({
                title: `connection.title.${configuration.appState}`,
                text: `connection.description.${configuration.appState}`
            }));

            if (configuration.appState === SequraFE.appStates.SETTINGS) {
                manageButton = generator.createButtonField({
                    className: 'sqm-button sqm--deployment',
                    buttonType: 'primary',
                    buttonSize: 'medium',
                    buttonLabel: 'connection.deployments.manage',
                    onClick: () => {
                        SequraFE.showDeploymentsModal({
                            api, configuration, generator, components, validator, utilities,
                            notConnectedDeployments,
                            activeSettings
                        }).then(({ confirmed, updatedSettings, activatedDeployment }) => {
                            if (confirmed && updatedSettings) {
                                activeSettings = updatedSettings;
                                changedSettings = utilities.cloneObject(updatedSettings);
                                SequraFE.state.setData('connectionSettings', activeSettings);
                                data.connectionSettings = activeSettings;

                                if (activatedDeployment) {
                                    const alreadyExists = activeDeployments.some(
                                        d => d.id === activatedDeployment.id
                                    );
                                    if (!alreadyExists) {
                                        activeDeployments.push(activatedDeployment);
                                    }

                                    activeDeploymentId = activatedDeployment.id;
                                    notConnectedDeployments = notConnectedDeployments.filter(
                                        d => d.id !== activatedDeployment.id
                                    );
                                    SequraFE.state.setData('notConnectedDeployments', activeSettings);
                                }

                                const pageContent = document.querySelector('.sq-content');
                                while (pageContent?.firstChild) {
                                    pageContent.removeChild(pageContent.firstChild);
                                }

                                this.render();
                            }
                        });
                    }
                })

                headingWrapper.append(manageButton);
            }

            contentInner.append(headingWrapper);
            contentInner.append(generator.createRadioGroupField({
                name: 'environment-input',
                value: changedSettings.environment,
                label: 'connection.environment.label',
                options: [
                    { label: 'connection.environment.options.live', value: 'live' },
                    { label: 'connection.environment.options.sandbox', value: 'sandbox' }
                ],
                onChange: (value) => handleChange('environment', value)
            }));

            const fieldWrapper = generator.createElement('div', 'sq-field-wrapper');

            if (activeDeployments.length > 0) {
                const menuWrapper = generator.createElement('div', 'items sqp-menu-items-deployments');
                menuWrapper.append(
                    ...activeDeployments.map(deployment => {
                        const item = generator.createElement(
                            'span',
                            `sqp-menu-item ${deployment.id === activeDeploymentId ? 'sqs--active' : ''}`,
                            deployment.name
                        );

                        item.style.cursor = 'pointer';
                        item.addEventListener('click', () => {
                            if (deployment.id !== activeDeploymentId) {
                                activeDeploymentId = deployment.id;
                                updateFormFields();
                                updateDeploymentMenuActiveState();
                                disableFooter(false);
                            }
                        });

                        return item;
                    })
                );
                fieldWrapper.append(menuWrapper);
            }

            const username = generator.createTextField({
                name: 'username-input',
                value: getSettingsForActiveDeployment(changedSettings).username ?? '',
                className: 'sq-text-input',
                label: 'connection.username.label',
                description: 'connection.username.description',
                onChange: (value) => handleChange('username', value)
            });
            const password = generator.createPasswordField({
                name: 'password-input',
                value: getSettingsForActiveDeployment(changedSettings).password ?? '',
                className: 'sq-password-input',
                label: 'connection.password.label',
                description: 'connection.password.description',
                onChange: (value) => handleChange('password', value)
            });

            const connectionDataFrame = generator.createElement('div', 'sq-data-frame');
            connectionDataFrame.append(fieldWrapper);
            connectionDataFrame.append(username);
            connectionDataFrame.append(password);

            contentInner.append(connectionDataFrame);
            pageContent?.append(contentInner);

            document.querySelector('.sqp-description')?.append(
                generator.createButtonLink({
                    className: 'sq-link-button',
                    text: 'connection.description.endLink',
                    href: 'https://en.sequra.com/',
                    openInNewTab: true
                })
            );

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
                    className: 'sqm--block sqm--bellow-frame',
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
                const current = getSettingsForActiveDeployment(changedSettings);
                if (current) current[name] = value;
            }

            if (name === 'environment') {
                changedSettings.environment = value;
            }

            if (name === 'sendStatisticalData') {
                changedSettings.sendStatisticalData = value;
            }

            disableFooter(false);
        };

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

                    connect()
                })
            } else {
                utilities.showLoader();

                connect();
            }
        }

        const hasChange = () => {
            if (
                changedSettings.environment !== activeSettings.environment ||
                changedSettings.sendStatisticalData !== activeSettings.sendStatisticalData
            ) {
                return true;
            }

            for (const conn of changedSettings.connectionData) {
                const orig = activeSettings.connectionData.find(c => c.deployment === conn.deployment);
                if (!orig) return true;

                if (
                    conn.username !== orig.username ||
                    conn.password !== orig.password ||
                    conn.merchantId !== orig.merchantId
                ) {
                    return true;
                }
            }

            return false;
        };

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

        const sanitizeDeploymentTargetsErrorReason = (reason) => {
            const namesMap = {
                'sequra': 'seQura',
                'svea': 'SVEA'
            }
            const deployments = (reason.split('/')[1] || '').split(',').filter(Boolean).map(name => {
                name = name.trim();
                return namesMap[name] || name;
            });
            if (deployments.length > 1) {
                return deployments.slice(0, -1).join(', ') + SequraFE.translationService.translate('general.and') + deployments.slice(-1);
            }
            return deployments[0];
        }

        /**
         * Handle connection validation error.
         */
        const handleValidationError = (result = null) => {
            if (result && typeof result.reason === 'string' && result.reason.includes('deployment')) {
                const deployment = sanitizeDeploymentTargetsErrorReason(result.reason);
                const errorKey = 'general.errors.connection.invalidUsernameOrPasswordForDeployment';

                SequraFE.responseService.errorHandler(
                    { errorCode: `${errorKey}|${deployment}` }
                ).catch(() => { });
            } else {
                SequraFE.responseService.errorHandler(
                    { errorCode: 'general.errors.connection.invalidUsernameOrPassword' }
                ).catch(() => { });
            }

            utilities.hideLoader();
        }

        const connect = () => {
            utilities.showLoader();

            api.post(configuration.connectUrl, changedSettings, SequraFE.customHeader)
                .then((result) => {

                    if (!areCredentialsValid(result)) {
                        handleValidationError(result);

                        return;
                    }

                    if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                        const currentConnection = getSettingsForActiveDeployment(activeSettings);
                        if (
                            currentConnection &&
                            currentConnection.username &&
                            currentConnection.username.length !== 0
                        ) {
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

                    if ( configuration.appState === SequraFE.appStates.SETTINGS) {
                        if(navigateToOnboarding){
                            SequraFE.state.setCredentialsChanged();
                            SequraFE.state.goToState(SequraFE.appStates.ONBOARDING);
                            return;
                        }
                        // Reload GeneralSettings data.
                        api.get(configuration.getGeneralSettingsUrl, null, SequraFE.customHeader).then(generalSettings => {
                            SequraFE.state.setData('generalSettings', generalSettings);
                        }).catch(() => {
                              SequraFE.responseService.errorHandler({ errorCode: 'general.errors.backgroundDataFetchFailure' }).catch(e => console.error(e));
                        }).finally(() => utilities.hideLoader());
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

                api.post(configuration.disconnectUrl, createPayload(), SequraFE.customHeader)
                    .then(() => SequraFE.state.display())
                    .finally(utilities.hideLoader);
            })
        }

        /**
         * Handles the disconnect button click.
         */
        const createPayload = () => {
            const isFullDisconnect = activeDeployments.length <= 1;
            const deploymentId = activeDeploymentId;

            return {
                isFullDisconnect,
                deploymentId
            };
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
                    canClose: false,
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
                            type: 'danger',
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
                    canClose: false,
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
