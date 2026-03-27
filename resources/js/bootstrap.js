import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF token depuis la balise meta (complément au cookie XSRF-TOKEN)
const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// Intercepteur 419 : session/token expirés → rafraîchissement automatique
window.axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 419) {
            try {
                // Récupérer un nouveau token CSRF (route publique légère)
                const { data } = await axios.get('/csrf-token');
                const newToken = data.token;

                // Mettre à jour la balise meta et les headers axios
                const meta = document.head.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', newToken);
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;

                // Rejouer la requête originale avec le nouveau token
                error.config.headers['X-CSRF-TOKEN'] = newToken;
                return axios(error.config);
            } catch {
                // Si le refresh échoue (session vraiment expirée) → login
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);
