export function safeRedirectPath(redirect) {
    if (typeof redirect !== 'string' || !redirect.startsWith('/') || redirect.startsWith('//')) {
        return null;
    }
    return redirect;
}

export function authRoute(redirect) {
    const path = safeRedirectPath(redirect);
    return path ? { path: '/auth', query: { redirect: path } } : { path: '/auth' };
}

export function registerRoute(redirect) {
    const path = safeRedirectPath(redirect);
    return path ? { path: '/register', query: { redirect: path } } : { path: '/register' };
}

export function postLoginPath(route) {
    return safeRedirectPath(route?.query?.redirect) || '/main';
}
