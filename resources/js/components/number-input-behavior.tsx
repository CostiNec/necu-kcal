import { useEffect } from 'react';

function isNumberInput(
    target: EventTarget | Node | null,
): target is HTMLInputElement {
    return target instanceof HTMLInputElement && target.type === 'number';
}

function setNativeValue(input: HTMLInputElement, value: string) {
    const setter = Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        'value',
    )?.set;

    setter?.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function prepareNumberInput(input: HTMLInputElement) {
    input.inputMode = 'decimal';

    if (document.activeElement !== input && input.value === '') {
        setNativeValue(input, '0');
    }
}

export function NumberInputBehavior() {
    useEffect(() => {
        const prepareInputsWithin = (node: Node) => {
            if (isNumberInput(node)) {
                prepareNumberInput(node);
                return;
            }

            if (!(node instanceof Element)) return;

            node.querySelectorAll<HTMLInputElement>('input[type="number"]')
                .forEach(prepareNumberInput);
        };
        const handleFocus = (event: FocusEvent) => {
            if (isNumberInput(event.target) && event.target.value === '0') {
                setNativeValue(event.target, '');
            }
        };
        const handleBlur = (event: FocusEvent) => {
            if (isNumberInput(event.target) && event.target.value === '') {
                setNativeValue(event.target, '0');
            }
        };
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach(prepareInputsWithin);
            });
        });

        prepareInputsWithin(document.body);
        document.addEventListener('focusin', handleFocus);
        document.addEventListener('focusout', handleBlur);
        observer.observe(document.body, { childList: true, subtree: true });

        return () => {
            document.removeEventListener('focusin', handleFocus);
            document.removeEventListener('focusout', handleBlur);
            observer.disconnect();
        };
    }, []);

    return null;
}
