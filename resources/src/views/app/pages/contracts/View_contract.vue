<template>
  <div class="main-content">
    <breadcumb :page="$t('Contract') || 'Contract'" :folder="$t('Contracts') || 'Contracts'"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <template v-else-if="contract">
      <!-- Summary panel -->
      <b-card class="summary-panel mb-4">
        <b-row>
          <b-col md="8">
            <h4 class="mb-3">{{ contract.contract_number }} — {{ contract.subject }}</h4>
            <b-row>
              <b-col sm="6" md="4">
                <strong>{{ contract.party_type === 'employee' ? ($t('Employee') || 'Employee') : ($t('Customer') || 'Customer') }}:</strong>
                {{ contract.party_name || (contract.party_type === 'employee' ? contract.employee_name : contract.client_name) }}
              </b-col>
              <b-col sm="6" md="4" v-if="contract.project_name"><strong>Project:</strong> {{ contract.project_name }}</b-col>
              <b-col sm="6" md="4"><strong>Value:</strong> ${{ Number(contract.value).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</b-col>
              <b-col sm="6" md="4"><strong>Type:</strong> {{ contract.type || '-' }}</b-col>
              <b-col sm="6" md="4"><strong>Start:</strong> {{ contract.start_date }}</b-col>
              <b-col sm="6" md="4"><strong>End:</strong> {{ contract.end_date }}</b-col>
              <b-col sm="6" md="4"><strong>Status:</strong> <span :class="['badge', 'status-' + contract.status]">{{ contract.status }}</span></b-col>
            </b-row>
            <!-- Signature info -->
            <div v-if="contract.signer_name || contract.signed_at || contract.signed_ip" class="signature-info mt-3 p-3 bg-light rounded">
              <strong>Signature:</strong>
              <span v-if="contract.signer_name"> {{ contract.signer_name }}</span>
              <span v-if="contract.signed_at"> — {{ formatDate(contract.signed_at) }}</span>
              <span v-if="contract.signed_ip"> — IP: {{ contract.signed_ip }}</span>
            </div>
          </b-col>
          <b-col md="4" class="text-right">
            <b-button variant="outline-primary" @click="previewContractPdf" class="mb-2 mr-2" :disabled="pdfPreviewLoading">
              <lucide-icon name="eye" /> {{ pdfPreviewLoading ? 'Loading...' : 'Preview PDF' }}
            </b-button>
            <b-button variant="primary" @click="downloadPdf" class="mb-2">
              <lucide-icon name="download" /> Download PDF
            </b-button>
            <br />
            <router-link :to="'/app/contracts/edit/' + contract.id" class="btn btn-outline-primary">Edit Contract</router-link>
          </b-col>
        </b-row>
      </b-card>

      <!-- Tabs -->
      <b-tabs v-model="tabIndex" content-class="mt-3">
        <b-tab title="Contract" active>
          <b-card>
            <div v-if="contract.description" class="contract-description-html" v-html="sanitizedDescription"></div>
            <p v-else class="text-muted">No description.</p>
            <p v-if="contract.party_type !== 'employee'" class="text-muted small mt-2">Hide from customer: {{ contract.hide_from_customer ? 'Yes' : 'No' }}</p>
          </b-card>
        </b-tab>

        <b-tab title="Attachments">
          <b-card>
            <b-form-group v-if="canEdit">
              <input type="file" ref="fileInput" @change="uploadAttachment" style="display: none" />
              <b-button variant="outline-primary" size="sm" @click="$refs.fileInput.click()"><lucide-icon name="plus" /> Upload</b-button>
            </b-form-group>
            <b-list-group>
              <b-list-group-item v-for="a in contract.attachments" :key="a.id" class="d-flex justify-content-between align-items-center">
                <span>{{ a.file_name }}</span>
                <span>
                  <b-button size="sm" variant="outline-info" @click="downloadAttachment(a)">Download</b-button>
                  <b-button v-if="canEdit" size="sm" variant="outline-danger" class="ml-1" @click="deleteAttachment(a.id)">Delete</b-button>
                </span>
              </b-list-group-item>
              <b-list-group-item v-if="!contract.attachments || !contract.attachments.length" class="text-muted">No attachments.</b-list-group-item>
            </b-list-group>
          </b-card>
        </b-tab>

        <b-tab title="Comments">
          <b-card>
            <b-form-group v-if="canEdit || true">
              <b-form-textarea v-model="newComment" rows="2" placeholder="Add a comment..." />
              <b-button size="sm" variant="primary" class="mt-2" @click="addComment" :disabled="!newComment.trim()">Post</b-button>
            </b-form-group>
            <div v-for="c in contract.comments" :key="c.id" class="border-bottom pb-2 mb-2">
              <small class="text-muted">{{ c.user_name }} — {{ formatDate(c.created_at) }}</small>
              <p class="mb-0">{{ c.body }}</p>
              <b-button v-if="canEdit" size="sm" variant="link" class="text-danger p-0" @click="deleteComment(c.id)">Delete</b-button>
            </div>
            <p v-if="!contract.comments || !contract.comments.length" class="text-muted">No comments.</p>
          </b-card>
        </b-tab>

        <b-tab title="Renewal History">
          <b-card>
            <b-form-group v-if="canEdit">
              <b-row>
                <b-col md="4"><b-form-input v-model="renewalForm.renewal_date" type="date" placeholder="Renewal date" /></b-col>
                <b-col md="4"><b-form-input v-model="renewalForm.new_end_date" type="date" placeholder="New end date" /></b-col>
                <b-col md="4"><b-button size="sm" variant="primary" @click="addRenewal">Add renewal</b-button></b-col>
              </b-row>
              <b-form-input v-model="renewalForm.notes" class="mt-2" placeholder="Notes (optional)" />
            </b-form-group>
            <b-list-group>
              <b-list-group-item v-for="r in contract.renewals" :key="r.id">
                {{ r.renewal_date }} → End: {{ r.new_end_date }} <span v-if="r.notes">— {{ r.notes }}</span>
              </b-list-group-item>
              <b-list-group-item v-if="!contract.renewals || !contract.renewals.length" class="text-muted">No renewals.</b-list-group-item>
            </b-list-group>
          </b-card>
        </b-tab>

        <b-tab title="Tasks">
          <b-card>
            <b-form-group v-if="canEdit">
              <b-input-group>
                <b-form-input v-model="taskForm.title" placeholder="Task title" />
                <b-form-input v-model="taskForm.due_date" type="date" />
                <b-button variant="primary" @click="addTask">Add task</b-button>
              </b-input-group>
            </b-form-group>
            <b-table :items="contract.tasks" :fields="taskFields" small>
              <template #cell(status)="row">
                <b-badge :variant="row.value === 'completed' ? 'success' : 'secondary'">{{ row.value }}</b-badge>
              </template>
              <template #cell(actions)="row" v-if="canEdit">
                <b-button size="sm" variant="outline-danger" @click="deleteTask(row.item.id)">Delete</b-button>
              </template>
            </b-table>
            <p v-if="!contract.tasks || !contract.tasks.length" class="text-muted">No tasks.</p>
          </b-card>
        </b-tab>

        <b-tab title="Notes">
          <b-card>
            <b-form-group v-if="canEdit || true">
              <b-form-textarea v-model="newNote" rows="2" placeholder="Add a note..." />
              <b-button size="sm" variant="primary" class="mt-2" @click="addNote" :disabled="!newNote.trim()">Add note</b-button>
            </b-form-group>
            <div v-for="n in contract.notes" :key="n.id" class="border-bottom pb-2 mb-2">
              <small class="text-muted">{{ n.user_name }} — {{ formatDate(n.created_at) }}</small>
              <p class="mb-0" style="white-space: pre-line;">{{ n.content }}</p>
              <b-button v-if="canEdit" size="sm" variant="link" class="text-danger p-0" @click="deleteNote(n.id)">Delete</b-button>
            </div>
            <p v-if="!contract.notes || !contract.notes.length" class="text-muted">No notes.</p>
          </b-card>
        </b-tab>

        <b-tab title="Templates">
          <b-card>
            <div class="d-flex justify-content-between align-items-start mb-2">
              <p class="text-muted small mb-0">Use merge fields in templates: {contract_number}, {customer_name}, {start_date}, {end_date}, {contract_value}, etc.</p>
              <b-button v-if="canEdit" size="sm" variant="primary" @click="openTemplateForm()">+ New template</b-button>
            </div>
            <b-link @click="loadMergeFields">View available merge fields</b-link>
            <div v-if="mergeFields.length" class="mt-2 p-2 bg-light rounded">
              <div v-for="f in mergeFields" :key="f.key" class="small">{{ f.key }} — {{ f.label }}</div>
            </div>

            <div v-if="templates.length" class="mt-3">
              <b-table :items="templates" :fields="templateFields" small striped hover>
                <template #cell(actions)="row">
                  <b-button size="sm" variant="link" class="p-0 mr-2" @click="editTemplate(row.item)" v-if="canEdit">Edit</b-button>
                  <b-button size="sm" variant="link" class="p-0 text-danger" @click="deleteTemplate(row.item.id)" v-if="canEdit">Delete</b-button>
                </template>
              </b-table>
            </div>
            <p v-else class="text-muted small mt-3 mb-0">No templates yet. Click <em>New template</em> to create one.</p>

            <hr />
            <h6>Preview against this contract</h6>
            <div class="mt-2">
              <b-form-select v-model="selectedTemplateId" :options="templateOptions" class="mb-2" @change="loadTemplatePreview" />
              <div v-if="selectedTemplateId" class="mb-2">
                <b-button size="sm" variant="outline-primary" @click="previewTemplatePdf" :disabled="pdfPreviewLoading" class="mr-2">
                  <lucide-icon name="eye" /> {{ pdfPreviewLoading ? 'Loading...' : 'Preview as PDF' }}
                </b-button>
                <b-button size="sm" variant="outline-secondary" @click="downloadTemplatePdf">
                  <lucide-icon name="download" /> Download PDF
                </b-button>
              </div>
              <div v-if="templatePreview" class="p-3 border rounded bg-white" v-html="templatePreview"></div>
            </div>
          </b-card>

          <b-modal
            id="contract-template-modal"
            :title="templateForm.id ? 'Edit template' : 'New template'"
            size="lg"
            ok-title="Save"
            @ok.prevent="saveTemplate"
            no-close-on-backdrop
          >
            <b-form-group label="Name *">
              <b-form-input v-model="templateForm.name" placeholder="e.g. Standard service contract" />
            </b-form-group>
            <b-form-group label="Content (HTML supported, use merge fields like {customer_name})">
              <b-form-textarea v-model="templateForm.content" rows="12" placeholder="<h2>{subject}</h2>&#10;<p>This contract is between {customer_name} and us...</p>" />
            </b-form-group>
            <p class="small text-muted mb-0">Tip: merge fields are case-sensitive and wrapped in curly braces. Click <em>View available merge fields</em> on the tab to see all options.</p>
          </b-modal>
        </b-tab>
      </b-tabs>

      <b-modal
        id="pdf-preview-modal"
        :title="pdfPreviewTitle"
        size="xl"
        hide-footer
        @hidden="closePdfPreview"
        body-class="p-0"
      >
        <iframe
          v-if="pdfPreviewUrl"
          :src="pdfPreviewUrl"
          style="width: 100%; height: 75vh; border: 0;"
          title="PDF preview"
        ></iframe>
        <div v-else class="text-center p-5 text-muted">Loading preview...</div>
      </b-modal>
    </template>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import DOMPurify from "dompurify";

export default {
  metaInfo: { title: "View Contract" },
  data() {
    return {
      contractId: this.$route.params.id,
      contract: null,
      isLoading: true,
      tabIndex: 0,
      newComment: "",
      newNote: "",
      renewalForm: { renewal_date: "", new_end_date: "", notes: "" },
      taskForm: { title: "", due_date: "" },
      taskFields: [{ key: "title", label: "Title" }, { key: "due_date", label: "Due" }, { key: "status", label: "Status" }, { key: "actions", label: "" }],
      mergeFields: [],
      templates: [],
      selectedTemplateId: null,
      templatePreview: "",
      templateForm: { id: null, name: "", content: "" },
      templateFields: [
        { key: "name", label: "Name" },
        { key: "actions", label: "" },
      ],
      pdfPreviewUrl: "",
      pdfPreviewTitle: "PDF preview",
      pdfPreviewLoading: false,
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions"]),
    canEdit() {
      return this.currentUserPermissions && this.currentUserPermissions.includes("contracts");
    },
    templateOptions() {
      const opts = [{ value: null, text: "Select a template" }];
      (this.templates || []).forEach(t => opts.push({ value: t.id, text: t.name }));
      return opts;
    },
    sanitizedDescription() {
      return this.contract && this.contract.description ? DOMPurify.sanitize(this.contract.description) : "";
    },
  },
  mounted() {
    this.fetchContract();
    axios.get("contracts/merge-fields").then(r => { this.mergeFields = r.data.merge_fields || []; }).catch(() => {});
    this.fetchTemplates();
  },
  methods: {
    makeToast(variant, msg, title) {
      this.$bvToast.toast(msg, { title: title || this.$t("Notice") || "Notice", variant: variant, solid: true });
    },
    formatDate(val) {
      if (!val) return "";
      try {
        const d = new Date(val);
        return isNaN(d.getTime()) ? val : d.toLocaleString();
      } catch (e) { return val; }
    },
    fetchContract() {
      this.isLoading = true;
      axios.get("contracts/" + this.contractId).then(r => {
        this.contract = r.data.contract;
        this.isLoading = false;
      }).catch(() => { this.isLoading = false; });
    },
    downloadPdf() {
      axios.get(`contracts/${this.contractId}/pdf`, { responseType: "blob" }).then(r => {
        const url = window.URL.createObjectURL(r.data);
        const a = document.createElement("a");
        a.href = url;
        a.download = `Contract_${this.contract.contract_number || this.contractId}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
      }).catch(() => this.makeToast("danger", "Download failed", "Failed"));
    },
    previewContractPdf() {
      this.pdfPreviewLoading = true;
      this.pdfPreviewTitle = `Contract ${this.contract.contract_number || ""} — preview`;
      axios.get(`contracts/${this.contractId}/pdf`, { responseType: "blob", params: { preview: 1 } }).then(r => {
        this.openPdfBlob(r.data);
      }).catch(() => this.makeToast("danger", "Preview failed", "Failed"))
        .finally(() => { this.pdfPreviewLoading = false; });
    },
    previewTemplatePdf() {
      if (!this.selectedTemplateId) return;
      const template = (this.templates || []).find(t => t.id === this.selectedTemplateId);
      this.pdfPreviewLoading = true;
      this.pdfPreviewTitle = template ? `Template: ${template.name} — preview` : "Template preview";
      axios.get(`contracts/${this.contractId}/templates/${this.selectedTemplateId}/pdf`, { responseType: "blob", params: { preview: 1 } }).then(r => {
        this.openPdfBlob(r.data);
      }).catch(() => this.makeToast("danger", "Preview failed", "Failed"))
        .finally(() => { this.pdfPreviewLoading = false; });
    },
    downloadTemplatePdf() {
      if (!this.selectedTemplateId) return;
      const template = (this.templates || []).find(t => t.id === this.selectedTemplateId);
      const safeName = template ? (template.name || "template").replace(/[^A-Za-z0-9_\-]+/g, "_") : "template";
      axios.get(`contracts/${this.contractId}/templates/${this.selectedTemplateId}/pdf`, { responseType: "blob" }).then(r => {
        const url = window.URL.createObjectURL(r.data);
        const a = document.createElement("a");
        a.href = url;
        a.download = `Template_${safeName}_${this.contract.contract_number || this.contractId}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
      }).catch(() => this.makeToast("danger", "Download failed", "Failed"));
    },
    openPdfBlob(blob) {
      if (this.pdfPreviewUrl) window.URL.revokeObjectURL(this.pdfPreviewUrl);
      const pdfBlob = blob.type === "application/pdf" ? blob : new Blob([blob], { type: "application/pdf" });
      this.pdfPreviewUrl = window.URL.createObjectURL(pdfBlob);
      this.$bvModal.show("pdf-preview-modal");
    },
    closePdfPreview() {
      if (this.pdfPreviewUrl) {
        window.URL.revokeObjectURL(this.pdfPreviewUrl);
        this.pdfPreviewUrl = "";
      }
    },
    loadMergeFields() {
      if (this.mergeFields.length) return;
      axios.get("contracts/merge-fields").then(r => { this.mergeFields = r.data.merge_fields || []; });
    },
    loadTemplatePreview() {
      if (!this.selectedTemplateId) { this.templatePreview = ""; return; }
      axios.get(`contracts/${this.contractId}/templates/${this.selectedTemplateId}/render`).then(r => {
        this.templatePreview = r.data.content || "";
      }).catch(() => { this.templatePreview = "Error loading preview."; });
    },
    fetchTemplates() {
      return axios.get("contracts-templates").then(r => { this.templates = r.data.templates || []; }).catch(() => {});
    },
    openTemplateForm() {
      this.templateForm = { id: null, name: "", content: "" };
      this.$bvModal.show("contract-template-modal");
    },
    editTemplate(t) {
      this.templateForm = { id: t.id, name: t.name || "", content: t.content || "" };
      this.$bvModal.show("contract-template-modal");
    },
    saveTemplate() {
      const name = (this.templateForm.name || "").trim();
      if (!name) {
        this.makeToast("danger", "Template name is required.", "Validation");
        return;
      }
      const payload = { name, content: this.templateForm.content || "" };
      const req = this.templateForm.id
        ? axios.put(`contracts-templates/${this.templateForm.id}`, payload)
        : axios.post("contracts-templates", payload);
      req.then(() => {
        this.makeToast("success", "Template saved.", "Success");
        this.$bvModal.hide("contract-template-modal");
        this.fetchTemplates().then(() => {
          if (this.selectedTemplateId) this.loadTemplatePreview();
        });
      }).catch(err => {
        const msg = (err.response && err.response.data && (err.response.data.message || (err.response.data.errors && Object.values(err.response.data.errors).flat()[0]))) || "Failed to save template.";
        this.makeToast("danger", msg, "Failed");
      });
    },
    deleteTemplate(id) {
      if (!window.confirm("Delete this template?")) return;
      axios.delete(`contracts-templates/${id}`).then(() => {
        this.makeToast("success", "Template deleted.", "Success");
        if (this.selectedTemplateId === id) {
          this.selectedTemplateId = null;
          this.templatePreview = "";
        }
        this.fetchTemplates();
      }).catch(() => this.makeToast("danger", "Delete failed.", "Failed"));
    },
    uploadAttachment() {
      const file = this.$refs.fileInput && this.$refs.fileInput.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append("file", file);
      axios.post(`contracts/${this.contractId}/attachments`, fd, { headers: { "Content-Type": "multipart/form-data" } }).then(() => {
        this.makeToast("success", "Uploaded", "Success");
        this.fetchContract();
      }).catch(() => this.makeToast("danger", "Upload failed", "Failed"));
    },
    downloadAttachment(a) {
      axios.get(`contracts/${this.contractId}/attachments/${a.id}/download`, { responseType: "blob" }).then(r => {
        const url = window.URL.createObjectURL(r.data);
        const link = document.createElement("a");
        link.href = url;
        link.download = a.file_name;
        link.click();
        window.URL.revokeObjectURL(url);
      }).catch(() => this.makeToast("danger", "Download failed", "Failed"));
    },
    deleteAttachment(id) {
      if (!confirm("Delete this attachment?")) return;
      axios.delete(`contracts/${this.contractId}/attachments/${id}`).then(() => { this.fetchContract(); });
    },
    addComment() {
      if (!this.newComment.trim()) return;
      axios.post(`contracts/${this.contractId}/comments`, { body: this.newComment }).then(() => {
        this.newComment = "";
        this.fetchContract();
      });
    },
    deleteComment(id) {
      axios.delete(`contracts/${this.contractId}/comments/${id}`).then(() => this.fetchContract());
    },
    addRenewal() {
      if (!this.renewalForm.renewal_date || !this.renewalForm.new_end_date) return;
      axios.post(`contracts/${this.contractId}/renewals`, this.renewalForm).then(() => {
        this.renewalForm = { renewal_date: "", new_end_date: "", notes: "" };
        this.fetchContract();
      });
    },
    addTask() {
      if (!this.taskForm.title.trim()) return;
      axios.post(`contracts/${this.contractId}/tasks`, this.taskForm).then(() => {
        this.taskForm = { title: "", due_date: "" };
        this.fetchContract();
      });
    },
    deleteTask(id) {
      axios.delete(`contracts/${this.contractId}/tasks/${id}`).then(() => this.fetchContract());
    },
    addNote() {
      if (!this.newNote.trim()) return;
      axios.post(`contracts/${this.contractId}/notes`, { content: this.newNote }).then(() => {
        this.newNote = "";
        this.fetchContract();
      });
    },
    deleteNote(id) {
      axios.delete(`contracts/${this.contractId}/notes/${id}`).then(() => this.fetchContract());
    },
  },
};
</script>

<style scoped>
.signature-info { font-size: 0.9rem; }
.badge.status-active { background: #10b981; }
.badge.status-expired { background: #ef4444; }
.badge.status-draft { background: #6b7280; }
.badge.status-cancelled { background: #f59e0b; }
</style>
