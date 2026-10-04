// resources/js/bootstrap.js

// Lodash (optionnel). Garde-le seulement si tu utilises window._ quelque part.
// Si tu n'en as pas besoin, commente ces 2 lignes.
import _ from 'lodash';
window._ = _;

// Axios
import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo / Pusher (optionnel - version Vite)
// import Echo from 'laravel-echo';
// import Pusher from 'pusher-js';

// window.Pusher = Pusher;

// window.Echo = new Echo({
//   broadcaster: 'pusher',
//   key: import.meta.env.VITE_PUSHER_APP_KEY,
//   cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//   forceTLS: true,
// });
