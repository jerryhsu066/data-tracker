// Convert base64url string to ArrayBuffer
function base64urlToBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64.padEnd(base64.length + (4 - base64.length % 4) % 4, '='));
    return Uint8Array.from(binary, c => c.charCodeAt(0)).buffer;
}

// Convert ArrayBuffer to base64url string
function bufferToBase64url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (const b of bytes) binary += String.fromCharCode(b);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Serialize a PublicKeyCredential returned by the browser to a JSON string
function serializeCredential(credential) {
    const response = credential.response;
    const obj = {
        id:    credential.id,
        rawId: bufferToBase64url(credential.rawId),
        type:  credential.type,
        response: response.attestationObject
            ? {
                clientDataJSON:    bufferToBase64url(response.clientDataJSON),
                attestationObject: bufferToBase64url(response.attestationObject),
                transports:        response.getTransports?.() ?? [],
            }
            : {
                clientDataJSON:    bufferToBase64url(response.clientDataJSON),
                authenticatorData: bufferToBase64url(response.authenticatorData),
                signature:         bufferToBase64url(response.signature),
                userHandle:        response.userHandle ? bufferToBase64url(response.userHandle) : null,
            },
        clientExtensionResults: credential.getClientExtensionResults?.() ?? {},
    };
    return JSON.stringify(obj);
}

// Prepare creation options received from the server for use with navigator.credentials.create()
function prepareCreationOptions(options) {
    return {
        publicKey: {
            ...options,
            challenge:  base64urlToBuffer(options.challenge),
            user: {
                ...options.user,
                id: new TextEncoder().encode(options.user.id),
            },
            excludeCredentials: (options.excludeCredentials ?? []).map(c => ({
                ...c,
                id: base64urlToBuffer(c.id),
            })),
        },
    };
}

// Prepare request options received from the server for use with navigator.credentials.get()
function prepareRequestOptions(options) {
    return {
        publicKey: {
            ...options,
            challenge: base64urlToBuffer(options.challenge),
            allowCredentials: (options.allowCredentials ?? []).map(c => ({
                ...c,
                id: base64urlToBuffer(c.id),
            })),
        },
    };
}

export async function registerPasskey(serverOptions) {
    const options    = prepareCreationOptions(serverOptions);
    const credential = await navigator.credentials.create(options);
    return serializeCredential(credential);
}

export async function authenticateWithPasskey(serverOptions) {
    const options    = prepareRequestOptions(serverOptions);
    const credential = await navigator.credentials.get(options);
    return serializeCredential(credential);
}

export const isWebauthnSupported = () =>
    window.PublicKeyCredential !== undefined &&
    typeof window.PublicKeyCredential === 'function';
