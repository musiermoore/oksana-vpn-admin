import axios from 'axios';
import { getClientTimeZone } from './lib/timezone';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['X-Client-Timezone'] = getClientTimeZone();
