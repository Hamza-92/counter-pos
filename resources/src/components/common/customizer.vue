<template>
  <div>
    <div class="customizer" :class="{ open: isOpen }">
      <div class="handle" @click="isOpen = !isOpen">
        <lucide-icon :name="isOpen ? 'x' : 'settings'" />
      </div>

      <vue-perfect-scrollbar
        :settings="{ suppressScrollX: true, wheelPropagation: false }"
        class="customizer-body ps rtl-ps-none"
      >
        <div class>
          <div class="card-header">
            <p class="mb-0">Sidebar Layout</p>
          </div>

          <div class="card-body">
            <div class="layout-options">
              <label class="layout-option" :class="{ active: getSidebarLayout === 'horizontal' }">
                <input 
                  type="radio" 
                  name="sidebar-layout" 
                  value="horizontal" 
                  @change="changeSidebarLayout('horizontal')"
                  :checked="getSidebarLayout === 'horizontal'"
                />
                <span class="option-label">
                  <lucide-icon name="rows-2" />
                  Sidebar 1
                </span>
              </label>
              <label class="layout-option" :class="{ active: getSidebarLayout === 'vertical' }">
                <input
                  type="radio"
                  name="sidebar-layout"
                  value="vertical"
                  @change="changeSidebarLayout('vertical')"
                  :checked="getSidebarLayout === 'vertical'"
                />
                <span class="option-label">
                  <lucide-icon name="rows-2" />
                  Sidebar 2
                </span>
              </label>
            </div>
          </div>
        </div>

        <div class>
          <div class="card-header">
            <p class="mb-0">Primary Color</p>
          </div>

          <div class="card-body">
            <div class="color-palette">
              <button
                v-for="preset in presetColors"
                :key="preset"
                type="button"
                class="color-swatch"
                :class="{ active: currentPrimaryColor.toLowerCase() === preset.toLowerCase() }"
                :style="{ background: preset }"
                :title="preset"
                @click="selectPrimaryColor(preset)"
              >
                <lucide-icon name="check" v-if="currentPrimaryColor.toLowerCase() === preset.toLowerCase()" />
              </button>
            </div>
            <div class="custom-color-row mt-3">
              <label class="mb-0 mr-2">Custom:</label>
              <input
                type="color"
                class="custom-color-input"
                :value="currentPrimaryColor"
                @input="selectPrimaryColor($event.target.value)"
              />
              <span class="ml-2 color-hex">{{ currentPrimaryColor }}</span>
            </div>
          </div>
        </div>

        <div class>
          <div class="card-header">
            <p class="mb-0">Dark Mode</p>
          </div>

          <div class="card-body">
            <label class="switch switch-primary mr-3 mt-2" v-b-popover.hover.left="'Dark Mode'">
              <input type="checkbox" :checked="getThemeMode.dark" @click="handleDarkModeToggle" />
              <span class="slider"></span>
            </label>
          </div>
        </div>

        <div
          class
          v-if="getThemeMode.layout != 'vertical-sidebar' && getThemeMode.layout != 'vertical-sidebar-two'"
        >
          <div class="card-header" id="headingOne">
            <p class="mb-0">RTL</p>
          </div>

          <div class="card-body">
            <label class="checkbox checkbox-primary">
              <input type="checkbox" id="rtl-checkbox" @change="changeThemeRtl" />
              <span>Enable RTL</span>
              <span class="checkmark"></span>
            </label>
          </div>
        </div>

         <div class>
          <div class="card-header">
            <p class="mb-0">Language</p>
          </div>

          <div class="card-body">
             <div class="menu-icon-language">

                <a v-for="lang in getAvailableLanguages" :key="lang.locale" @click="SetLocal(lang.locale)">
                  <img
                    :src="`/flags/${lang.flag}`"
                    :alt="lang.name"
                    class="flag-icon flag-icon-squared"
                    style="width: 20px; margin-right: 8px"
                  />
                  <span class="title-lang">{{ lang.name }}</span>
                </a>
            
            </div>
          </div>
        </div>
      </vue-perfect-scrollbar>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions } from "vuex";

