if (!window.SequraFE) {
    window.SequraFE = {};
}

/**
 * Creates and shows the "Manage Deployments" modal.
 *
 * @param {{
 *   generator: typeof SequraFE.elementGenerator,
 *   components: typeof SequraFE.components
 *   notConnectedDeployments: DeploymentSettings[]
 *   changedSettings: ConnectionSettings
 *   activeSettings: ConnectionSettings
 * }} dependencies
 *
 * @returns {Promise<boolean>} Resolves true if confirmed, false if canceled
 */
window.SequraFE.showDeploymentsModal = function (
    {
        api, configuration, generator, components, validator, utilities,
        notConnectedDeployments, activeSettings
    }
) {
    return new Promise((resolve) => {
        if (!notConnectedDeployments || notConnectedDeployments.length === 0) {
            resolve({confirmed: false, selectedDeploymentId: null});

            return;
        }

        let changedSettings = utilities.cloneObject(activeSettings);

        let hasChanges = false;
        let activeDeploymentId = notConnectedDeployments[0].id;

        const getSettingsForDeployment = (deploymentId) => {
            let entry = changedSettings.connectionData.find(c => c.deployment === deploymentId);
            if (!entry) {
                entry = {username: '', password: '', merchantId: '', deployment: deploymentId};
                changedSettings.connectionData.push(entry);
            }

            return entry;
        };

        const fieldWrapper = generator.createElement('div', 'sq-field-wrapper');
        const menuWrapper = generator.createElement('div', 'items sqp-menu-items-deployments');

        const deploymentItems = notConnectedDeployments.map(deployment => {
            const item = generator.createElement(
                'span',
                `sqp-menu-item ${deployment.id === activeDeploymentId ? 'sqs--active' : ''}`,
                deployment.name
            );

            item.style.cursor = 'pointer';
            item.addEventListener('click', () => {
                if (deployment.id !== activeDeploymentId) {
                    activeDeploymentId = deployment.id;

                    deploymentItems.forEach(el => el.classList.remove('sqs--active'));
                    item.classList.add('sqs--active');

                    const settings = getSettingsForDeployment(activeDeploymentId);
                    usernameInput.value = settings.username;
                    passwordInput.value = settings.password;
                }
            });

            return item;
        });

        menuWrapper.append(...deploymentItems);
        fieldWrapper.append(menuWrapper);

        const content = generator.createElement('div', 'sq-content-inner sqv--deployments', '', null, [
            fieldWrapper
        ]);
        let errorContainer = generator.createElement('div', 'sqp-flash-message-wrapper');
        content.append(errorContainer);

        const usernameInput = generator.createTextField({
            name: 'new-username-input',
            value: getSettingsForDeployment(activeDeploymentId).username,
            className: 'sq-text-input',
            label: 'connection.username.label',
            description: 'connection.username.description',
            onChange: (value) => handleChange('username', value)
        });
        content.append(usernameInput);

        const passwordInput = generator.createPasswordField({
            name: 'new-password-input',
            value: getSettingsForDeployment(activeDeploymentId).password,
            className: 'sq-password-input',
            label: 'connection.password.label',
            description: 'connection.password.description',
            onChange: (value) => handleChange('password', value)
        });
        content.append(passwordInput);

        const handleChange = (name, value) => {
            if (['username', 'password'].includes(name)) {
                validator.validateRequiredField(
                    document.querySelector(`[name="new-${name}-input"]`),
                    'validation.requiredField'
                );

                const current = getSettingsForDeployment(activeDeploymentId);
                if (current) {
                    current[name] = value;
                    hasChanges = true;
                }
            }
        };

        /**
         * Validates form inputs.
         *
         * @returns {boolean}
         */
        const isFormValid = () => {
            let errorCount = 0;

            !validator.validateRequiredField(
                document.querySelector('[name="new-username-input"]'),
                'validation.requiredField'
            ) && errorCount++;

            !validator.validateRequiredField(
                document.querySelector('[name="new-password-input"]'),
                'validation.requiredField'
            ) && errorCount++;

            return errorCount === 0;
        }

        const handleSave = async () => {
            if (!isFormValid()) {
                return;
            }

            utilities.showLoader();

            try {
                const finalSettings = utilities.cloneObject(activeSettings);
                const updatedConnection = getSettingsForDeployment(activeDeploymentId);
                const existingIndex = finalSettings.connectionData.findIndex(c => c.deployment === activeDeploymentId);
                if (existingIndex >= 0) {
                    finalSettings.connectionData[existingIndex] = updatedConnection;
                } else {
                    finalSettings.connectionData.push(updatedConnection);
                }

                const result = await api.post(configuration.connectUrl, finalSettings);
                if (!areCredentialsValid(result)) {
                    handleValidationError();

                    return;
                }

                modal.close();
                resolve({
                    confirmed: true,
                    selectedDeploymentId: activeDeploymentId,
                    hasChanges,
                    updatedSettings: changedSettings,
                    activatedDeployment: notConnectedDeployments.find(deployment => deployment.id === activeDeploymentId)
                });
            } catch (error) {
                handleValidationError();
            } finally {
                utilities.hideLoader();
            }
        };

        /**
         * Returns true if username and password are valid.
         *
         * @param {{isValid: boolean, reason: string|null}} result
         */
        const areCredentialsValid = (result) => {
            return result.isValid || result.reason.includes('merchantId');
        }

        this.errorHandler = (response) => {
            const {utilities, templateService, elementGenerator} = SequraFE;

            templateService.clearComponent(errorContainer);

            if (response.errorCode) {
                errorContainer.prepend(utilities.createFlashMessage(response.errorCode, 'error'));
            } else if (response.errorMessage) {
                errorContainer.prepend(utilities.createFlashMessage(response.errorMessage, 'error'));
            } else {
                errorContainer.prepend(utilities.createFlashMessage('general.errors.unknown', 'error'));
            }

            return Promise.reject(response);
        };

        /**
         * Handle connection validation error.
         */
        const handleValidationError = () => {
            this.errorHandler({errorCode: 'general.errors.connection.invalidUsernameOrPassword'}).catch(() => {
            });

            utilities.hideLoader();
        }

        const modal = components.Modal.create({
            title: 'deployments.title',
            className: 'sq-modal',
            content: [content],
            footer: true,
            buttons: [
                {
                    type: 'secondary',
                    label: 'general.cancel',
                    onClick: () => {
                        modal.close();
                        resolve({confirmed: false, selectedDeploymentId: null});
                    }
                },
                {
                    className: 'sqp-save',
                    type: 'primary',
                    label: 'general.saveChanges',
                    onClick: handleSave
                }
            ]
        });

        modal.open('.sq-content');
    });
};
