<template>
  <div class="main-content">
    <breadcumb
      :page="$t('Service_Job_Details') || 'Service Job Details'"
      :folder="$t('Service_Maintenance')"
    />

    <div class="mb-3">
      <router-link to="/app/service/jobs" class="sjd-back-btn">
        <lucide-icon name="arrow-left" />
        <span>{{ $t('Back_to_Service_Jobs') || 'Back to Service Jobs' }}</span>
      </router-link>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else-if="job" class="service-job-details-container">
      <!-- Hero Header -->
      <div class="job-header">
        <div class="header-content">
          <div class="header-icon">
            <lucide-icon name="wrench" :size="28" />
          </div>
          <div class="header-text">
            <div class="header-eyebrow">
              <span class="header-ref-pill">
                <lucide-icon name="hash" />
                {{ job.Ref }}
              </span>
              <span class="header-status-pill" :class="statusClass(job.status)">
                <span class="hsp-dot"></span>
                {{ statusLabel(job.status) }}
              </span>
            </div>
            <h1 class="job-title">{{ job.client_name || ($t('Service_Job_Details') || 'Service Job Details') }}</h1>
            <div class="header-meta">
              <span v-if="job.service_item" class="hm-pill">
                <lucide-icon name="package" />
                <span>{{ job.service_item }}</span>
              </span>
              <span v-if="job.technician_name" class="hm-pill">
                <lucide-icon name="user" />
                <span>{{ job.technician_name }}</span>
              </span>
              <span v-if="job.scheduled_date" class="hm-pill">
                <lucide-icon name="calendar" />
                <span>{{ formatDateTime(job.scheduled_date) }}</span>
              </span>
            </div>
          </div>
        </div>
        <div class="header-actions">
          <button
            class="action-btn pdf-btn"
            @click="Service_Job_PDF(job.id)"
            :title="$t('Download_PDF') || 'Download PDF'"
            :disabled="isPdfLoading"
          >
            <lucide-icon name="file-down" :size="20" />
          </button>
          <button
            class="action-btn print-btn"
            @click="printJob"
            :title="$t('Print') || 'Print'"
          >
            <lucide-icon name="printer" :size="20" />
          </button>
          <router-link
            :to="`/app/service/jobs/edit/${job.id}`"
            class="action-btn edit-btn"
            :title="$t('Edit') || 'Edit'"
          >
            <lucide-icon name="pencil" :size="20" />
          </router-link>
          <button
            class="action-btn close-btn"
            @click="$router.back()"
            :title="$t('Close') || 'Close'"
          >
            <lucide-icon name="x" :size="20" />
          </button>
        </div>
      </div>

      <!-- Status Journey -->
      <div v-if="!isCancelled" class="status-journey">
        <div
          v-for="(stage, idx) in statusJourney"
          :key="stage.key"
          class="sj-step"
          :class="{
            'sj-step--done': stage.index < currentStageIndex,
            'sj-step--current': stage.index === currentStageIndex,
            'sj-step--pending': stage.index > currentStageIndex
          }"
        >
          <div class="sj-step__node">
            <lucide-icon :name="stage.index < currentStageIndex ? 'check' : stage.icon" />
          </div>
          <div class="sj-step__label">{{ $t(stage.label) || stage.label }}</div>
          <div v-if="idx < statusJourney.length - 1" class="sj-step__bar"></div>
        </div>
      </div>
      <div v-else class="status-cancelled-banner">
        <lucide-icon name="x-circle" />
        <span>{{ $t(job.status) || statusLabel(job.status) }}</span>
      </div>

      <!-- Content Section -->
      <div class="job-content">
        <div class="job-grid">
          <!-- Reference Card -->
          <div class="info-card">
            <div class="card-header">
              <div class="card-icon reference-icon">
                <lucide-icon name="tag" />
              </div>
              <h3 class="card-title">{{ $t('Reference') || 'Reference' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ job.Ref }}</div>
            </div>
          </div>

          <!-- Customer Card -->
          <div class="info-card">
            <div class="card-header">
              <div class="card-icon customer-icon">
                <lucide-icon name="user" />
              </div>
              <h3 class="card-title">{{ $t('Customer') || 'Customer' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ job.client_name || '-' }}</div>
            </div>
          </div>

          <!-- Technician Card -->
          <div class="info-card">
            <div class="card-header">
              <div class="card-icon technician-icon">
                <lucide-icon name="wrench" />
              </div>
              <h3 class="card-title">{{ $t('Technician') || 'Technician' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ job.technician_name || '-' }}</div>
            </div>
          </div>

          <!-- Service Item Card -->
          <div class="info-card">
            <div class="card-header">
              <div class="card-icon service-icon">
                <lucide-icon name="package" />
              </div>
              <h3 class="card-title">{{ $t('Service_Item') || 'Service Item' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ job.service_item || '-' }}</div>
            </div>
          </div>

          <!-- Job Type Card -->
          <div class="info-card" v-if="job.job_type">
            <div class="card-header">
              <div class="card-icon job-type-icon">
                <lucide-icon name="file-text" />
              </div>
              <h3 class="card-title">{{ $t('Job_Type') || 'Job Type' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ job.job_type }}</div>
            </div>
          </div>

          <!-- Status Card -->
          <div class="info-card">
            <div class="card-header">
              <div class="card-icon status-icon">
                <lucide-icon name="check-circle" />
              </div>
              <h3 class="card-title">{{ $t('Status') || 'Status' }}</h3>
            </div>
            <div class="card-body">
              <span :class="['status-badge-modern', statusClass(job.status)]">
                <span class="status-dot"></span>
                {{ statusLabel(job.status) }}
              </span>
            </div>
          </div>

          <!-- Scheduled Date Card -->
          <div class="info-card" v-if="job.scheduled_date">
            <div class="card-header">
              <div class="card-icon datetime-icon">
                <lucide-icon name="calendar-days" />
              </div>
              <h3 class="card-title">{{ $t('Scheduled_Date') || 'Scheduled Date' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ formatDateTime(job.scheduled_date) }}</div>
            </div>
          </div>

          <!-- Started At Card -->
          <div class="info-card" v-if="job.started_at">
            <div class="card-header">
              <div class="card-icon datetime-icon">
                <lucide-icon name="clock" />
              </div>
              <h3 class="card-title">{{ $t('Started_At') || 'Started At' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ formatDateTime(job.started_at) }}</div>
            </div>
          </div>

          <!-- Completed At Card -->
          <div class="info-card" v-if="job.completed_at">
            <div class="card-header">
              <div class="card-icon datetime-icon">
                <lucide-icon name="check" />
              </div>
              <h3 class="card-title">{{ $t('Completed_At') || 'Completed At' }}</h3>
            </div>
            <div class="card-body">
              <div class="info-value">{{ formatDateTime(job.completed_at) }}</div>
            </div>
          </div>
        </div>

        <!-- Notes Section -->
        <div v-if="job.notes" class="notes-section">
          <h3 class="notes-title">
            <lucide-icon name="file-text" />
            {{ $t('Notes') || 'Notes' }}
          </h3>
          <div class="notes-content">
            <p>{{ job.notes }}</p>
          </div>
        </div>

        <!-- Checklist Section -->
        <div v-if="checklist && checklist.length > 0" class="checklist-section">
          <h3 class="checklist-title">
            <lucide-icon name="check" />
            {{ $t('Checklist') || 'Checklist' }}
          </h3>
          <div class="checklist-grid">
            <div
              v-for="item in checklist"
              :key="item.id"
              :class="['checklist-item', { 'completed': item.is_completed }]"
            >
              <div class="checklist-item-header">
                <div class="checklist-checkbox">
                  <lucide-icon
                    :class="item.is_completed ? 'text-success' : 'text-muted'"
                    :name="item.is_completed ? 'check-circle' : 'circle'"
                  />
                </div>
                <div class="checklist-item-name">{{ item.item_name }}</div>
              </div>
              <div v-if="item.category_name" class="checklist-category">
                <lucide-icon name="folder" />
                {{ item.category_name }}
              </div>
            </div>
          </div>
        </div>

        <!-- Empty Checklist Message -->
        <div v-else class="empty-checklist">
          <lucide-icon name="info" />
          <p>{{ $t('No_checklist_items_defined') || 'No checklist items defined for this job.' }}</p>
        </div>

        <!-- ============== Finance Stat Cards ============== -->
        <div class="finance-stats">
          <div class="fstat fstat--total">
            <div class="fstat__icon"><lucide-icon name="receipt" /></div>
            <div class="fstat__body">
              <div class="fstat__label">{{ $t('Total') }}</div>
              <div class="fstat__value">{{ currencySymbol }}{{ formatNumber(job.total_amount) }}</div>
            </div>
          </div>
          <div class="fstat fstat--paid">
            <div class="fstat__icon"><lucide-icon name="check-circle" /></div>
            <div class="fstat__body">
              <div class="fstat__label">{{ $t('Paid') }}</div>
              <div class="fstat__value">{{ currencySymbol }}{{ formatNumber(job.paid_amount) }}</div>
              <div class="fstat__progress">
                <div class="fstat__progress-bar" :style="{ width: paidPercent + '%' }"></div>
              </div>
              <div class="fstat__percent">{{ paidPercent }}% {{ $t('paid') || 'paid' }}</div>
            </div>
          </div>
          <div class="fstat" :class="job.balance_due > 0 ? 'fstat--due' : 'fstat--settled'">
            <div class="fstat__icon">
              <lucide-icon :name="job.balance_due > 0 ? 'alert-circle' : 'check'" />
            </div>
            <div class="fstat__body">
              <div class="fstat__label">{{ $t('Balance_Due') || 'Balance Due' }}</div>
              <div class="fstat__value">{{ currencySymbol }}{{ formatNumber(job.balance_due) }}</div>
              <span class="fstat__chip" :class="paymentChipClass(job.payment_status)">
                {{ $t(job.payment_status || 'unpaid') }}
              </span>
            </div>
          </div>
          <div v-if="job.warranty_expires_at" class="fstat fstat--warranty">
            <div class="fstat__icon"><lucide-icon name="shield-check" /></div>
            <div class="fstat__body">
              <div class="fstat__label">{{ $t('Warranty') || 'Warranty' }}</div>
              <div class="fstat__value fstat__value--sm">{{ formatDate(job.warranty_expires_at) }}</div>
              <span v-if="isWarrantyActive(job.warranty_expires_at)" class="fstat__chip fstat__chip--ok">{{ $t('Active') || 'Active' }}</span>
              <span v-else class="fstat__chip fstat__chip--expired">{{ $t('Expired') || 'Expired' }}</span>
            </div>
          </div>
          <div v-if="job.delivered_at" class="fstat fstat--delivered">
            <div class="fstat__icon"><lucide-icon name="truck" /></div>
            <div class="fstat__body">
              <div class="fstat__label">{{ $t('Delivered_On') || 'Delivered' }}</div>
              <div class="fstat__value fstat__value--sm">{{ formatDateTime(job.delivered_at) }}</div>
            </div>
          </div>
        </div>

        <!-- ============== Device Information ============== -->
        <div class="section-card" v-if="job.device_brand || job.device_model || job.device_serial || job.device_imei">
          <h3 class="section-title">
            <lucide-icon name="smartphone" />
            {{ $t('Device_Information') || 'Device Information' }}
          </h3>
          <div class="device-grid">
            <div v-if="job.device_brand" class="device-cell">
              <div class="dev-label">{{ $t('Brand') || 'Brand' }}</div>
              <div class="dev-value">{{ job.device_brand }}</div>
            </div>
            <div v-if="job.device_model" class="device-cell">
              <div class="dev-label">{{ $t('Model') || 'Model' }}</div>
              <div class="dev-value">{{ job.device_model }}</div>
            </div>
            <div v-if="job.device_color" class="device-cell">
              <div class="dev-label">{{ $t('Color') || 'Color' }}</div>
              <div class="dev-value">{{ job.device_color }}</div>
            </div>
            <div v-if="job.device_serial" class="device-cell">
              <div class="dev-label">{{ $t('Serial_Number') || 'Serial' }}</div>
              <div class="dev-value">{{ job.device_serial }}</div>
            </div>
            <div v-if="job.device_imei" class="device-cell">
              <div class="dev-label">{{ $t('IMEI') || 'IMEI' }}</div>
              <div class="dev-value">{{ job.device_imei }}</div>
            </div>
            <div v-if="job.device_password" class="device-cell">
              <div class="dev-label">{{ $t('Unlock_Code') || 'Unlock' }}</div>
              <div class="dev-value">{{ job.device_password }}</div>
            </div>
            <div v-if="job.accessories && job.accessories.length" class="device-cell device-cell-wide">
              <div class="dev-label">{{ $t('Accessories_Received') || 'Accessories' }}</div>
              <div class="dev-value">
                <span v-for="a in job.accessories" :key="a" class="accessory-pill">{{ a }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ============== Intake / Diagnostic ============== -->
        <div class="section-card" v-if="job.condition_on_arrival || job.reported_issue || job.diagnosis">
          <h3 class="section-title">
            <lucide-icon name="clipboard-list" />
            {{ $t('Intake_Diagnostic') || 'Intake & Diagnostic' }}
          </h3>
          <div class="intake-grid">
            <div v-if="job.condition_on_arrival" class="intake-block">
              <h4>{{ $t('Condition_On_Arrival') || 'Condition on Arrival' }}</h4>
              <p>{{ job.condition_on_arrival }}</p>
            </div>
            <div v-if="job.reported_issue" class="intake-block">
              <h4>{{ $t('Reported_Issue') || 'Reported Issue' }}</h4>
              <p>{{ job.reported_issue }}</p>
            </div>
            <div v-if="job.diagnosis" class="intake-block">
              <h4>{{ $t('Diagnosis') || 'Diagnosis' }}</h4>
              <p>{{ job.diagnosis }}</p>
            </div>
          </div>
        </div>

        <!-- ============== Quote ============== -->
        <div class="section-card" v-if="job.quote_amount > 0 || job.quote_approved_at || job.quote_valid_until">
          <h3 class="section-title">
            <lucide-icon name="file-pen" />
            {{ $t('Quote') || 'Quote' }}
          </h3>
          <div class="quote-row">
            <div v-if="job.quote_amount > 0" class="quote-cell">
              <div class="dev-label">{{ $t('Quote_Amount') || 'Quote Amount' }}</div>
              <div class="dev-value">{{ currencySymbol }}{{ formatNumber(job.quote_amount) }}</div>
            </div>
            <div v-if="job.quote_valid_until" class="quote-cell">
              <div class="dev-label">{{ $t('Valid_Until') || 'Valid Until' }}</div>
              <div class="dev-value">{{ formatDate(job.quote_valid_until) }}</div>
            </div>
            <div v-if="job.quote_approved_at" class="quote-cell">
              <div class="dev-label">{{ $t('Approved') || 'Approved' }}</div>
              <div class="dev-value text-success">
                <lucide-icon name="check" />
                {{ formatDateTime(job.quote_approved_at) }}
                <span v-if="job.quote_approved_by"> · {{ job.quote_approved_by }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ============== Line Items ============== -->
        <div class="section-card" v-if="items && items.length">
          <h3 class="section-title">
            <lucide-icon name="receipt-text" />
            <span>{{ $t('Line_Items') || 'Parts & Labor' }}</span>
            <span class="section-count">{{ items.length }}</span>
          </h3>
          <div class="modern-table-wrap">
            <table class="modern-table">
              <thead>
                <tr>
                  <th>{{ $t('Type') || 'Type' }}</th>
                  <th>{{ $t('Description') || 'Description' }}</th>
                  <th class="text-right">{{ $t('Qty') }}</th>
                  <th class="text-right">{{ $t('Unit_Price') }}</th>
                  <th class="text-right">{{ $t('Total') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in items" :key="row.id">
                  <td>
                    <span class="type-chip" :class="'type-chip--' + row.type">
                      <lucide-icon :name="row.type === 'part' ? 'box' : 'wrench'" />
                      {{ $t(row.type) || row.type }}
                    </span>
                  </td>
                  <td class="td-strong">{{ row.description }}</td>
                  <td class="text-right">{{ row.quantity }}</td>
                  <td class="text-right">{{ currencySymbol }}{{ formatNumber(row.unit_price) }}</td>
                  <td class="text-right td-amount">{{ currencySymbol }}{{ formatNumber(row.total) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr v-if="job.diagnostic_fee > 0" class="tfoot-row">
                  <td colspan="4" class="text-right">{{ $t('Diagnostic_Fee') || 'Diagnostic Fee' }}</td>
                  <td class="text-right">{{ currencySymbol }}{{ formatNumber(job.diagnostic_fee) }}</td>
                </tr>
                <tr class="tfoot-row tfoot-row--grand">
                  <td colspan="4" class="text-right">{{ $t('Grand_Total') || 'Grand Total' }}</td>
                  <td class="text-right">{{ currencySymbol }}{{ formatNumber(job.total_amount) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- ============== Photos Gallery ============== -->
        <div class="section-card" v-if="photos && photos.length">
          <h3 class="section-title">
            <lucide-icon name="camera" />
            <span>{{ $t('Photos') || 'Photos' }}</span>
            <span class="section-count">{{ photos.length }}</span>
          </h3>
          <div class="photos-gallery">
            <div v-for="ph in photos" :key="ph.id" class="photo-cell" @click="previewPhoto = ph">
              <img :src="ph.url" :alt="ph.original_name" />
              <div class="photo-overlay">
                <lucide-icon name="zoom-in" />
              </div>
              <span class="photo-stage-pill">{{ $t(ph.stage) || ph.stage }}</span>
              <small v-if="ph.caption" class="photo-caption">{{ ph.caption }}</small>
            </div>
          </div>
          <b-modal v-if="previewPhoto" :visible="!!previewPhoto" hide-footer @hidden="previewPhoto = null" size="lg">
            <img :src="previewPhoto.url" class="img-fluid" />
          </b-modal>
        </div>

        <!-- ============== Payments Timeline ============== -->
        <div class="section-card" v-if="payments && payments.length">
          <h3 class="section-title">
            <lucide-icon name="banknote" />
            <span>{{ $t('Payments') || 'Payments' }}</span>
            <span class="section-count">{{ payments.length }}</span>
          </h3>
          <div class="modern-table-wrap">
            <table class="modern-table">
              <thead>
                <tr>
                  <th>{{ $t('Reference') }}</th>
                  <th>{{ $t('Date') }}</th>
                  <th>{{ $t('Kind') || 'Kind' }}</th>
                  <th>{{ $t('Method') || 'Method' }}</th>
                  <th class="text-right">{{ $t('Amount') }}</th>
                  <th>{{ $t('Notes') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in payments" :key="p.id">
                  <td><span class="ref-pill">{{ p.Ref }}</span></td>
                  <td>
                    <span class="td-date">
                      <lucide-icon name="calendar" />
                      {{ p.date }}
                    </span>
                  </td>
                  <td>
                    <span class="kind-chip" :class="'kind-chip--' + (p.payment_kind || 'payment')">
                      <lucide-icon :name="kindIcon(p.payment_kind)" />
                      {{ $t(p.payment_kind) || p.payment_kind }}
                    </span>
                  </td>
                  <td>
                    <span v-if="p.payment_method" class="method-pill">{{ p.payment_method }}</span>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td class="text-right td-amount" :class="p.payment_kind === 'refund' ? 'text-danger' : ''">
                    {{ p.payment_kind === 'refund' ? '-' : '' }}{{ currencySymbol }}{{ formatNumber(p.montant) }}
                  </td>
                  <td>
                    <span v-if="p.notes" class="td-notes" :title="p.notes">{{ p.notes }}</span>
                    <span v-else class="text-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else class="error-state">
      <lucide-icon name="x" />
      <p>{{ $t('Job_not_found') || 'Service job not found.' }}</p>
      <router-link to="/app/service/jobs" class="btn btn-primary">
        {{ $t('Back_to_Jobs') || 'Back to Jobs' }}
      </router-link>
    </div>

    <!-- PDF Loading Overlay -->
    <div v-if="isPdfLoading" class="pdf-loading-overlay">
      <div class="loading-content">
        <div class="spinner spinner-primary"></div>
        <p>{{ $t('Generating_PDF') || 'Generating PDF...' }}</p>
      </div>
    </div>

    <!-- Print Template -->
    <div id="print_Service_Job" style="display: none;">
      <div style="padding: 20px; font-family: Arial, sans-serif;">
        <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1a56db; padding-bottom: 15px;">
          <h1 style="color: #1a56db; margin: 0 0 10px 0;">SERVICE JOB</h1>
          <h2 style="color: #4b5563; margin: 0;">{{ job ? job.Ref : '' }}</h2>
        </div>

        <table style="width: 100%; margin-bottom: 20px;" cellpadding="5" cellspacing="0">
          <tr>
            <td style="width: 50%; vertical-align: top;">
              <h3 style="color: #1a56db; margin: 0 0 10px 0; font-size: 14px;">CUSTOMER</h3>
              <p style="margin: 5px 0;"><strong>Name:</strong> {{ job ? job.client_name : '-' }}</p>
              <p style="margin: 5px 0;" v-if="job && job.client_phone"><strong>Phone:</strong> {{ job.client_phone }}</p>
              <p style="margin: 5px 0;" v-if="job && job.client_email"><strong>Email:</strong> {{ job.client_email }}</p>
            </td>
            <td style="width: 50%; vertical-align: top;">
              <h3 style="color: #1a56db; margin: 0 0 10px 0; font-size: 14px;">JOB INFORMATION</h3>
              <p style="margin: 5px 0;"><strong>Service Item:</strong> {{ job ? job.service_item : '-' }}</p>
              <p style="margin: 5px 0;" v-if="job && job.job_type"><strong>Job Type:</strong> {{ job.job_type }}</p>
              <p style="margin: 5px 0;"><strong>Technician:</strong> {{ job ? job.technician_name : '-' }}</p>
              <p style="margin: 5px 0;" v-if="job && job.scheduled_date"><strong>Scheduled Date:</strong> {{ formatDateTime(job.scheduled_date) }}</p>
              <p style="margin: 5px 0;"><strong>Status:</strong> {{ job ? statusLabel(job.status) : '-' }}</p>
            </td>
          </tr>
        </table>

        <div v-if="job && job.notes" style="margin-bottom: 20px; padding: 10px; background: #f9fafb; border-left: 3px solid #1a56db;">
          <h3 style="color: #1a56db; margin: 0 0 10px 0; font-size: 14px;">NOTES</h3>
          <p style="margin: 0; white-space: pre-line;">{{ job.notes }}</p>
        </div>

        <div v-if="checklist && checklist.length > 0" style="margin-bottom: 20px;">
          <h3 style="color: #1a56db; margin: 0 0 10px 0; font-size: 14px;">CHECKLIST</h3>
          <table style="width: 100%; border-collapse: collapse;" cellpadding="5" cellspacing="0" border="1">
            <thead>
              <tr style="background: #1a56db; color: white;">
                <th style="padding: 8px; text-align: left;">Status</th>
                <th style="padding: 8px; text-align: left;">Category</th>
                <th style="padding: 8px; text-align: left;">Item</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in checklist" :key="item.id">
                <td style="padding: 8px;">{{ item.is_completed ? '✓ Completed' : '○ Pending' }}</td>
                <td style="padding: 8px;">{{ item.category_name || '-' }}</td>
                <td style="padding: 8px;">{{ item.item_name }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style="margin-top: 30px; text-align: center; padding-top: 15px; border-top: 2px solid #e5e7eb;">
          <p style="color: #1a56db; font-weight: bold; margin: 0;">Thank you for your business!</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  name: 'ServiceJobDetails',
  metaInfo: {
    title: 'Service Job Details'
  },
  data() {
    return {
      isLoading: true,
      isPdfLoading: false,
      job: null,
      checklist: [],
      items: [],
      payments: [],
      photos: [],
      previewPhoto: null,
      currencySymbol: '$'
    };
  },
  computed: {
    jobId() {
      return this.$route.params.id ? Number(this.$route.params.id) : null;
    },
    paidPercent() {
      if (!this.job) return 0;
      const total = Number(this.job.total_amount || 0);
      const paid = Number(this.job.paid_amount || 0);
      if (total <= 0) return paid > 0 ? 100 : 0;
      return Math.min(100, Math.max(0, Math.round((paid / total) * 100)));
    },
    isCancelled() {
      if (!this.job) return false;
      return this.job.status === 'cancelled' || this.job.status === 'declined';
    },
    statusJourney() {
      return [
        { key: 'intake', label: 'Intake', icon: 'log-in', index: 1 },
        { key: 'diagnostic', label: 'Diagnostic', icon: 'search', index: 2 },
        { key: 'quoted', label: 'Quote', icon: 'file-pen', index: 3 },
        { key: 'approved', label: 'Approved', icon: 'thumbs-up', index: 4 },
        { key: 'in_progress', label: 'In_Progress', icon: 'wrench', index: 5 },
        { key: 'ready', label: 'Ready', icon: 'package-check', index: 6 },
        { key: 'delivered', label: 'Delivered', icon: 'truck', index: 7 }
      ];
    },
    currentStageIndex() {
      if (!this.job) return 0;
      const map = {
        pending: 1, intake: 1,
        diagnostic: 2,
        quoted: 3,
        approved: 4,
        in_progress: 5,
        ready: 6,
        delivered: 7, completed: 7
      };
      return map[this.job.status] || 1;
    }
  },
  async mounted() {
    if (this.jobId) {
      await this.loadJobDetails();
    } else {
      this.isLoading = false;
    }
  },
  methods: {
    async loadJobDetails() {
      this.isLoading = true;
      try {
        const { data } = await axios.get(`service_jobs/${this.jobId}`);
        this.job = data.job || null;
        this.checklist = data.checklist || [];
        this.items = data.items || [];
        this.payments = data.payments || [];
        this.photos = data.photos || [];
      } catch (error) {
        console.error('Error loading job details:', error);
        this.makeToast('danger', this.$t('InvalidData') || 'Failed to load job details', this.$t('Failed') || 'Failed');
      } finally {
        this.isLoading = false;
      }
    },
    statusLabel(status) {
      const statusMap = {
        pending: this.$t('Pending') || 'Pending',
        intake: this.$t('Intake') || 'Intake',
        diagnostic: this.$t('Diagnostic') || 'Diagnostic',
        quoted: this.$t('Quoted') || 'Quoted',
        approved: this.$t('Approved') || 'Approved',
        in_progress: this.$t('In_Progress') || 'In Progress',
        ready: this.$t('Ready') || 'Ready for Pickup',
        delivered: this.$t('Delivered') || 'Delivered',
        declined: this.$t('Declined') || 'Declined',
        completed: this.$t('complete') || 'Completed',
        cancelled: this.$t('Cancelled') || 'Cancelled'
      };
      return statusMap[status] || status;
    },
    statusClass(status) {
      if (status === 'pending' || status === 'intake' || status === 'diagnostic' || status === 'quoted') return 'pending';
      if (status === 'in_progress' || status === 'approved') return 'in-progress';
      if (status === 'completed' || status === 'delivered' || status === 'ready') return 'completed';
      if (status === 'cancelled' || status === 'declined') return 'cancelled';
      return '';
    },
    paymentBadgeClass(s) {
      if (s === 'paid') return 'badge-outline-success';
      if (s === 'partial') return 'badge-outline-warning';
      return 'badge-outline-danger';
    },
    paymentChipClass(s) {
      if (s === 'paid') return 'fstat__chip--ok';
      if (s === 'partial') return 'fstat__chip--warn';
      return 'fstat__chip--danger';
    },
    kindIcon(kind) {
      if (kind === 'deposit') return 'piggy-bank';
      if (kind === 'refund') return 'rotate-ccw';
      return 'banknote';
    },
    formatNumber(n) {
      const v = Number(n) || 0;
      return v.toFixed(2);
    },
    typeBadgeVariant(t) {
      if (t === 'part') return 'info';
      if (t === 'labor') return 'primary';
      return 'secondary';
    },
    paymentKindVariant(k) {
      if (k === 'deposit') return 'warning';
      if (k === 'refund') return 'danger';
      return 'success';
    },
    isWarrantyActive(d) {
      if (!d) return false;
      try { return new Date(d) >= new Date(); } catch (e) { return false; }
    },
    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString();
    },
    formatDateTime(dateTime) {
      if (!dateTime) return '-';
      const date = new Date(dateTime);
      return date.toLocaleString();
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    //----------------------------------- Service Job PDF  -------------------------\\
    Service_Job_PDF(id) {
      // Show full page loading overlay
      this.isPdfLoading = true;
      NProgress.start();
      NProgress.set(0.1);
     
      axios
        .get(`service_job_pdf/${id}`, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Service_Job_" + id + ".pdf");
          document.body.appendChild(link);
          link.click();
          
          // Clean up
          document.body.removeChild(link);
          window.URL.revokeObjectURL(url);
          
          // Hide loading overlay after a short delay
          setTimeout(() => {
            this.isPdfLoading = false;
            NProgress.done();
            this.makeToast("success", this.$t("PDF_downloaded_successfully") || "PDF downloaded successfully", this.$t("Success") || "Success");
          }, 500);
        })
        .catch(() => {
          // Hide loading overlay on error
          this.isPdfLoading = false;
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },
    //------------------------------ Print -------------------------\\
    printJob() {
      this.$htmlToPaper('print_Service_Job');
    }
  }
};
</script>

<style scoped lang="scss">
/* ========================================
   SERVICE JOB DETAILS PAGE
   ======================================== */

.service-job-details-container {
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  overflow: hidden;
  margin-bottom: 30px;
}

/* Header */
.job-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 24px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    border-radius: 50%;
  }

  .header-content {
    display: flex;
    align-items: center;
    gap: 16px;
    z-index: 1;
  }

  .header-icon {
    width: 56px;
    height: 56px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
  }

  .header-text {
    .job-title {
      margin: 0;
      font-size: 24px;
      font-weight: 700;
      color: white;
      letter-spacing: -0.5px;
    }

    .job-ref {
      margin: 6px 0 0 0;
      font-size: 14px;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
    }
  }

  .header-actions {
    display: flex;
    gap: 8px;
    z-index: 1;
  }

  .action-btn {
    width: 44px;
    height: 44px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: white;
    font-size: 18px;
    text-decoration: none;

    &:hover {
      background: rgba(255, 255, 255, 0.25);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    &.pdf-btn:hover {
      background: rgba(239, 68, 68, 0.9);
      border-color: rgba(239, 68, 68, 0.9);
    }

    &.print-btn:hover {
      background: rgba(59, 130, 246, 0.9);
      border-color: rgba(59, 130, 246, 0.9);
    }

    &.edit-btn:hover {
      background: rgba(16, 185, 129, 0.9);
      border-color: rgba(16, 185, 129, 0.9);
    }

    &.close-btn:hover {
      background: rgba(239, 68, 68, 0.9);
      border-color: rgba(239, 68, 68, 0.9);
      transform: rotate(90deg);
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
}

/* Content */
.job-content {
  padding: 32px;
  background: #f8f9fc;
}

.job-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

/* Info Cards */
.info-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transition: transform 0.3s ease;
  }

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);

    &::before {
      transform: scaleX(1);
    }
  }

  .card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f1f5f9;
  }

  .card-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
  }

  .customer-icon {
    color: #667eea;
  }

  .technician-icon {
    color: #10b981;
  }

  .service-icon {
    color: #f5576c;
  }

  .job-type-icon {
    color: #8b5cf6;
  }

  .status-icon {
    color: #43e97b;
  }

  .datetime-icon {
    color: #fa709a;
  }

  .reference-icon {
    color: #8b5cf6;
  }

  .card-title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .card-body {
    .info-value {
      font-size: 16px;
      font-weight: 600;
      color: #1e293b;
      line-height: 1.5;
    }
  }
}

/* Status Badge */
.status-badge-modern {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulse 2s ease infinite;
  }

  &.pending {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    
    .status-dot {
      background: #f59e0b;
    }
  }

  &.in-progress {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: #1e40af;
    
    .status-dot {
      background: #3b82f6;
    }
  }

  &.completed {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    
    .status-dot {
      background: #10b981;
    }
  }

  &.cancelled {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    
    .status-dot {
      background: #ef4444;
    }
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Notes Section */
.notes-section {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
  border-left: 4px solid #667eea;

  .notes-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 700;
    color: #667eea;
    text-transform: uppercase;
    letter-spacing: 0.5px;

    i {
      font-size: 20px;
    }
  }

  .notes-content {
    p {
      margin: 0;
      color: #475569;
      line-height: 1.8;
      white-space: pre-line;
    }
  }
}

/* Checklist Section */
.checklist-section {
  background: white;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);

  .checklist-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 0 20px 0;
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 0.5px;

    i {
      font-size: 20px;
      color: #667eea;
    }
  }

  .checklist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
  }

  .checklist-item {
    background: #f8f9fc;
    border-radius: 12px;
    padding: 16px;
    border: 2px solid #e2e8f0;
    transition: all 0.3s ease;

    &:hover {
      border-color: #cbd5e1;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    &.completed {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border-color: #10b981;

      .checklist-item-name {
        color: #065f46;
      }
    }

    .checklist-item-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
    }

    .checklist-checkbox {
      font-size: 20px;
      flex-shrink: 0;
    }

    .checklist-item-name {
      font-size: 15px;
      font-weight: 600;
      color: #1e293b;
      flex: 1;
    }

    .checklist-category {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: #64748b;
      margin-top: 8px;

      i {
        font-size: 14px;
      }
    }
  }
}

/* Empty Checklist */
.empty-checklist {
  background: white;
  border-radius: 16px;
  padding: 40px;
  text-align: center;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);

  i {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 16px;
  }

  p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
  }
}

