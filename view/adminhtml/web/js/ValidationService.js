if (!window.SequraFE) {
    window.SequraFE = {};
}

if (typeof SequraFE.regex === 'undefined'){
    throw new Error('SequraFE.regex is not defined. Please provide the regex definitions before loading the ValidationService.');
}

(function () {
    /**
     * @typedef ValidationMessage
     * @property {string} code The message code.
     * @property {string} field The field name that the error is related to.
     * @property {string} message The error message.
     */

    /**
     * @typedef CategoryPaymentMethod
     * @property {string|null} category
     * @property {string|null} product
     * @property {string|null} title
     */

    const validationRule = {
        numeric: 'numeric',
        integer: 'integer',
        required: 'required',
        greaterThanZero: 'greaterThanZero',
        minValue: 'minValue',
        maxValue: 'maxValue',
        nonNegative: 'nonNegative',
        greaterThanX: 'greaterThanX'
    };

    const { templateService, utilities, translationService } = SequraFE;

    /**
     * Validates if the input has a value. If the value is not set, adds an error class to the input element.
     *
     * @param {HTMLInputElement|HTMLSelectElement} input
     * @param {string?} message
     * @return {boolean}
     */
    const validateRequiredField = (input, message) => {
        return validateField(input, !input.value?.trim() || (input.type === 'checkbox' && !input.checked), message);
    };

    /**
     * Validates a numeric input.
     *
     * @param {HTMLInputElement} input
     * @param {string?} message
     * @return {boolean} Indication of the validity.
     */
    const validateNumber = (input, message) => {
        const ruleset = input.dataset?.validationRule ? input.dataset.validationRule.split(',') : [];
        let result = true;

        if (!validateField(input, Number.isNaN(input.value), message)) {
            return false;
        }

        const value = Number(input.value);
        ruleset.forEach((rule) => {
            if (!result) {
                // break on first false rule
                return;
            }

            let condition = false;
            let subValue = null;
            if (rule.includes('|')) {
                [rule, subValue] = rule.split('|');
            }

            // condition should be positive for valid values
            switch (rule) {
                case validationRule.integer:
                    condition = Number.isInteger(value);
                    break;
                case validationRule.greaterThanZero:
                    condition = value > 0;
                    break;
                case validationRule.minValue:
                    condition = value >= Number(subValue);
                    break;
                case validationRule.maxValue:
                    condition = value <= Number(subValue);
                    break;
                case validationRule.nonNegative:
                    condition = value >= 0;
                    break;
                case validationRule.required:
                    condition = !!input.value?.trim();
                    break;
                case validationRule.greaterThanX:
                    condition = value >= Number(document.querySelector(`input[name="${subValue}"]`)?.value);
                    break;
                default:
                    return;
            }

            if (!validateField(input, !condition, message)) {
                result = false;
            }
        });

        return result;
    };

    /**
     * Validates if the input is a valid email. If not, adds the error class to the input element.
     *
     * @param {HTMLInputElement} input
     * @param {string?} message
     * @return {boolean}
     */
    const validateEmail = (input, message) => {
        const regex = new RegExp(SequraFE.regex.email);
        return validateField(input, !regex.test(String(input.value).toLowerCase()), message);
    };

    /**
     * Validates if the input is a valid URL. If not, adds an error class to the input element.
     *
     * @param {HTMLInputElement} input
     * @param {string?} message
     * @return {boolean}
     */
    const validateUrl = (input, message) => {
        const regex = new RegExp(SequraFE.regex.url);
        return validateField(input, !regex.test(String(input.value).toLowerCase()), message);
    };

    /**
     * Validates if the input field is longer than a specified number of characters.
     * If so, adds an error class to the input element.
     *
     * @param {HTMLInputElement} input
     * @param {string?} message
     * @return {boolean}
     */
    const validateMaxLength = (input, message) => {
        return validateField(input, input.dataset.maxLength && input.value.length > input.dataset.maxLength, message);
    };

    /**
     * Handles validation errors. These errors come from the back end.
     *
     * @param {ValidationMessage[]} errors
     */
    const handleValidationErrors = (errors) => {
        for (const error of errors) {
            markFieldGroupInvalid(`[name=${error.field}]`, error.message);
        }
    };

    /**
     * Marks a field as invalid.
     *
     * @param {string} fieldSelector The field selector.
     * @param {string} message The message to display.
     * @param {Element} [parent] A parent element.
     */
    const markFieldGroupInvalid = (fieldSelector, message, parent) => {
        if (!parent) {
            parent = templateService.getMainPage();
        }

        const inputEl = parent.querySelector(fieldSelector);
        inputEl && setError(inputEl, message);
    };

    /**
     * Sets error for an input.
     *
     * @param {HTMLElement} element
     * @param {string?} message
     */
    const setError = (element, message) => {
        const parent = utilities.getAncestor(element, 'sq-field-wrapper');
        parent && parent.classList.add('sqs--error');
        if (message) {
            let errorField = parent.querySelector('.sqp-input-error');
            if (!errorField) {
                errorField = SequraFE.elementGenerator.createElement('span', 'sqp-input-error', message);
                parent.append(errorField);
            }

            errorField.innerHTML = translationService.translate(message);
        }
    };

    /**
     * Removes error from input form group element.
     *
     * @param {HTMLElement} element
     */
    const removeError = (element) => {
        const parent = utilities.getAncestor(element, 'sq-field-wrapper');
        parent && parent.classList.remove('sqs--error');
    };

    /**
     * Validates if the input is a valid css selector. If not, adds the error class to the input element.
     *
     * @param {HTMLInputElement} input
     * @param {boolean} required
     * @param {string?} message
     * @return {boolean}
     */
    const validateCssSelector = (input, required, message) => {
        let isValid = false;
        try {
            document.querySelector(input.value);
            isValid = true;
        } catch {
            isValid = !required && !input.value;
        }

        return validateField(input, !isValid, message);
    };

    /**
     * Validates if the value is a valid date or duration following ISO 8601 format.
     *
     * @param {string} str
     * @return {boolean}
     */
    const validateDateOrDuration = (str) => {
        const regex = new RegExp(SequraFE.regex.dateOrDuration);
        return regex.test(str) && 'P' !== str && !str.endsWith('T');
    };

    /**
     * Check if a given string is a valid IP address.
     *
     * @param {string} str
     *
     * @returns {boolean}
     */
    const validateIpAddress = (str) => {
        const regex = new RegExp(SequraFE.regex.ip);
        return regex.test(str);
    };

    /**
     * Validates the provided JSON string and marks field invalid if the JSON is invalid.
     *
     * @param {HTMLElement} element
     * @param {boolean} required If true, the field is required.
     * @param {string?} message
     * @return {boolean}
     */
    const validateJSON = (element, required, message) => {
        let isValid = false;
        try {
            JSON.parse(element.value);
            isValid = true;
        } catch (e) {
            isValid = !required && !element.value;
        }
        return validateField(element, !isValid, message);
    };

    /**
     * Validates the condition against the input field and marks field invalid if the error condition is met.
     *
     * @param {HTMLElement} element
     * @param {boolean} errorCondition Error condition.
     * @param {string?} message
     * @return {boolean}
     */
    const validateField = (element, errorCondition, message) => {
        removeError(element);
        if (errorCondition) {
            setError(element, message);
            return false;
        }

        return true;
    };

    /**
     * Validates custom locations.
     * @param {Array<HTMLElement>} element Each element in the array should be the details element containing the
     *     custom location data.
     * @param {Array<Object>} value
     * @param {string} value[].selForTarget CSS selector for the target element.
     * @param {string} value[].widgetStyles JSON string representing the styles for the widget.
     * @param {string} value[].product Product name.
     * @param {CategoryPaymentMethod[]} allowedPaymentMethods Array of allowed payment methods.
     * @return {boolean}
     */
    const validateCustomLocations = (element, value, allowedPaymentMethods) => {
        let isValid = true;

        for (let i = 0; i < element.length; i++) {
            const location = value[i];
            const detailsElement = element[i];

            isValid = validateCssSelector(
                detailsElement.querySelector('input[type="text"]'),
                false,
                'validation.invalidField'
            ) && isValid;

            isValid = validateJSON(
                detailsElement.querySelector('textarea'),
                false,
                'validation.invalidJSON'
            ) && isValid;

            let isPaymentMethodValid = allowedPaymentMethods.some(pm => pm.product === location.product)
                && value.filter(l => l.product === location.product).length === 1;

            isValid = validateField(
                detailsElement.querySelector('select'),
                !isPaymentMethodValid,
                'validation.invalidField'
            ) && isValid;
        }

        return isValid;
    }

    /**
     * Validates related fields and disables the footer if any of them is invalid.
     * @param {string} parentField The parent field name that controls the visibility of related fields.
     * @param {Array<Object>} fieldsRelationships An array of objects containing the relationships between fields.
     * @param {string} fieldsRelationships[].parentField The parent field name that controls the visibility of related
     *     fields.
     * @param {Array<string>} fieldsRelationships[].requiredFields An array of field names that are required when the
     *     parent field is shown.
     * @param {Array<string>} fieldsRelationships[].fields An array of field names that are related to the parent
     *     field.
     * @param {boolean} show Whether to show or hide the related fields.
     * @return {boolean} Returns true if all related fields are valid, false otherwise.
     */
    const validateRelatedFields = (parentField, fieldsRelationships, show) => {
        if (!show) {
            return true;
        }

        let isValid = true;
        const { requiredFields, fields } = fieldsRelationships.find(group => group.parentField === parentField) || { requiredFields: [], fields: [] };
        for (let i = 0; i < fields.length; i++) {
            isValid = validateCssSelector(
                document.querySelector(`[name="${fields[i]}"]`),
                requiredFields.includes(fields[i]),
                'validation.invalidField'
            ) && isValid;
        }
        return isValid;
    }

    SequraFE.validationService = {
        setError,
        removeError,
        validateEmail,
        validateNumber,
        validateUrl,
        validateMaxLength,
        validateCssSelector,
        validateJSON,
        validateRelatedFields,
        validateCustomLocations,
        validateField,
        validateRequiredField,
        validateDateOrDuration,
        validateIpAddress,
        handleValidationErrors
    };
})();
