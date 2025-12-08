if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef GeneralSettings
     * @property {boolean} showSeQuraCheckoutAsHostedPage
     * @property {boolean} sendOrderReportsPeriodicallyToSeQura
     * @property {string[] | null} allowedIPAddresses
     * @property {string[] | null} excludedCategories
     * @property {string[] | null} excludedProducts
     */

    /**
     * @typedef Category
     * @property {string} id
     * @property {string} name
     */

    /**
     * @typedef CountrySettings
     * @property {string} countryCode
     * @property {string} merchantId
     */

    /**
     * @typedef SellingCountry
     * @property {string} name
     * @property {string} code
     * @property {string} merchantId
     */

    /**
     * Handles general settings form logic.
     *
     * @param {{
     * generalSettings: GeneralSettings,
     * countrySettings: CountrySettings[],
     * shopCategories: Category[],
     * sellingCountries: SellingCountry[],
     * connectionSettings: ConnectionSettings,
     * }} data
     * @param {{
     * saveGeneralSettingsUrl: string,
     * getGeneralSettingsUrl: string,
     * saveCountrySettingsUrl: string,
     * validateConnectionDataUrl: string,
     * page: string,
     * appState: string,
     * }} configuration
     * @constructor
     */
    function GeneralSettingsForm(data, configuration) {
        const {
            elementGenerator: generator,
            validationService: validator,
            utilities
        } = SequraFE;

        const classNameServiceRelatedField = 'sq-service-related-field';
        const classNameEnabledForService = 'sq-field-enabled-for-services';
        const classNameAllowFirstServicePaymentDelay = 'sq-field-allow-first-service-payment-delay';
        const classNameAllowServiceRegistrationItems = 'sq-field-allow-service-registration-items';
        const classNameDefaultServicesEndDate = 'sq-default-services-end-date';

        /** @type AjaxServiceType */
        const api = SequraFE.ajaxService;
        /** @type GeneralSettings */
        let activeGeneralSettings;
        /** @type CountrySettings[] */
        let activeCountryConfiguration;
        /** @type GeneralSettings */
        let changedGeneralSettings;
        /** @type CountrySettings[] */
        let changedCountryConfiguration;
        /** @type boolean */
        let haveGeneralSettingsChanged = false;
        /** @type boolean */
        let hasCountryConfigurationChanged = false;

        /** @type GeneralSettings */
        const defaultGeneralSettingsData = {
            showSeQuraCheckoutAsHostedPage: false,
            sendOrderReportsPeriodicallyToSeQura: false,
            allowedIPAddresses: [],
            excludedCategories: [],
            excludedProducts: [],
            enabledForServices: [],
            allowFirstServicePaymentDelay: [],
            allowServiceRegistrationItems: [],
            defaultServicesEndDate: 'P1Y'
        };

        /**
         * Handles form rendering.
         */
        this.render = () => {
            if (!activeCountryConfiguration) {
                activeCountryConfiguration = data?.countrySettings?.map((utilities.cloneObject))
            }

            if (!activeGeneralSettings) {
                activeGeneralSettings = utilities.cloneObject(defaultGeneralSettingsData);
                for (let key in activeGeneralSettings) {
                    activeGeneralSettings[key] = data?.generalSettings?.[key] ?? defaultGeneralSettingsData[key];
                }
            }

            changedCountryConfiguration = activeCountryConfiguration?.map((utilities.cloneObject))
            if (!changedCountryConfiguration.length && data.sellingCountries.length > 0) {
                hasCountryConfigurationChanged = true;
                changedCountryConfiguration = data.sellingCountries.map((sellingCountry) => ({
                    countryCode: sellingCountry.code,
                    merchantId: sellingCountry.merchantId || '',
                }));
            }
            changedGeneralSettings = utilities.cloneObject(activeGeneralSettings)
            initForm();

            if (SequraFE.state.getCredentialsChanged()) {
                handleSave();

                return;
            }

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
                        title: configuration.appState === SequraFE.appStates.ONBOARDING ?
                            'countries.title' : 'generalSettings.title',
                        text: configuration.appState === SequraFE.appStates.ONBOARDING ?
                            'countries.description' : 'generalSettings.description'
                    })
                ])
            );

            const pageInnerContent = document.querySelector('.sq-content-inner');

            if (data.sellingCountries.length === 0) {
                pageInnerContent?.append(utilities.createFlashMessage('general.errors.countries.noCountries', "error"));

                return;
            }

            if (configuration.appState === SequraFE.appStates.SETTINGS && !SequraFE.isPromotional) {
                const {
                    isShowCheckoutAsHostedPageFieldVisible,
                    isServiceSellingAllowed
                } = SequraFE.flags;

                if (isShowCheckoutAsHostedPageFieldVisible) {
                    pageInnerContent?.append(
                        generator.createToggleField({
                            value: changedGeneralSettings.showSeQuraCheckoutAsHostedPage,
                            label: 'generalSettings.showCheckoutAsHostedPage.label',
                            description: 'generalSettings.showCheckoutAsHostedPage.description',
                            onChange: (value) => handleGeneralSettingsChange('showSeQuraCheckoutAsHostedPage', value)
                        })
                    );
                }

                pageInnerContent?.append(
                    generator.createMultiItemSelectorField({
                        name: 'allowedIPAddresses-selector',
                        label: 'generalSettings.allowedIPAddresses.label',
                        description: 'generalSettings.allowedIPAddresses.description',
                        value: changedGeneralSettings.allowedIPAddresses?.join(', '),
                        searchable: false,
                        onChange: (value) => handleGeneralSettingsChange('allowedIPAddresses', value)
                    }),
                    generator.createMultiItemSelectorField({
                        label: 'generalSettings.excludedCategories.label',
                        description: 'generalSettings.excludedCategories.description',
                        value: changedGeneralSettings.excludedCategories?.join(','),
                        options: data.shopCategories.map((category) => ({ label: category.name, value: category.id })),
                        onChange: (value) => handleGeneralSettingsChange('excludedCategories', value)
                    }),
                    generator.createMultiItemSelectorField({
                        label: 'generalSettings.excludedProducts.label',
                        description: 'generalSettings.excludedProducts.description',
                        value: changedGeneralSettings.excludedProducts?.join(','),
                        searchable: false,
                        onChange: (value) => handleGeneralSettingsChange('excludedProducts', value)
                    })
                );

                if (isServiceSellingAllowed) {

                    pageInnerContent?.append(
                        generator.createToggleField({
                            className: classNameEnabledForService,
                            disabled: true,
                            label: 'generalSettings.enabledForServices.label',
                            description: 'generalSettings.enabledForServices.description'
                        }),
                        generator.createToggleField({
                            className: `${classNameServiceRelatedField} ${classNameAllowFirstServicePaymentDelay}`,
                            disabled: true,
                            label: 'generalSettings.allowFirstServicePaymentDelay.label',
                            description: 'generalSettings.allowFirstServicePaymentDelay.description'
                        }),
                        generator.createToggleField({
                            className: `${classNameServiceRelatedField} ${classNameAllowServiceRegistrationItems}`,
                            disabled: true,
                            label: 'generalSettings.allowServiceRegistrationItems.label',
                            description: 'generalSettings.allowServiceRegistrationItems.description'
                        }),
                        generator.createTextField({
                            className: `sq-text-input ${classNameServiceRelatedField} ${classNameDefaultServicesEndDate}`,
                            label: 'generalSettings.defaultServicesEndDate.label',
                            description: 'generalSettings.defaultServicesEndDate.description',
                            onChange: (value) => handleGeneralSettingsChange('defaultServicesEndDate', value)
                        })
                    );

                    showOrHideServiceRelatedFields();
                }
            }

            pageInnerContent?.append(
                generator.createMultiItemSelectorField({
                    name: 'countries-selector',
                    label: 'countries.selector.label',
                    description: 'countries.selector.description',
                    value: changedCountryConfiguration.map((country) => country.countryCode).join(','),
                    options: data.sellingCountries.map((country) => ({ label: country.name, value: country.code })),
                    onChange: handleCountryChange
                })
            );

            renderCountries();
            data.sellingCountries.length !== 0 && renderControls();
        }

        /**
         * Renders country fields.
         */
        const renderCountries = () => {
            changedCountryConfiguration.map((countryConfig) => {
                document.querySelector('.sq-content-inner')?.append(generator.createCountryField({
                    countryCode: countryConfig.countryCode,
                    merchantId: countryConfig.merchantId,
                    onChange: (value) => handleMerchantChange(countryConfig.countryCode, value)
                }))
            })
        }

        /**
         * Renders form controls.
         */
        const renderControls = () => {
            configuration.appState === SequraFE.appStates.ONBOARDING ?
                document.querySelector('.sq-content-inner')?.append(
                    generator.createButtonField({
                        className: 'sq-continue sqm--block',
                        buttonType: 'primary',
                        buttonLabel: 'general.continue',
                        onClick: handleSave
                    })
                ) :
                document.querySelector('.sq-content')?.append(
                    generator.createPageFooter({
                        onSave: handleSave,
                        onCancel: () => {
                            const pageContent = document.querySelector('.sq-content');
                            while (pageContent?.firstChild) {
                                pageContent?.removeChild(pageContent.firstChild);
                            }

                            this.render();
                        }
                    })
                )
        }

        /**
         * Validates country configuration.
         *
         * @returns {boolean}
         */
        const isCountryConfigurationValid = () => {
            let errorCount = 0;

            if (!changedCountryConfiguration.length) {
                validator.validateRequiredField(
                    document.querySelector(`[name="countries-selector"]`),
                    'validation.requiredField'
                );

                errorCount++;
            }

            changedCountryConfiguration.map((setting) => {
                !validator.validateRequiredField(
                    document.querySelector(`[name="country_${setting.countryCode}"]`),
                    'validation.requiredField'
                ) && errorCount++;
            });

            return errorCount === 0;
        }

        /**
         * Handles changes to the merchant ids.
         *
         * @param countryCode
         * @param merchantId
         */
        const handleMerchantChange = (countryCode, merchantId) => {
            validator.validateRequiredField(
                document.querySelector(`[name="country_${countryCode}"]`),
                'validation.requiredField'
            );

            changedCountryConfiguration.find(
                (setting) => setting.countryCode === countryCode
            ).merchantId = merchantId;

            hasCountryConfigurationChanged = true;
            disableFooter(false);
        }

        /**
         * Handles configured country changes.
         *
         * @param countryCodes
         */
        const handleCountryChange = (countryCodes) => {
            validator.validateRequiredField(
                document.querySelector(`[name="countries-selector"]`),
                'validation.requiredField'
            );

            const countryElements = document.querySelectorAll('.sq-country-field-wrapper');
            countryElements.forEach((el) => el.remove());

            changedCountryConfiguration = countryCodes.map((code) => {
                const sellingCountry = data.sellingCountries.find((country) => country.code === code);
                return {
                    countryCode: code,
                    merchantId: sellingCountry ? sellingCountry.merchantId : ''
                };
            });

            renderCountries();

            if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                document.querySelector('.sq-continue').remove();
                renderControls();
            }

            hasCountryConfigurationChanged = true;
            disableFooter(false);
        }

        /**
         * Handles general settings changes.
         *
         * @param name
         * @param value
         */
        const handleGeneralSettingsChange = (name, value) => {
            changedGeneralSettings[name] = value;
            haveGeneralSettingsChanged = true;
            disableFooter(false);
        }

        const showOrHideServiceRelatedFields = () => {
            if (!SequraFE.flags.isServiceSellingAllowed) {
                return;
            }
            // Update the values and descriptions of the fields.
            const countriesString = countries => {
                // input is like ['ES', 'PT']
                // output is like 'Spain and Portugal' or 'Spain, France, and Portugal'
                let countriesString = '';
                const translate = SequraFE.translationService.translate;
                for (let i = 0; i < countries.length; i++) {
                    const country = '<strong>' + translate('countries.' + countries[i] + '.label') + '</strong>';
                    if (i === 0) {
                        countriesString += country;
                    } else if (i === countries.length - 1) {
                        countriesString += translate('general.and') + country;
                    } else {
                        countriesString += ', ' + country;
                    }
                }
                return countriesString ? translate('countries.enabledCountries').replace('{countries}', countriesString) : '';
            }
            const descriptionWithCountries = (description, countries) => SequraFE.translationService.translate(description) + countriesString(countries);

            const enabledForServiceInput = document.querySelector(`.${classNameEnabledForService} input`);
            if (enabledForServiceInput) {
                enabledForServiceInput.checked = changedGeneralSettings.enabledForServices.length > 0;
            }
            const enabledForServiceSubtitle = document.querySelector(`.${classNameEnabledForService} .sqp-field-subtitle`);
            if (enabledForServiceSubtitle) {
                enabledForServiceSubtitle.innerHTML = descriptionWithCountries('generalSettings.enabledForServices.description', changedGeneralSettings.enabledForServices);
            }
            const allowFirstServicePaymentDelayInput = document.querySelector(`.${classNameAllowFirstServicePaymentDelay} input`);
            if (allowFirstServicePaymentDelayInput) {
                allowFirstServicePaymentDelayInput.checked = changedGeneralSettings.allowFirstServicePaymentDelay.length > 0;
            }
            const allowFirstServicePaymentDelaySubtitle = document.querySelector(`.${classNameAllowFirstServicePaymentDelay} .sqp-field-subtitle`);
            if (allowFirstServicePaymentDelaySubtitle) {
                allowFirstServicePaymentDelaySubtitle.innerHTML = descriptionWithCountries('generalSettings.allowFirstServicePaymentDelay.description', changedGeneralSettings.allowFirstServicePaymentDelay);
            }
            const allowServiceRegistrationItemsInput = document.querySelector(`.${classNameAllowServiceRegistrationItems} input`);
            if (allowServiceRegistrationItemsInput) {
                allowServiceRegistrationItemsInput.checked = changedGeneralSettings.allowServiceRegistrationItems.length > 0;
            }
            const allowServiceRegistrationItemsSubtitle = document.querySelector(`.${classNameAllowServiceRegistrationItems} .sqp-field-subtitle`);
            if (allowServiceRegistrationItemsSubtitle) {
                allowServiceRegistrationItemsSubtitle.innerHTML = descriptionWithCountries('generalSettings.allowServiceRegistrationItems.description', changedGeneralSettings.allowServiceRegistrationItems);
            }
            const defaultServicesEndDateInput = document.querySelector(`.${classNameDefaultServicesEndDate}`);
            if (defaultServicesEndDateInput) {
                defaultServicesEndDateInput.value = changedGeneralSettings.defaultServicesEndDate;
            }

            // Update the visibility of the fields.
            const selector = '.sq-field-wrapper:has(.sq-service-related-field), .sq-field-wrapper.sq-service-related-field'
            const hiddenClass = 'sqs--hidden';
            document.querySelectorAll(selector).forEach((el) => {
                if (changedGeneralSettings.enabledForServices.length > 0) {
                    el.classList.remove(hiddenClass)
                } else {
                    el.classList.add(hiddenClass)
                }
            });
        }

        const areIPAddressesValid = () => {
            let hasError = false;

            changedGeneralSettings.allowedIPAddresses.forEach((address) => {
                if (!validator.validateIpAddress(address)) {
                    hasError = true;
                }
            });

            validator.validateField(
                document.querySelector(`[name="allowedIPAddresses-selector"]`),
                hasError,
                'validation.invalidIPAddress'
            );

            return !hasError;
        }

        const isValidTimeDuration = () => {
            const valid = validator.validateDateOrDuration(changedGeneralSettings.defaultServicesEndDate);
            validator.validateField(
                document.querySelector('.sq-default-services-end-date'),
                !valid,
                'validation.invalidTimeDuration'
            );

            return valid;
        }

        /**
         * Handles saving of the form.
         */
        const handleSave = () => {
            if (!isCountryConfigurationValid() || !areIPAddressesValid() || !isValidTimeDuration()) {
                return;
            }

            utilities.showLoader();

            areIPAddressesValid()
            saveChangedData();
        }

        /**
         * Save changed data.
         */
        const saveChangedData = () => {
            utilities.showLoader();

            const promises = [];

            haveGeneralSettingsChanged &&
                promises.push(api.post(configuration.saveGeneralSettingsUrl, changedGeneralSettings, SequraFE.customHeader));

            hasCountryConfigurationChanged &&
                promises.push(api.post(configuration.saveCountrySettingsUrl, changedCountryConfiguration, SequraFE.customHeader));

            Promise.all(promises)
                .then(async () => {
                    disableFooter(true);
                    if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                        activeGeneralSettings = utilities.cloneObject(changedGeneralSettings);
                    } else {
                        try {
                            activeGeneralSettings = await api.get(configuration.getGeneralSettingsUrl, null, SequraFE.customHeader);
                            if (JSON.stringify(activeGeneralSettings) !== JSON.stringify(changedGeneralSettings)) {
                                changedGeneralSettings = utilities.cloneObject(activeGeneralSettings);
                                // Update the service components in the form.
                                showOrHideServiceRelatedFields();
                            }
                        } catch (error) {
                            activeGeneralSettings = utilities.cloneObject(changedGeneralSettings);
                            SequraFE.responseService.errorHandler({ errorCode: 'general.errors.backgroundDataFetchFailure' }).catch(e => console.error(e));
                        }
                    }
                    activeCountryConfiguration = changedCountryConfiguration.map((utilities.cloneObject))

                    configuration.appState === SequraFE.appStates.SETTINGS &&
                        SequraFE.state.setData('generalSettings', activeGeneralSettings);
                    SequraFE.state.setData('countrySettings', activeCountryConfiguration);

                    haveGeneralSettingsChanged = false;
                    hasCountryConfigurationChanged = false;

                    let haveCredentialsChanges = SequraFE.state.getCredentialsChanged();
                    if (configuration.appState === SequraFE.appStates.ONBOARDING) {
                        if (haveCredentialsChanges) {
                            SequraFE.state.removeCredentialsChanged();
                            SequraFE.state.goToState(SequraFE.appStates.SETTINGS);

                            return;
                        }

                        const index = SequraFE.pages.onboarding.indexOf(SequraFE.appPages.ONBOARDING.COUNTRIES)
                        SequraFE.pages.onboarding.length > index + 1 ?
                            window.location.hash = configuration.appState + '-' + SequraFE.pages.onboarding[index + 1] :
                            window.location.hash = SequraFE.appStates.PAYMENT + '-' + SequraFE.appPages.PAYMENT.METHODS;
                    }

                    if (haveCredentialsChanges) {
                        SequraFE.state.removeCredentialsChanged();
                    }
                })
                .finally(() => {
                    configuration.appState !== SequraFE.appStates.ONBOARDING && utilities.hideLoader();
                });
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
    }

    SequraFE.GeneralSettingsForm = GeneralSettingsForm;
})();
