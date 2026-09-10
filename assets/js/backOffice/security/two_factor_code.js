/**
 * Saisie du code de vérification MFA en six cases, au format 3 - 3.
 *
 * Amélioration progressive : le template rend un champ texte unique, qui reste utilisable
 * si ce script ne se charge pas. Le script le masque et le remplace par six cases ; la
 * valeur saisie est recopiée dans le champ d'origine, seul transmis au serveur.
 *
 * Gère la frappe chiffre par chiffre, l'effacement, les flèches, le collage d'un code
 * complet (« 123456 », « 123 456 », « 123-456 ») et le remplissage automatique du
 * navigateur (autocomplete="one-time-code").
 */
const CODE_LENGTH = 6;
const SEPARATOR_AFTER = 3;

const onlyDigits = (value) => (value || '').replace(/\D/g, '');

document.addEventListener('DOMContentLoaded', () => {
    const source = document.querySelector('[data-auth-code-input]');
    if (!source) {
        return;
    }

    const group = document.createElement('div');
    group.className = 'auth-code';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-labelledby', 'auth-code-label');

    const digits = [];

    const sync = () => {
        source.value = digits.map((digit) => digit.value).join('');
    };

    // Répartit une suite de chiffres à partir de la case "start", puis place le curseur
    // sur la case suivante.
    const fill = (start, chars) => {
        let index = start;
        for (const char of chars) {
            if (index >= CODE_LENGTH) {
                break;
            }
            digits[index].value = char;
            index++;
        }
        sync();
        digits[Math.min(index, CODE_LENGTH - 1)].focus();
    };

    for (let index = 0; index < CODE_LENGTH; index++) {
        if (index === SEPARATOR_AFTER) {
            const separator = document.createElement('span');
            separator.className = 'auth-code-separator';
            separator.setAttribute('aria-hidden', 'true');
            separator.textContent = '-';
            group.appendChild(separator);
        }

        const digit = document.createElement('input');
        digit.type = 'text';
        digit.id = `auth-code-digit-${index + 1}`;
        digit.className = 'auth-code-digit';
        digit.inputMode = 'numeric';
        digit.autocomplete = index === 0 ? 'one-time-code' : 'off';
        digit.required = true;
        digit.setAttribute('aria-label', `Chiffre ${index + 1} sur ${CODE_LENGTH}`);

        // Refuse tout caractère non numérique tapé au clavier.
        digit.addEventListener('beforeinput', (event) => {
            if (event.inputType === 'insertText' && /\D/.test(event.data || '')) {
                event.preventDefault();
            }
        });

        digit.addEventListener('input', (event) => {
            // Frappe simple : le chiffre tapé remplace le contenu de la case.
            if (event.inputType === 'insertText') {
                digit.value = onlyDigits(event.data).slice(-1);
                sync();
                if (digit.value && index < CODE_LENGTH - 1) {
                    digits[index + 1].focus();
                }
                return;
            }

            // Remplissage automatique du navigateur ou saisie mobile : plusieurs chiffres
            // peuvent arriver d'un coup dans une seule case.
            const chars = onlyDigits(digit.value);
            if (chars.length > 1) {
                fill(index, chars);
                return;
            }

            // Effacement, ou chiffre saisi par composition (certains claviers mobiles).
            digit.value = chars;
            sync();
            if (digit.value && index < CODE_LENGTH - 1) {
                digits[index + 1].focus();
            }
        });

        digit.addEventListener('paste', (event) => {
            event.preventDefault();
            const clipboard = event.clipboardData || window.clipboardData;
            fill(index, onlyDigits(clipboard.getData('text')));
        });

        digit.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && digit.value === '' && index > 0) {
                event.preventDefault();
                digits[index - 1].value = '';
                digits[index - 1].focus();
                sync();
            } else if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                digits[index - 1].focus();
            } else if (event.key === 'ArrowRight' && index < CODE_LENGTH - 1) {
                event.preventDefault();
                digits[index + 1].focus();
            }
        });

        // Sélectionne le chiffre existant pour qu'une nouvelle frappe le remplace.
        digit.addEventListener('focus', () => digit.select());

        group.appendChild(digit);
        digits.push(digit);
    }

    // Le champ d'origine devient le simple porteur de la valeur envoyée au serveur.
    source.type = 'hidden';
    source.className = '';
    source.removeAttribute('required');
    source.removeAttribute('autofocus');
    source.value = '';
    source.insertAdjacentElement('afterend', group);

    const label = document.getElementById('auth-code-label');
    if (label) {
        label.htmlFor = digits[0].id;
    }

    digits[0].focus();
});
