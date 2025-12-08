if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef Option
     * @property {string?} label
     * @property {any} value
     */

    /**
     * @typedef {Object.<string, *>} ElementProps
     * @property {string?} name
     * @property {any?} value
     * @property {string?} className
     * @property {string?} placeholder
     * @property {(value: any) => any?} onChange
     * @property {string?} label
     * @property {string?} description
     * @property {string?} error
     */

    /**
     * @typedef {ElementProps} FormField
     * @property {'text' | 'number' | 'radio' |'dropdown' | 'checkbox' | 'file' | 'multiselect' | 'button' |
     *     'buttonLink'} type
     */

    const translationService = SequraFE.translationService;

    /**
     * Creates a generic HTML node element and assigns provided class and inner text.
     *
     * @param {keyof HTMLElementTagNameMap} type Represents the name of the tag
     * @param {string?} className CSS class
     * @param {string?} innerHTMLKey Inner text translation key.
     * @param {Record<string, any>?} properties An object of additional properties.
     * @param {HTMLElement[]?} children
     * @returns {HTMLElement}
     */
    const createElement = (type, className, innerHTMLKey, properties, children) => {
        const child = document.createElement(type);
        className && child.classList.add(...className.trim().split(' '));
        if (innerHTMLKey) {
            let params = innerHTMLKey.split('|');
            child.innerHTML = translationService.translate(params[0], params.slice(1));
        }

        if (properties) {
            if (properties.dataset) {
                Object.assign(child.dataset, properties.dataset);
                delete properties.dataset;
            }

            Object.assign(child, properties);
            if (properties.onChange) {
                child.addEventListener('change', properties.onChange, false);
            }

            if (properties.onClick) {
                child.addEventListener('click', properties.onClick, false);
            }
        }

        if (children) {
            child.append(...children);
        }

        return child;
    };

    /**
     * Creates an element out of provided HTML markup.
     *
     * @param {string} html
     * @returns {HTMLElement}
     */
    const createElementFromHTML = (html) => {
        const element = document.createElement('div');
        element.innerHTML = html;

        return element.firstElementChild;
    };

    /**
     * Creates a button.
     *
     * @param {{ label?: string, type?: 'primary' | 'secondary' | 'cancel' | 'danger', size?: 'small' | 'medium',
     *     className?: string, [key: string]: any, onClick?: () => void}} props
     * @return {HTMLButtonElement}
     */
    const createButton = ({ type, size, className, onClick, label, ...properties }) => {
        const cssClass = ['sq-button'];
        type && cssClass.push('sqt--' + type);
        size && cssClass.push('sqm--' + size);
        className && cssClass.push(className);

        const button = createElement('button', cssClass.join(' '), '', { type: 'button', ...properties }, [
            createElement('span', '', label)
        ]);

        onClick &&
            button.addEventListener(
                'click',
                (event) => {
                    event.stopPropagation();
                    event.preventDefault();
                    onClick();
                },
                false
            );

        return button;
    };

    /**
     * Creates a version badge.
     *
     * @param {string?} version
     * @return {HTMLElement}
     */
    const createVersionBadge = (version) => {
        return createElement('span', 'sq-version-badge', version);
    };

    /**
     * Creates a Loader.
     *
     * @param {{ type?: 'small' | 'large', variation?: 'dark', className?:
     *     string, [key: string]: any }} props
     * @return {HTMLElement}
     */
    const createLoader = ({ type, variation }) => {
        const cssClass = ['sq-loader'];
        type && cssClass.push('sqt--' + type);
        variation && cssClass.push('sqm--' + variation);

        return createElement('div', cssClass.join(' '), '', null, [createElement('span', 'sqp-spinner', null)]);
    };

    /**
     * Creates a link that looks like a button.
     *
     * @param {{text?: string, className?: string, href: string, downloadFile?: string, openInNewTab?: boolean}} props
     * @return {HTMLLinkElement}
     */
    const createButtonLink = ({ text, className = '', href, downloadFile, openInNewTab }) => {
        const link = createElement('a', className, '', {
            href: href,
            target: openInNewTab ? "_blank" : ""
        }, [createElement('span', '', text)]);
        if (downloadFile) {
            link.setAttribute('download', downloadFile);
        }

        return link;
    };

    /**
     * Creates an input field wrapper around the provided input element.
     *
     * @param {HTMLElement} input The input element.
     * @param {string?} label Label translation key.
     * @param {string?} description Description translation key.
     * @param {string?} variation Variation of the input element.
     * @param {string?} error Error translation key.
     * @param {string?} className Class name.
     * @return {HTMLDivElement}
     */
    const createFieldWrapper = (input, label, description, variation, error, className) => {
        const field = createElement('div', 'sq-field-wrapper ' + className + (variation ? 'sqm--' + variation : ''));
        const labelWrapper = createElement('div', 'sq-label-wrapper');
        if (label) {
            labelWrapper.appendChild(createElement('span', 'sqp-field-title', label));
        }

        if (description) {
            labelWrapper.appendChild(createElement('span', 'sqp-field-subtitle', description));
        }

        const inputWrapper = createElement('div', '', '', null, [
            input,
            error ? field.appendChild(createElement('span', 'sqp-input-error', error)) : ''
        ]);

        field.append(labelWrapper, inputWrapper);

        return field;
    };

    /**
     * Creates store switcher.
     *
     * @param {{options: Option[], value: string, label?: string, onChange: (value: string) => void?}} props
     * @return {HTMLDivElement}
     */
    const createStoreSwitcher = (props) => {
        const wrapper = createElement('div', 'sq-store-switcher');

        wrapper.append(
            createDropdownField({
                className: 'sqp-store-switcher-dropdown ',
                placeholder: 'general.selectStorePlaceholder',
                variation: 'label-left',
                ...props
            })
        );

        return wrapper;
    };

    /**
     * Creates dropdown wrapper around the provided dropdown element.
     *
     * @param {ElementProps & DropdownComponentModel} props The properties.
     * @return {HTMLDivElement}
     */
    const createDropdownField = ({ className = '', label, description, variation, error, ...dropdownProps }) => {
        return createFieldWrapper(
            SequraFE.components.Dropdown.create(dropdownProps),
            label,
            description,
            variation,
            error,
            className
        );
    };

    /**
     * Creates a password input field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createPasswordField = ({ className = '', label, description, variation, error, onChange, ...rest }) => {
        const wrapper = createElement('div', `sq-password ${className}`);
        const input = createElement('input', 'sqp-field-component', '', { type: 'password', ...rest });
        const span = createElement('span');
        span.addEventListener('click', () => {
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        });
        onChange && input.addEventListener('change', (event) => onChange(event.currentTarget?.value));

        wrapper.append(input, span);

        return createFieldWrapper(wrapper, label, description, variation, error, '');
    };

    /**
     * Creates a text input field.
     *
     * @param {ElementProps & { type?: 'text' | 'number', variation?: 'label-left' }} props The properties.
     * @return {HTMLElement}
     */
    const createTextField = ({ className = '', label, description, variation, error, onChange, ...rest }) => {
        /** @type HTMLInputElement */
        const input = createElement('input', `sqp-field-component ${className}`, '', { type: 'text', ...rest });
        onChange && input.addEventListener('change', (event) => onChange(event.currentTarget?.value));

        return createFieldWrapper(input, label, description, variation, error, '');
    };

    /**
     * Creates a text area field.
     *
     * @param {ElementProps & { type?: 'text' | 'number', variation?: 'label-left' }} props The properties.
     * @return {HTMLElement}
     */
    const createTextArea = ({ className = '', label, description, variation, error, onChange, ...rest }) => {
        /** @type HTMLInputElement */
        const textArea = createElement('textarea', `sqp-field-component ${className}`, '', { ...rest });
        onChange && textArea.addEventListener('change', (event) => onChange(event.currentTarget?.value));

        return createFieldWrapper(textArea, label, description, variation, error, 'sqp-textarea-field');
    };

    /**
     * Creates a country input field.
     * @param {string?} countryCode
     * @param {string?} merchantId
     * @param {(value: string) => void?} onChange
     * @return {HTMLElement}
     */
    const createCountryField = ({ countryCode, merchantId, onChange }) => {
        const code = countryCode.toUpperCase();
        return createElement('div', 'sq-country-field-wrapper sqs--hidden', '', null, [
            createTextField({
                className: 'sq-text-input',
                name: `country_${code}`,
                label: `countries.${code}.label`,
                description: `countries.${code}.description`,
                value: merchantId,
                hidden: true,
                onChange
            })
        ]);
    };

    /**
     * Creates a number input field.
     *
     * @param {ElementProps & { type?: 'text' | 'number' }} props The properties.
     * @return {HTMLElement}
     */
    const createNumberField = (props) => {
        return createTextField({ type: 'number', step: '0.01', ...props });
    };

    /**
     * Creates a radio group field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createRadioGroupField = ({ name, value, className, options, label, description, error, onChange }) => {
        const wrapper = createElement('div', 'sq-radio-input-group');
        options.forEach((option) => {
            const label = createElement('label', 'sq-radio-input');
            const props = { type: 'radio', value: option.value, name };
            if (value === option.value) {
                props.checked = 'checked';
            }

            label.append(createElement('input', className, '', props), createElement('span', '', option.label));
            wrapper.append(label);
            onChange && label.addEventListener('click', () => onChange(option.value));
        });

        return createFieldWrapper(wrapper, label, description, error, '');
    };

    /**
     * Creates a toggle field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createToggleField = ({ className = '', label, description, error, onChange, value, ...rest }) => {
        /** @type HTMLInputElement */
        const checkbox = createElement('input', 'sqp-toggle-input', '', { type: 'checkbox', checked: value, ...rest });
        onChange && checkbox.addEventListener('change', () => onChange(checkbox.checked));

        const field = createElement('div', className + ' sq-field-wrapper sqt--toggle', '', null, [
            createElement('h3', 'sqp-field-title', label, null, [
                createElement('label', 'sq-toggle', '', null, [checkbox, createElement('span', 'sqp-toggle-round')])
            ])
        ]);

        if (description) {
            field.appendChild(createElement('span', 'sqp-field-subtitle', description));
        }

        if (error) {
            field.appendChild(createElement('span', 'sqp-input-error', error));
        }

        return field;
    };

    /**
     * Creates a checkbox field.
     *
     * @param {ElementProps} props The properties.
     * @return {HTMLElement}
     */
    const createCheckboxField = ({ className = '', label, description, error, onChange, value, ...rest }) => {
        /** @type HTMLInputElement */
        const checkbox = createElement('input', 'sqp-checkbox-input', '', { type: 'checkbox', checked: value, ...rest });
        onChange && checkbox.addEventListener('change', () => onChange(checkbox.checked));

        const field = createElement('div', className + ' sq-field-wrapper sqt--checkbox');

        if (label) {
            field.appendChild(createElement('h3', 'sqp-field-title', label));
        }

        field.appendChild(
            createElement('div', 'sqp-description-wrapper', '', null, [
                createElement('label', 'sq-checkbox', '', null, [checkbox, createElement('span', 'sqp-checkmark')]),
                description && createElement('span', 'sqp-field-subtitle', description)
            ])
        );

        if (error) {
            field.appendChild(createElement('span', 'sqp-input-error', error));
        }

        return field;
    };

    /**
     * Creates a button field.
     *
     * @param {ElementProps & { onClick?: () => void , buttonType?: string, buttonSize?: string,
     *     buttonLabel?: string, className?: string}} props The properties.
     * @return {HTMLElement}
     */
    const createButtonField = (
        {
            label,
            description,
            className,
            buttonType,
            buttonSize,
            buttonLabel,
            onClick,
            error
        }
    ) => {
        const button = createButton({
            type: buttonType,
            size: buttonSize,
            className: '',
            label: translationService.translate(buttonLabel),
            onClick: onClick
        });

        return createFieldWrapper(button, label, description, '', error, className);
    };

    /**
     * Creates a field with a link that looks like a button.
     *
     * @param {ElementProps & {text: string, href: string}} props
     */
    const createButtonLinkField = ({ label, text, description, href, error }) => {
        const buttonLink = createButtonLink({
            text: translationService.translate(text),
            className: '',
            href: href
        });

        return createFieldWrapper(buttonLink, label, description, '', error, '');
    };

    /**
     * Creates multi item selector wrapper around the provided multi item selector element.
     *
     * @param {ElementProps & MultiItemSelectorComponentModel} props The properties.
     * @return {HTMLDivElement}
     */
    const createMultiItemSelectorField = ({ label, description, variation, error, ...config }) => {
        return createFieldWrapper(
            SequraFE.components.MultiItemSelector.create(config),
            label,
            description,
            variation,
            error,
            ''
        );
    };

    /**
     * Creates a flash message.
     *
     * @param {string|string[]} messageKey
     * @param {'error' | 'warning' | 'success'} status
     * @param {number?} clearAfter Time in ms to remove alert message.
     * @return {HTMLElement}
     */
    const createFlashMessage = (messageKey, status, clearAfter) => {
        const hideHandler = () => {
            wrapper.remove();
        };
        const wrapper = createElement('div', `sq-alert sqt--${status}`);
        let messageBlock;
        if (Array.isArray(messageKey)) {
            const [titleKey, descriptionKey] = messageKey;
            messageBlock = createElement('div', 'sqp-alert-title', '', null, [
                createElement('span', 'sqp-message', '', null, [
                    createElement('span', 'sqp-message-title', titleKey),
                    createElement('span', 'sqp-message-description', descriptionKey)
                ])
            ]);
        } else {
            messageBlock = createElement('span', 'sqp-alert-title', messageKey);
        }

        const button = createButton({ onClick: hideHandler });

        if (clearAfter) {
            setTimeout(hideHandler, clearAfter);
        }

        wrapper.append(messageBlock, button);

        return wrapper;
    };

    /**
     * Creates a toaster message.
     *
     * @param {string} label
     * @param {number} timeout Clear timeout in ms.
     * @returns {HTMLElement}
     */
    const createToaster = (label, timeout = 5000) => {
        const toaster = createElement('div', 'sq-toaster', '', null, [
            createElement('span', 'sqp-toaster-title', label),
            createElement('button', 'sq-button', '', null, [createElement('span')])
        ]);

        toaster.children[1].addEventListener('click', () => toaster.remove());

        setTimeout(() => toaster.remove(), timeout);

        return toaster;
    };

    /**
     * Adds a page footer with save and cancel buttons.
     *
     * @param {() => void} onSave
     * @param {() => void} onCancel
     * @returns HTMLElement
     */
    const createPageFooter = ({ onSave, onCancel }) => {
        return createElement('div', 'sq-page-footer', '', null, [
            createElement('div', 'sqp-actions', '', null, [
                createButton({
                    className: 'sqp-cancel',
                    type: 'cancel',
                    size: 'medium',
                    label: 'general.cancel',
                    onClick: onCancel
                }),
                createButton({
                    className: 'sqp-save',
                    type: 'primary',
                    size: 'medium',
                    label: 'general.saveChanges',
                    onClick: onSave
                })
            ])
        ]);
    };

    /**
     * Creates form fields based on the fields configurations.
     *
     * @param {FormField[]} fields
     */
    const createFormFields = (fields) => {
        /** @type HTMLElement[] */
        const result = [];
        fields.forEach(({ type, ...rest }) => {
            switch (type) {
                case 'text':
                    result.push(createTextField({ ...rest, className: 'sq-text-input' }));
                    break;
                case 'number':
                    result.push(createNumberField({ ...rest, className: 'sq-text-input' }));
                    break;
                case 'dropdown':
                    result.push(createDropdownField(rest));
                    break;
                case 'radio':
                    result.push(createRadioGroupField(rest));
                    break;
                case 'checkbox':
                    result.push(createToggleField(rest));
                    break;
                case 'button':
                    result.push(createButtonField(rest));
                    break;
                case 'buttonLink':
                    result.push(createButtonLinkField(rest));
                    break;
            }

            rest.className && result[result.length - 1].classList.add(...rest.className.trim().split(' '));
        });

        return result;
    };

    /**
     * Creates a main header item.
     *
     * @param {{title?: string, text?: string}} params
     * @returns {HTMLElement}
     */
    const createPageHeading = ({ title, text }) => {
        return createElement('div', 'sqp-page-heading', '', null, [
            createElement('h3', 'sqp-page-title', title),
            createElement('span', 'sqp-description', text)
        ]);
    };

    /**
     * Creates a wizard sidebar.
     *
     * @param {{label: string, description?: string, href: string, isActive?: boolean, isCompleted?: boolean}[]} steps
     * @returns {HTMLElement}
     */
    const createWizardSidebar = ({ steps }) => {
        const wrapper = createElement('div', 'sq-wizard-sidebar');

        wrapper.append(
            ...steps.map((item) => {
                return createElement(
                    'a',
                    'sqp-step' + (item.isActive ? ' sqs--active' : item.isCompleted ? ' sqs--completed' : ''),
                    '',
                    {
                        href: item.href
                    },
                    [
                        createElement('span', 'sq-link-label', item.label),
                        item.description ? createElement('span', 'sq-link-description', item.description) : ''
                    ]
                );
            })
        );

        return wrapper;
    };

    /**
     * Creates a settings sidebar.
     *
     * @param {{label: string, icon: string, href: string, isActive?: boolean}[]} links
     * @returns {HTMLElement}
     */
    const createSettingsSidebar = ({ links }) => {
        const wrapper = createElement('ul', 'sq-settings-sidebar');

        wrapper.append(
            ...links.map((item) => {
                return createElement('li', 'sq-sidebar-item' + (item.isActive ? ' sqs--active' : ''), '', null, [
                    createElement(
                        'a',
                        'sq-sidebar-link' + ' sqm--' + item.icon,
                        '',
                        {
                            href: item.href
                        },
                        [createElement('span', '', item.label)]
                    )
                ]);
            })
        );

        return wrapper;
    };

    /**
    * Creates a support link FAB.
    *
    * @returns {HTMLElement}
    */
    const createSupportLink = () => {
        return createElement(
            'a',
            'sq-support-link',
            '',
            {
                href: SequraFE.translationService.translate('supportLink.link'),
                target: '_blank'
            },
            [
                createElement(
                    'span',
                    'sq-support-link-label',
                    'supportLink.label'
                )
            ]
        );
    }

    SequraFE.elementGenerator = {
        createElement,
        createElementFromHTML,
        createButton,
        createLoader,
        createDropdownField,
        createPasswordField,
        createTextField,
        createTextArea,
        createNumberField,
        createToggleField,
        createCheckboxField,
        createRadioGroupField,
        createFlashMessage,
        createStoreSwitcher,
        createButtonField,
        createButtonLink,
        createButtonLinkField,
        createMultiItemSelectorField,
        createCountryField,
        createFormFields,
        createPageFooter,
        createToaster,
        createPageHeading,
        createVersionBadge,
        createWizardSidebar,
        createSettingsSidebar,
        createSupportLink
    };
})();
