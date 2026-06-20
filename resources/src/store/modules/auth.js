import Vue from 'vue';
import Vuex from 'vuex';

Vue.use(Vuex);

const state = {
    isAuthenticated: false,
    Permissions: null,
    user: {},
    loading: false,
    error: null,
    notifs: 0,
    Default_Language: 'en',
    show_language: 1,
    availableLanguages: [],
    date_format: 'YYYY-MM-DD', // Default date format from database
    price_format: null, // Price format from database
    dark_mode: false, // Dark mode from database
};

const getters = {
    isAuthenticated: state => state.isAuthenticated,
    currentUser: state => state.user,
    currentUserPermissions: state => state.Permissions,
    loading: state => state.loading,
    notifs_alert: state => state.notifs,
    DefaultLanguage: state => state.Default_Language,
    show_language: state => state.show_language,
    error: state => state.error,
    getAvailableLanguages: state => state.availableLanguages,
    getDateFormat: state => state.date_format,
    getPriceFormat: state => state.price_format,
    getDarkMode: state => state.dark_mode,
    getTimezone: state => (state.user && state.user.timezone) || 'UTC',
};

const mutations = {
    setLoading(state, data) {
        state.loading = data;
        state.error = null;
    },
    setError(state, data) {
        state.error = data;
        state.loading = false;
    },
    clearError(state) {
        state.error = null;
    },
    setPermissions(state, Permissions) {
        state.Permissions = Permissions;
    },

    setUser(state, user) {
        state.user = user;
    },

    SetDefaultLanguage(state, { i18n, Language }) {
        if (i18n) {
            i18n.locale = Language;
        }
        state.Default_Language = Language;
    },

    Notifs_alert(state, notifs) {
        state.notifs = notifs;
    },
    show_language(state, show_language) {
        state.show_language = show_language;
    },
    setAvailableLanguages(state, languages) {
        state.availableLanguages = languages;
    },
    setDateFormat(state, dateFormat) {
        state.date_format = dateFormat;
    },
    setPriceFormat(state, priceFormat) {
        state.price_format = priceFormat;
    },
    setDarkMode(state, darkMode) {
        state.dark_mode = darkMode;
    },
    logout(state) {
        state.user = null;
        state.Permissions = null;
        state.loading = false;
        state.error = null;
    },
};

const actions = {
    async refreshUserPermissions({ commit }, i18n) {
        try {
            const userAuth = await axios.get("get_user_auth");
            const { permissions, user, notifs } = userAuth.data;

            commit('setPermissions', permissions);
            commit('setUser', user);
            commit('Notifs_alert', notifs);
            commit('show_language', user.show_language);
            commit('SetDefaultLanguage', { i18n, Language: user.default_language || 'en' });
            // Set date format from database
            commit('setDateFormat', user.date_format || 'YYYY-MM-DD');
            // Set price format from database
            commit('setPriceFormat', user.price_format || null);
            // Track the system-wide dark_mode flag in the auth module so
            // settings UIs can still read it, but DO NOT sync it into the
            // config store (themeMode.dark). The active theme is a
            // per-browser preference owned by localStorage; syncing the
            // system value here would overwrite each user's choice on
            // every refresh.
            const darkMode = user.dark_mode ?? false;
            commit('setDarkMode', darkMode);
            // Sync customize button visibility from DB to config store
            const customizeVisible = user.customize_button_visible ?? true;
            commit('config/setCustomizeButtonVisible', customizeVisible, { root: true });

        } catch (error) {
            commit('setPermissions', null);
            commit('setUser', null);
            commit('Notifs_alert', null);
            commit('show_language', null);
            commit('SetDefaultLanguage', { i18n, Language: 'en' });
            commit('setDateFormat', 'YYYY-MM-DD');
            commit('setPriceFormat', null);
            commit('setDarkMode', false);
            // Don't touch config/themeMode.dark — it's a per-browser
            // preference owned by localStorage, not the auth flow.
        }
    },

     async loadAvailableLanguages({ commit }) {
        try {
            const response = await axios.get("/languages"); // must return: [{ name, locale, flag }]
            commit('setAvailableLanguages', response.data);
        } catch (error) {
            console.warn("⚠️ Failed to load languages:", error);
        }
    },

   logout({ commit }) {
    axios.post('/logout', {}, { baseURL: '' }) // override the baseURL here
        .then(() => {
            // Full page navigation OUT of the SPA
            window.location.replace('/login');
        });
    }
};

export default {
    state,
    getters,
    actions,
    mutations
};
