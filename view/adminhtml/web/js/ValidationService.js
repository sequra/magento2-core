if (!window.SequraFE) {
    window.SequraFE = {};
}

(function () {
    /**
     * @typedef ValidationMessage
     * @property {string} code The message code.
     * @property {string} field The field name that the error is related to.
     * @property {string} message The error message.
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
        let regex =
            /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

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
        let regex = /(https?:\/\/)([\w\-])+\.([a-zA-Z]{2,63})([\/\w-]*)*\/?\??([^#\n\r]*)?#?([^\n\r]*)/m;

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
     * Validates the provided JSON string and marks field invalid if the JSON is invalid.
     *
     * @param {HTMLElement} element
     * @param {string?} value JSON value.
     * @param {string?} message
     * @return {boolean}
     */
    const validateJson = (element, value, message) => {
        try {
            JSON.parse(value);
            removeError(element);

            return true;
        } catch (e) {
            setError(element, message);

            return false;
        }
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
        if (errorCondition) {
            setError(element, message);

            return false;
        }

        removeError(element);

        return true;
    };

    SequraFE.validationService = {
        setError,
        removeError,
        validateEmail,
        validateNumber,
        validateUrl,
        validateMaxLength,
        validateJson,
        validateField,
        validateRequiredField,
        handleValidationErrors
    };
})();
