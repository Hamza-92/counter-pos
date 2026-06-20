import Vue from 'vue';
import VueI18n from 'vue-i18n';
import axios from 'axios';

Vue.use(VueI18n);

export const loadI18n = async () => {
  const userLang = localStorage.getItem('language') || 'en';

  let dbMessages = {};
  try {
    const isBaseURLSet = axios.defaults.baseURL && axios.defaults.baseURL !== '/';
    const endpoint = isBaseURLSet
      ? `translations/${userLang}`      // baseURL will apply
      : `/api/translations/${userLang}`; // full path for unauthenticated pages

    const response = await axios.get(endpoint);
    dbMessages = response.data;
  } catch (error) {
    console.warn("⚠️ Failed to load DB translations. No fallback used.");
  }

  const messages = {
    [userLang]: dbMessages || {}
  };

  const i18n = new VueI18n({
    locale: userLang,
    fallbackLocale: 'en',
    messages,
    silentTranslationWarn: true,
  });

  return i18n;
};
