import Vue from 'vue';
import App from './portal/App.vue';
import router from './portal/router';

window.axios = require('axios');
window.axios.defaults.baseURL = '/api/';
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.__PORTAL_CSRF__;
if (csrf) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

axios.interceptors.response.use(
  function (r) { return r; },
  function (err) {
    var status = err.response && err.response.status;
    var url = err.config && err.config.url;
    var isPortal = url && url.indexOf('/portal/') !== -1;
    var isGuestPage = window.location.pathname.indexOf('/portal/login') !== -1 || window.location.pathname.indexOf('/portal/set-password') !== -1;
    // 401 = not logged in to portal; 403 = e.g. admin not allowed -> send to portal login (do not call logout API)
    if (isPortal && (status === 401 || status === 403) && !isGuestPage) {
      window.location.replace('/portal/login');
    }
    return Promise.reject(err);
  }
);

Vue.config.productionTip = false;

new Vue({
  router,
  render: (h) => h(App),
}).$mount('#portal-app');