export default {
  data() {
    return {
      isOpen: false,
      languages: [],
      presetColors: [
        '#663399',
        '#2f47c2',
        '#0f9d58',
        '#e91e63',
        '#ff9800',
        '#f44336',
        '#00bcd4',
        '#212121',
      ],
    };
  },

  computed: {
    ...mapGetters("config", ["getThemeMode", "getPrimaryColor"]),
    ...mapGetters(["getcompactLeftSideBarBgColor", "getAvailableLanguages", "getSidebarLayout"]),
    currentPrimaryColor() {
      return this.getPrimaryColor || '#663399';
    },
  },

  methods: {
    ...mapActions("config", ["changeThemeMode", "changeThemeRtl", "changeThemeLayout", "setPrimaryColor", "initPrimaryColor"]),
    ...mapActions([
      "changecompactLeftSideBarBgColor",
      "setSidebarLayout",
    ]),

    selectPrimaryColor(color) {
      if (!color) return;
      this.setPrimaryColor(color);
    },

    changeSidebarLayout(layout) {
      this.setSidebarLayout(layout);
      this.$root.$bvToast.toast(
        `Switched to ${layout} sidebar layout`,
        {
          title: 'Layout Changed',
          variant: 'success',
          solid: true,
          autoHideDelay: 2000
        }
      );
    },

    handleDarkModeToggle() {
      // Toggle the theme mode in Vuex store (client-side only, no database persistence)
      this.changeThemeMode();
    },

    SetLocal(locale) {
      this.$i18n.locale = locale;
      this.$store.dispatch("setLanguage", locale);
      Fire.$emit("ChangeLanguage");
      window.location.reload();
    },
    
    // async fetchLanguages() {
    //   try {
    //     const response = await axios.get("/languages");
    //     this.languages = response.data;
    //   } catch (error) {
    //     console.warn("Failed to load languages");
    //   }
    // },
  },

  async created() {
    this.$store.dispatch("loadAvailableLanguages");
    this.initPrimaryColor();
  }
};
</script>

<style lang="scss" scoped>
.layout-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.layout-option {
  display: flex;
  align-items: center;
  padding: 12px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
  position: relative;
}

.layout-option:hover {
  border-color: #663399;
  background: #f7f7f7;
}

.layout-option.active {
  border-color: #663399;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.layout-option input[type="radio"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.option-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 500;
  color: #47404f;
  width: 100%;
}

.option-label i {
  font-size: 20px;
  color: #663399;
}

.layout-option.active .option-label {
  color: #663399;
  font-weight: 600;
}

/* Dark mode support */
body.dark-theme .layout-option {
  border-color: #444;
  background: transparent;
}

body.dark-theme .layout-option:hover {
  border-color: #764ba2;
  background: rgba(118, 75, 162, 0.1);
}

body.dark-theme .layout-option.active {
  border-color: #764ba2;
  background: rgba(118, 75, 162, 0.2);
}

body.dark-theme .option-label {
  color: #e0e0e0;
}

body.dark-theme .layout-option.active .option-label {
  color: #fff;
}

.color-palette {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.color-swatch {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
  transition: transform 0.15s ease, border-color 0.15s ease;
}

.color-swatch:hover {
  transform: scale(1.08);
}

.color-swatch.active {
  border-color: #fff;
  outline: 2px solid #47404f;
}

.color-swatch i {
  color: #fff;
  font-size: 16px;
  font-weight: bold;
}

.custom-color-row {
  display: flex;
  align-items: center;
  font-size: 13px;
  color: #47404f;
}

.custom-color-input {
  width: 42px;
  height: 32px;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  padding: 2px;
  background: transparent;
  cursor: pointer;
}

.color-hex {
  font-family: monospace;
  font-size: 12px;
  color: #888;
}

body.dark-theme .custom-color-row,
body.dark-theme .color-hex {
  color: #e0e0e0;
}

body.dark-theme .color-swatch.active {
  outline-color: #fff;
}
</style>