/* Error State */
.error-state {
  background: white;
  border-radius: 16px;
  padding: 60px;
  text-align: center;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);

  i {
    font-size: 64px;
    color: #ef4444;
    margin-bottom: 20px;
  }

  p {
    margin: 0 0 24px 0;
    color: #64748b;
    font-size: 16px;
  }
}

/* Totals Bar */
.totals-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  background: white;
  border-radius: 16px;
  padding: 18px 24px;
  margin: 0 0 24px 0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}
.totals-block { min-width: 140px; }
.totals-label {
  font-size: 11px;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: .04em;
  margin-bottom: 4px;
}
.totals-value {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}

/* Generic section card (shared by device, intake, items, photos, payments) */
.section-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin: 0 0 24px 0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}
.section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 0 20px 0;
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  i {
    font-size: 20px;
    color: #667eea;
  }
}

/* Device grid */
.device-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 14px;
}
.device-cell { background: #f8f9fc; border-radius: 10px; padding: 12px 14px; }
.device-cell-wide { grid-column: 1 / -1; }
.dev-label {
  font-size: 11px;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: .04em;
  margin-bottom: 4px;
}
.dev-value {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}
.accessory-pill {
  display: inline-block;
  background: #e0e7ff;
  color: #3730a3;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  margin: 2px 4px 2px 0;
}

/* Intake / Diagnostic */
.intake-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}
.intake-block {
  background: #f8f9fc;
  border-radius: 10px;
  padding: 14px 16px;

  h4 {
    font-size: 12px;
    text-transform: uppercase;
    color: #6b7280;
    margin: 0 0 8px 0;
    letter-spacing: .04em;
  }
  p { margin: 0; color: #1e293b; line-height: 1.6; white-space: pre-line; }
}

/* Quote */
.quote-row {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
}
.quote-cell { min-width: 180px; }

/* Items table */
.items-display-table th {
  font-size: 11px;
  text-transform: uppercase;
  color: #6b7280;
  letter-spacing: .04em;
}

/* Photos gallery */
.photos-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}
.photo-cell {
  position: relative;
  border: 1px solid #e7eaee;
  border-radius: 8px;
  padding: 6px;
  background: #fff;

  img {
    width: 100%;
    height: 140px;
    object-fit: cover;
    cursor: zoom-in;
    border-radius: 4px;
  }
  .photo-stage {
    position: absolute;
    top: 10px;
    left: 10px;
  }
  small { display: block; margin-top: 4px; }
}

