// Volání api.php. Chyba nese HTTP status a zprávu, kterou lze rovnou ukázat uživateli.

export class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

async function request(params, signal) {
    let response;
    try {
        response = await fetch(`api.php?${new URLSearchParams(params)}`, { signal });
    } catch (error) {
        if (error.name === 'AbortError') {
            throw error;
        }
        throw new ApiError('Nepodařilo se spojit se serverem. Zkontrolujte připojení.', 0);
    }

    const data = await response.json().catch(() => null);
    if (!response.ok) {
        throw new ApiError(data?.error ?? 'Na serveru nastala chyba.', response.status);
    }

    return data;
}

export const api = {
    parcelAt: (lat, lng) => request({ action: 'parcel', lat: lat.toFixed(7), lng: lng.toFixed(7) }),
    parcelById: (id) => request({ action: 'parcel', id }),
    suggest: (q, signal) => request({ action: 'suggest', q }, signal),
    resolve: (key, text) => request({ action: 'resolve', key, text }),
    district: () => request({ action: 'district' }),
};
