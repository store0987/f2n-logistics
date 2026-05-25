export const API_BASE_URL = window.location.origin; 

export const apiFetch = (path, options) => fetch(`${API_BASE_URL}${path}`, options);