/* ===== Back button ===== */
.sjd-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-weight: 500;
  border-radius: 10px;
  padding: 8px 14px;
  transition: all .2s ease;
  text-decoration: none;
  font-size: 13.5px;

  &:hover {
    background: #f1f5f9;
    color: #667eea;
    border-color: #c7d2fe;
    transform: translateX(-2px);
    text-decoration: none;
  }

  svg { width: 16px; height: 16px; }
}

/* ===== Hero header enhancements ===== */
.header-eyebrow {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}
.header-ref-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  letter-spacing: .02em;

  svg { width: 13px; height: 13px; }
}
.header-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  background: rgba(255, 255, 255, 0.92);
  color: #1e293b;

  .hsp-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #94a3b8;
    animation: pulse 2s ease infinite;
  }
  &.pending { color: #92400e; .hsp-dot { background: #f59e0b; } }
  &.in-progress { color: #1e40af; .hsp-dot { background: #3b82f6; } }
  &.completed { color: #065f46; .hsp-dot { background: #10b981; } }
  &.cancelled { color: #991b1b; .hsp-dot { background: #ef4444; } }
}
.header-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.hm-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 8px;
  font-size: 12.5px;
  color: rgba(255, 255, 255, 0.95);
  font-weight: 500;

  svg { width: 14px; height: 14px; opacity: .9; }
}

/* ===== Status Journey Timeline ===== */
.status-journey {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  background: #fff;
  padding: 22px 24px 16px;
  margin: 0;
  border-bottom: 1px solid #f1f5f9;
  overflow-x: auto;
}
.sj-step {
  flex: 1;
  min-width: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  text-align: center;
}
.sj-step__node {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  color: #94a3b8;
  border: 2px solid #e2e8f0;
  position: relative;
  z-index: 2;
  transition: all .25s ease;

  svg { width: 16px; height: 16px; }
}
.sj-step__label {
  margin-top: 8px;
  font-size: 11.5px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .03em;
}
.sj-step__bar {
  position: absolute;
  top: 18px;
  left: calc(50% + 22px);
  right: calc(-50% + 22px);
  height: 2px;
  background: #e2e8f0;
  z-index: 1;
}
.sj-step--done {
  .sj-step__node {
    background: linear-gradient(135deg, #10b981, #059669);
    border-color: #10b981;
    color: #fff;
  }
  .sj-step__label { color: #059669; }
  .sj-step__bar { background: #10b981; }
}
.sj-step--current {
  .sj-step__node {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: #667eea;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.18);
    animation: pulseRing 2s ease infinite;
  }
  .sj-step__label { color: #4338ca; font-weight: 700; }
}
@keyframes pulseRing {
  0%, 100% { box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.18); }
  50% { box-shadow: 0 0 0 8px rgba(102, 126, 234, 0.08); }
}

.status-cancelled-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: linear-gradient(135deg, #fee2e2, #fecaca);
  color: #991b1b;
  padding: 14px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-size: 13px;
  border-bottom: 1px solid #fecaca;

  svg { width: 18px; height: 18px; }
}

/* ===== Finance stat cards ===== */
.finance-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin: 0 0 24px;
}
.fstat {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 14px;
  padding: 18px 20px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: #cbd5e1;
  }
}
.fstat--total::before { background: linear-gradient(180deg, #6366f1, #8b5cf6); }
.fstat--paid::before { background: linear-gradient(180deg, #10b981, #059669); }
.fstat--due::before { background: linear-gradient(180deg, #ef4444, #dc2626); }
.fstat--settled::before { background: linear-gradient(180deg, #10b981, #059669); }
.fstat--warranty::before { background: linear-gradient(180deg, #f59e0b, #d97706); }
.fstat--delivered::before { background: linear-gradient(180deg, #3b82f6, #2563eb); }

.fstat__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 44px;
  background: #f1f5f9;
  color: #475569;

  svg { width: 22px; height: 22px; }
}
.fstat--total .fstat__icon { background: #eef2ff; color: #6366f1; }
.fstat--paid .fstat__icon { background: #ecfdf5; color: #059669; }
.fstat--due .fstat__icon { background: #fef2f2; color: #dc2626; }
.fstat--settled .fstat__icon { background: #ecfdf5; color: #059669; }
.fstat--warranty .fstat__icon { background: #fffbeb; color: #d97706; }
.fstat--delivered .fstat__icon { background: #eff6ff; color: #2563eb; }

.fstat__body { flex: 1; min-width: 0; }
.fstat__label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #6b7280;
  font-weight: 600;
}
.fstat__value {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  margin-top: 4px;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
}
.fstat__value--sm { font-size: 14px; font-weight: 600; }
.fstat__progress {
  margin-top: 10px;
  height: 6px;
  background: #e5e7eb;
  border-radius: 999px;
  overflow: hidden;
}
.fstat__progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #34d399, #10b981);
  transition: width .4s ease;
}
.fstat__percent {
  margin-top: 6px;
  font-size: 11px;
  font-weight: 600;
  color: #059669;
}
.fstat__chip {
  display: inline-block;
  margin-top: 6px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
}
.fstat__chip--ok { background: #d1fae5; color: #065f46; }
.fstat__chip--warn { background: #fef3c7; color: #92400e; }
.fstat__chip--danger { background: #fee2e2; color: #991b1b; }
.fstat__chip--expired { background: #f1f5f9; color: #64748b; }

/* ===== Modern table ===== */
.section-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 22px;
  padding: 0 8px;
  background: #eef2ff;
  color: #4338ca;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  margin-left: auto;
  text-transform: none;
  letter-spacing: 0;
}
.modern-table-wrap {
  border: 1px solid #eef0f4;
  border-radius: 12px;
  overflow: hidden;
  overflow-x: auto;
}
.modern-table {
  width: 100%;
  margin: 0;
  border-collapse: collapse;
}
.modern-table thead th {
  background: #fafbfc;
  border-bottom: 1px solid #eef0f4;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #64748b;
  font-weight: 700;
  padding: 12px 14px;
  text-align: left;
}
.modern-table thead th.text-right { text-align: right; }
.modern-table tbody td {
  padding: 14px;
  border-top: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 13.5px;
  color: #1f2937;
  transition: background .15s ease;
}
.modern-table tbody tr:hover td { background: #fafbff; }
.modern-table tfoot td {
  padding: 12px 14px;
  background: #fafbfc;
  border-top: 1px solid #eef0f4;
  font-size: 13.5px;
  color: #475569;
}
.modern-table tfoot .tfoot-row--grand td {
  font-weight: 700;
  font-size: 15px;
  color: #0f172a;
  background: linear-gradient(135deg, #fafbfc, #eef2ff);
}
.td-strong { font-weight: 600; color: #0f172a; }
.td-amount {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #0f172a;
}
.td-date {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #475569;
  svg { width: 14px; height: 14px; color: #94a3b8; }
}
.td-notes {
  display: inline-block;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
  color: #64748b;
}
.ref-pill {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12.5px;
  background: #f1f5f9;
  color: #475569;
  padding: 3px 8px;
  border-radius: 6px;
  font-weight: 500;
}
.method-pill {
  display: inline-block;
  padding: 4px 10px;
  background: #eef2ff;
  color: #4338ca;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 500;
}
.type-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid transparent;

  svg { width: 13px; height: 13px; }
}
.type-chip--part { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
.type-chip--labor { background: #ecfeff; color: #0e7490; border-color: #a5f3fc; }
.type-chip--service { background: #fef3c7; color: #92400e; border-color: #fde68a; }

.kind-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid transparent;

  svg { width: 13px; height: 13px; }
}
.kind-chip--payment { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
.kind-chip--deposit { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.kind-chip--refund { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

/* ===== Photos gallery enhancements ===== */
.photos-gallery .photo-cell {
  position: relative;
  border: 1px solid #e7eaee;
  border-radius: 12px;
  padding: 6px;
  background: #fff;
  cursor: zoom-in;
  transition: transform .25s ease, box-shadow .25s ease;
  overflow: hidden;

  img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    display: block;
    transition: transform .35s ease;
  }
  &:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.12);

    img { transform: scale(1.05); }
    .photo-overlay { opacity: 1; }
  }
}
.photo-overlay {
  position: absolute;
  inset: 6px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.45);
  color: #fff;
  opacity: 0;
  transition: opacity .25s ease;
  pointer-events: none;

  svg { width: 32px; height: 32px; }
}
.photo-stage-pill {
  position: absolute;
  top: 12px;
  left: 12px;
  padding: 3px 10px;
  border-radius: 999px;
  background: rgba(255,255,255,0.92);
  color: #4338ca;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  backdrop-filter: blur(4px);
}
.photo-caption {
  display: block;
  margin-top: 6px;
  padding: 0 4px;
  font-size: 12px;
  color: #64748b;
}

/* ===== Responsive ===== */
@media (max-width: 768px) {
  .job-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  .header-actions { align-self: stretch; }
  .status-journey { padding: 16px 12px 10px; }
  .sj-step__label { font-size: 10px; }
  .finance-stats { grid-template-columns: 1fr; }
}

/* PDF Loading Overlay */
.pdf-loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(5px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;

  .loading-content {
    background: white;
    padding: 40px;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);

    .spinner {
      margin: 0 auto 20px;
    }

    p {
      margin: 0;
      color: #1e293b;
      font-size: 16px;
      font-weight: 600;
    }
  }
}
</style>

