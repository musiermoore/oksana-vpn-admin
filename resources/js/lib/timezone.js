const pad = (value) => String(value).padStart(2, '0');

export const getClientTimeZone = () => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
};

const extractParts = (value, timeZone = getClientTimeZone()) => {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const formatter = new Intl.DateTimeFormat('sv-SE', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });

    const entries = Object.fromEntries(
        formatter.formatToParts(date)
            .filter((part) => part.type !== 'literal')
            .map((part) => [part.type, part.value]),
    );

    return entries;
};

export const toDateTimeLocalInputValue = (value, timeZone = getClientTimeZone()) => {
    if (!value) {
        return '';
    }

    const parts = extractParts(value, timeZone);

    if (!parts) {
        return '';
    }

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};

export const formatDateTimeInTimeZone = (
    value,
    {
        locale = 'ru-RU',
        timeZone = getClientTimeZone(),
        withTimeZone = false,
    } = {},
) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(locale, {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZoneName: withTimeZone ? 'short' : undefined,
    }).format(date);
};

export const formatDateRangeInTimeZone = (start, end, options = {}) => {
    const startText = formatDateTimeInTimeZone(start, options);
    const endText = formatDateTimeInTimeZone(end, options);

    if (!startText || !endText) {
        return '';
    }

    return `${startText} - ${endText}`;
};

export const useClientTimezoneFields = (form, fieldNames = []) => {
    const clientTimeZone = getClientTimeZone();
    form.client_timezone = clientTimeZone;

    const setDateTimeFieldsFromUtc = (source = {}) => {
        fieldNames.forEach((fieldName) => {
            form[fieldName] = toDateTimeLocalInputValue(source?.[fieldName], clientTimeZone);
        });
    };

    return {
        clientTimeZone,
        setDateTimeFieldsFromUtc,
    };
};
