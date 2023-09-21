if (!window.SequraFE) {
    window.SequraFE = {};
}

if (!window.SequraFE.components) {
    window.SequraFE.components = {};
}

(function () {
    /**
     * @typedef MultiItemSelectorComponentModel
     *
     * @property {Option[]} options
     * @property {string?} name
     * @property {string?} value
     * @property {string?} placeholder
     * @property {(value: string[]) => void?} onChange
     * @property {boolean?} searchable
     */

    /**
     * Multi-select dropdown component.
     *
     * @param {MultiItemSelectorComponentModel} props
     *
     * @constructor
     */
    const MultiItemSelectorComponent = ({ options = [], name, onChange, value = '', searchable = true }) => {
        const { elementGenerator: generator } = SequraFE;
        const selectedItems = value ? value.split(',').map((item) => item.trim()) : [];
        const wrapper = generator.createElement('div', 'sq-multi-item-selector');
        const hiddenInput = generator.createElement('input', 'sqp-hidden-input', '', {
            type: 'hidden',
            name,
            value: selectedItems.join(',')
        });
        const removeItem = (item) => {
            selectedItems.splice(selectedItems.indexOf(item), 1);
            render(true);
        };

        const render = (focused = false) => {
            hiddenInput.value = selectedItems.join(',');
            SequraFE.templateService.clearComponent(wrapper);
            const availableOptions = options.filter((option) => !selectedItems.includes(option.value));
            const renderSelectedItem = (item) => {
                return generator.createElement(
                    'span',
                    'sqp-selected-item',
                    searchable ? options.find((o) => o.value === item).label : item,
                    null,
                    [
                        generator.createButton({
                            className: 'sqp-remove-button',
                            onClick: () => {
                                removeItem(item);
                                onChange && onChange(selectedItems);
                            }
                        })
                    ]
                );
            };

            let inputElement = SequraFE.components.Dropdown.create({
                searchable: true,
                options: availableOptions,
                onChange: (value) => {
                    selectedItems.push(value);
                    render(true);
                    onChange && onChange(selectedItems);
                },
                onOpenStateChange: (opened) => {
                    opened ? wrapper.classList.add('sqs--active') : wrapper.classList.remove('sqs--active');
                }
            });

            if (!searchable) {
                inputElement = generator.createElement('input', 'sq-multi-input', '', { type: 'text' });
                inputElement.addEventListener('blur', (event) => addItemToSelected(event.currentTarget.value.trim()))
                inputElement.addEventListener('keydown', (event) => {
                    if (event.keyCode === 13) {
                        event.preventDefault();
                        addItemToSelected(event.currentTarget.value.trim());
                    }
                });
            }

            const addItemToSelected = (inputValue) => {
                if (inputValue.length !== 0 && !selectedItems.includes(inputValue)) {
                    selectedItems.push(inputValue);
                    render(true);
                    onChange && onChange(selectedItems);
                }
            }

            wrapper.append(...selectedItems.map(renderSelectedItem), hiddenInput, inputElement);
            wrapper.addEventListener('click', () => {
                wrapper.classList.add('sqs--active');
                if (searchable) {
                    setTimeout(() => {
                        inputElement.querySelector('button').click();
                    }, 10);
                }
            });

            if (focused) {
                setTimeout(() => {
                    searchable ? inputElement.querySelector('button').click() : inputElement.focus();
                }, 10);
            }
        };

        render();

        return wrapper;
    };

    SequraFE.components.MultiItemSelector = {
        /**
         * @param {MultiItemSelectorComponentModel} config
         * @returns {HTMLElement}
         */
        create: (config) => MultiItemSelectorComponent(config)
    };
})();
