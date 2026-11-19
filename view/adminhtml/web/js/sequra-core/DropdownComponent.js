if (!window.SequraFE) {
    window.SequraFE = {};
}

if (!window.SequraFE.components) {
    window.SequraFE.components = {};
}

(function () {
    /**
     * @typedef DropdownComponentModel
     *
     * @property {Option[]} options
     * @property {string?} name
     * @property {string?} value
     * @property {string?} placeholder
     * @property {(value: string) => void?} onChange
     * @property {(open: boolean) => void?} onOpenStateChange
     * @property {boolean?} updateTextOnChange
     * @property {boolean?} searchable
     */

    /**
     * Single-select dropdown component.
     *
     * @param {DropdownComponentModel} props
     *
     * @constructor
     */
    const DropdownComponent = ({
        options,
        name,
        value = '',
        placeholder,
        onChange,
        onOpenStateChange,
        updateTextOnChange = true,
        searchable = false
    }) => {
        const { elementGenerator: generator, translationService } = SequraFE;
        const filterItems = (text) => {
            const filteredItems = text
                ? options.filter((option) => option.label.toLowerCase().includes(text.toLowerCase()))
                : options;

            renderOptions(filteredItems);
        };

        const renderOptions = (options) => {
            list.innerHTML = '';
            if (options.length) {
                options.forEach((option) => {
                    const listItem = generator.createElement(
                        'li',
                        'sqp-dropdown-list-item' + (option === selectedItem ? ' sqs--selected' : ''),
                        option.label
                    );
                    list.append(listItem);

                    listItem.addEventListener('click', (e) => {
                        e.stopPropagation();
                        hiddenInput.value = option.value;
                        updateTextOnChange && (buttonSpan.innerHTML = translationService.translate(option.label));
                        list.classList.remove('sqs--show');
                        list.childNodes.forEach((node) => node.classList.remove('sqs--selected'));
                        listItem.classList.add('sqs--selected');
                        wrapper.classList.remove('sqs--active');
                        buttonSpan.classList.add('sqs--selected');
                        selectButton.classList.remove('sqs--search-active');
                        onChange && onChange(option.value);
                    });
                });
            } else {
                list.append(
                    generator.createElement('li', 'sqp-dropdown-list-item sqv--no-items', 'general.noItemsAvailable', {
                        onClick: (e) => {
                            e.stopPropagation();
                            searchInput?.focus();
                        }
                    })
                );
            }
        };

        const hiddenInput = generator.createElement('input', 'sqp-hidden-input', '', { type: 'hidden', name, value });
        const wrapper = generator.createElement('div', 'sq-single-select-dropdown');

        const selectButton = generator.createElement('button', 'sqp-dropdown-button sqp-field-component', '', {
            type: 'button'
        });
        const selectedItem = options.find((option) => option.value === value);
        const buttonSpan = generator.createElement(
            'span',
            selectedItem ? 'sqs--selected' : '',
            selectedItem ? selectedItem.label : placeholder
        );
        selectButton.append(buttonSpan);

        const searchInput = generator.createElement('input', 'sq-text-input', '', {
            type: 'text',
            placeholder: translationService.translate('general.search')
        });
        searchInput.addEventListener('input', (event) => filterItems(event.currentTarget?.value || ''));
        if (searchable) {
            selectButton.append(searchInput);
        }

        const list = generator.createElement('ul', 'sqp-dropdown-list');
        renderOptions(options);

        selectButton.addEventListener('click', (e) => {
            e.stopPropagation();

            if (searchable && selectButton.classList.contains('sqs--search-active')) {
                return;
            }

            list.classList.toggle('sqs--show');
            wrapper.classList.toggle('sqs--active');
            onOpenStateChange?.(list.classList.contains('sqs--show'));
            if (searchable) {
                selectButton.classList.toggle('sqs--search-active');
                if (selectButton.classList.contains('sqs--search-active')) {
                    searchInput.focus();
                    searchInput.value = '';
                    filterItems('');
                }
            }
        });

        document.documentElement.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target) && event.target !== wrapper) {
                list.classList.remove('sqs--show');
                wrapper.classList.remove('sqs--active');
                selectButton.classList.remove('sqs--search-active');
                onOpenStateChange?.(list.classList.contains('sqs--show'));
            }
        });

        wrapper.append(hiddenInput, selectButton, list);

        return wrapper;
    };

    SequraFE.components.Dropdown = {
        /**
         * @param {DropdownComponentModel} config
         * @returns {HTMLElement}
         */
        create: (config) => DropdownComponent(config)
    };
})();
