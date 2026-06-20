<template>
  <div class="main-content">
    <breadcumb :page="isNew ? $t('Create') : $t('Edit')" :folder="$t('Banners')" />

    <div v-if="loading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="wrapper">
      <b-form @submit.prevent="save">
        <div class="row">
          <!-- LEFT -->
          <div class="col-md-8">
            <b-form-group :label="$t('Title')">
              <b-form-input v-model="form.title" required />
            </b-form-group>

            <b-form-group :label="$t('Position')">
              <b-form-select v-model="form.position" :options="positions" />
              <!-- Recommended size hint -->
              <div class="mt-2 p-2 border rounded bg-light d-flex align-items-center">
                <div class="mr-2">🖼️</div>
                <div class="small" style=" color: #ffff; ">
                  <div class="mb-1">
                    <strong>{{ posInfo.label }}</strong> —
                    {{ $t('Recommended') }}: {{ posInfo.w }} × {{ posInfo.h }} ({{ posInfo.ratioText }})
                  </div>
                  <div v-if="imgW && imgH"
                       class="small">
                    {{ $t('Uploaded') }}: {{ imgW }} × {{ imgH }} ({{ uploadedRatioText }})
                    <span v-if="aspectMismatch">• {{ $t('Aspect ratio differs from recommendation') }}</span>
                    <span v-else>• {{ $t('Looks good') }} ✅</span>
                  </div>
                </div>
              </div>
            </b-form-group>

            <b-form-group :label="$t('Image')">
              <b-form-file accept="image/*" @change="onFile" />
              <div v-if="preview" class="mt-2">
                <img :src="preview" class="img-thumbnail" style="max-height:120px">
              </div>
            </b-form-group>
          </div>

          <!-- RIGHT -->
          <div class="col-md-4">
            <b-form-group :label="$t('Active')">
              <b-form-checkbox switch v-model="form.active">
                {{ form.active ? $t('Active') : $t('Disabled') }}
              </b-form-checkbox>
            </b-form-group>

            <b-button type="submit" :disabled="saving" variant="btn btn-primary btn-icon m-1">
              <span v-if="saving" class="spinner-border spinner-border-sm mr-2"></span>
              <lucide-icon name="check" /> {{ $t('Save') }}
            </b-button>
            <b-button variant="btn btn-outline-secondary m-1" @click="$router.back()">
              {{ $t('Cancel') }}
            </b-button>

            <!-- Quick cheat sheet -->
            <div class="mt-3 p-2 border rounded small text-muted">
              <div class="mb-1"><strong>{{ $t('Size guide') }}</strong></div>
              <ul class="mb-0 pl-3">
                <li>Top (Left/Right): 1200×600 (2:1)</li>
                <li>Center (Left/Right): 1200×600 (2:1)</li>
                <li>Footer (Left/Right): 1200×600 (2:1)</li>
              </ul>
              <div class="mt-1">{{ $t('Tip') }}: {{ $t('Use 2x for retina (e.g., 2400×1200).') }}</div>
            </div>
          </div>
        </div>
      </b-form>
    </b-card>
  </div>
</template>

<script>

export default {
  metaInfo: {
    title: "Store Banner Form"
  },
  props: { id: { type: String, required: false, default: 'new' } },
  data () {
    return {
      loading: true,
      saving: false,
      form: {
        title: '',
        position: 'top_left', // default
        active: true,
        image: null
      },
      preview: null,
      imgW: null,
      imgH: null,
      // Positions with labels
      positions: [
        { value: 'top_left',     text: 'Top — Left' },
        { value: 'top_right',    text: 'Top — Right' },
        { value: 'center_left',  text: 'Center — Left' },
        { value: 'center_right', text: 'Center — Right' },
        { value: 'footer_left',  text: 'Footer — Left' },
        { value: 'footer_right', text: 'Footer — Right' }
      ],
      // Recommended sizes per position (edit to your taste)
      rec: {
        top_left:     { w: 1200, h: 600, label: 'Top — Left' },
        top_right:    { w: 1200, h: 600, label: 'Top — Right' },
        center_left:  { w: 1200, h: 600, label: 'Center — Left' },
        center_right: { w: 1200, h: 600, label: 'Center — Right' },
        footer_left:  { w: 1200, h: 600, label: 'Footer — Left' },
        footer_right: { w: 1200, h: 600, label: 'Footer — Right' }
      }
    }
  },
  computed: {
    isNew () { return this.id === 'new' || !this.id },
    posInfo () {
      const meta = this.rec[this.form.position] || { w: 1200, h: 600, label: '—' }
      return {
        ...meta,
        ratioText: this.toRatioText(meta.w, meta.h)
      }
    },
    uploadedRatioText () {
      return (this.imgW && this.imgH) ? this.toRatioText(this.imgW, this.imgH) : '—'
    },
    aspectMismatch () {
      if (!this.imgW || !this.imgH) return false
      const recRatio = this.posInfo.w / this.posInfo.h
      const upRatio  = this.imgW / this.imgH
      // consider mismatch if > 5% difference
      return Math.abs(upRatio - recRatio) / recRatio > 0.05
    }
  },
  mounted () { this.init() },
  methods: {
    async init () {
      if (!this.isNew) {
        const { data } = await axios.get(`/store/banners/${this.id}`)
        // Normalize: keep existing image_url if provided by API; otherwise try image path
        Object.assign(this.form, { ...data, image: null })
        this.preview = data.image_url || (data.image ? `/${data.image}` : null)
        // Try to compute dimensions from preview
        if (this.preview) this.readImageDims(this.preview)
      }
      this.loading = false
    },

    onFile (e) {
      this.form.image = e.target.files[0]
      this.imgW = this.imgH = null
      if (this.form.image) {
        const r = new FileReader()
        r.onload = () => {
          this.preview = r.result
          this.readImageDims(this.preview)
        }
        r.readAsDataURL(this.form.image)
      }
    },

    readImageDims (src) {
      const img = new Image()
      img.onload = () => { this.imgW = img.width; this.imgH = img.height }
      img.src = src
    },

    toRatioText (w, h) {
      // reduce to simplest integer ratio if possible
      const gcd = (a, b) => b ? gcd(b, a % b) : a
      const g = gcd(Math.round(w), Math.round(h)) || 1
      const rw = Math.round(w / g), rh = Math.round(h / g)
      return `${rw}:${rh}`
    },

    async save () {
      this.saving = true
      try {
        const fd = new FormData()
        Object.entries(this.form).forEach(([k, v]) => {
          if (k === 'image') {
            if (v) fd.append('image', v)
          } else if (k === 'active') {
            fd.append('active', this.form.active ? '1' : '0') // boolean -> 1/0 for Laravel
          } else {
            fd.append(k, v ?? '')
          }
        })

        if (this.isNew) {
          await axios.post('/store/banners', fd)
        } else {
          await axios.post(`/store/banners/${this.id}?_method=PUT`, fd)
        }

        this.$bvToast && this.$bvToast.toast(this.$t('Saved_successfully'), {
          title: this.$t('Banners'), variant: 'success'
        })
        this.$router.push({ name: 'StoreBanners' })
      } finally {
        this.saving = false
      }
    }
  }
}
</script>
