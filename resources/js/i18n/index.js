import { usePage } from '@inertiajs/react';
import { messages } from './catalog';

function get(source, path) {
    return path.split('.').reduce((current, part) => (
        current && Object.prototype.hasOwnProperty.call(current, part) ? current[part] : undefined
    ), source);
}

function interpolate(template, replacements = {}) {
    if (typeof template !== 'string') {
        return template;
    }

    return template.replace(/:(\w+)/g, (match, key) => (
        replacements[key] === undefined || replacements[key] === null ? match : String(replacements[key])
    ));
}

export function localeTag(locale) {
    if (locale === 'uk') {
        return 'uk-UA';
    }

    if (locale === 'ru') {
        return 'ru-RU';
    }

    return 'en-US';
}

export function translate(locale, key, replacements = {}) {
    const fromLocale = get(messages[locale] ?? {}, key);
    const fromEnglish = get(messages.en, key);
    const value = fromLocale ?? fromEnglish ?? key;

    return interpolate(value, replacements);
}

export function hasTranslation(locale, key) {
    return get(messages[locale] ?? {}, key) !== undefined || get(messages.en, key) !== undefined;
}

export function enumLabel(locale, group, value) {
    if (value === null || value === undefined || value === '') {
        return translate(locale, 'common.dash');
    }

    const key = `enums.${group}.${value}`;
    if (hasTranslation(locale, key)) {
        return translate(locale, key);
    }

    return String(value).replaceAll('_', ' ');
}

export function statusLabel(locale, status) {
    return enumLabel(locale, 'status', status);
}

export function formatDateTime(value, locale = 'en') {
    if (!value) {
        return translate(locale, 'common.dash');
    }

    try {
        return new Date(value).toLocaleString(localeTag(locale));
    } catch {
        return value;
    }
}

export function useT() {
    const { locale = 'en' } = usePage().props;
    const current = messages[locale] ? locale : 'en';

    const t = (key, replacements = {}) => translate(current, key, replacements);
    t.locale = current;
    t.tag = localeTag(current);
    t.enum = (group, value) => enumLabel(current, group, value);
    t.status = (status) => statusLabel(current, status);
    t.date = (value) => formatDateTime(value, current);
    t.has = (key) => hasTranslation(current, key);

    return t;
}

export { messages };
