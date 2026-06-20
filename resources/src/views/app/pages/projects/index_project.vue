<template>
  <div class="main-content projects-page-modern">
    <breadcumb :page="$t('Project_List')" :folder="$t('Projects')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">

      <!-- Enhanced Stats Dashboard -->
      <div class="stats-dashboard">
        <div class="stat-card completed">
          <div class="stat-icon-wrapper">
            <lucide-icon class="stat-icon" name="check" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{count_completed}}</div>
            <div class="stat-label">{{$t('complete')}}</div>
          </div>
          <div class="stat-decoration"></div>
        </div>

        <div class="stat-card not-started">
          <div class="stat-icon-wrapper">
            <lucide-icon class="stat-icon" name="pause" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{count_not_started}}</div>
            <div class="stat-label">{{$t('Not_Started')}}</div>
          </div>
          <div class="stat-decoration"></div>
        </div>

        <div class="stat-card in-progress">
          <div class="stat-icon-wrapper">
            <lucide-icon class="stat-icon" name="loader" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{count_in_progress}}</div>
            <div class="stat-label">{{$t('In_Progress')}}</div>
          </div>
          <div class="stat-decoration"></div>
        </div>

        <div class="stat-card cancelled">
          <div class="stat-icon-wrapper">
            <lucide-icon class="stat-icon" name="x" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{count_cancelled}}</div>
            <div class="stat-label">{{$t('Cancelled')}}</div>
          </div>
          <div class="stat-decoration"></div>
        </div>
      </div>

      <!-- Modern Control Bar -->
      <div class="control-bar">
        <div class="control-left">
          <div class="view-switcher">
            <button 
              @click="viewMode = 'list'"
              :class="['view-btn', { active: viewMode === 'list' }]"
            >
              <lucide-icon name="list" />
              <span>{{ $t('List') || 'List' }}</span>
            </button>
            <button 
              @click="viewMode = 'kanban'"
              :class="['view-btn', { active: viewMode === 'kanban' }]"
            >
              <lucide-icon name="columns-3" />
              <span>{{ $t('Kanban') || 'Kanban' }}</span>
            </button>
            <div class="active-indicator" :style="{ transform: viewMode === 'kanban' ? 'translateX(100%)' : 'translateX(0)' }"></div>
          </div>
        </div>

        <div class="control-right">
          <button class="action-btn filter-btn" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            <span>{{ $t("Filter") }}</span>
          </button>
          <button @click="Project_PDF()" class="action-btn pdf-btn">
            <lucide-icon name="copy" />
            <span>PDF</span>
          </button>
          <vue-excel-xlsx
            class="action-btn excel-btn"
            :data="projects"
            :columns="columns"
            :file-name="'Projects'"
            :file-type="'xlsx'"
            :sheet-name="'Projects'"
          >
            <lucide-icon name="file-spreadsheet" />
            <span>EXCEL</span>
          </vue-excel-xlsx>
          <router-link to="/app/projects/store" class="action-btn add-btn">
            <lucide-icon name="plus" />
            <span>{{$t('Add')}}</span>
          </router-link>
        </div>
      </div>

      <!-- List View -->
      <transition name="fade-slide">
        <div v-if="viewMode === 'list'" class="list-view-container">
          <div class="table-card">
            <vue-good-table
              mode="remote"
              :columns="columns"
              :totalRows="totalRows"
              :rows="projects"
              @on-page-change="onPageChange"
              @on-per-page-change="onPerPageChange"
              @on-sort-change="onSortChange"
              @on-search="onSearch"
              :search-options="{
                enabled: true,
                placeholder: $t('Search_this_table'),  
              }"
              :pagination-options="{
              enabled: true,
              mode: 'records',
              nextLabel: 'next',
              prevLabel: 'prev',
            }"
              styleClass="modern-table table-hover vgt-table"
            >

              <template slot="table-row" slot-scope="props">
                <span v-if="props.column.field == 'actions'">
                  <div class="action-buttons-cell">
                    <router-link
                      title="Edit"
                      v-b-tooltip.hover
                      :to="'/app/projects/edit/'+props.row.id"
                      class="action-icon edit"
                    >
                      <lucide-icon name="pencil" />
                    </router-link>

                    <a
                      title="Delete"
                      class="action-icon delete"
                      v-b-tooltip.hover
                      @click="Remove_Project(props.row.id)"
                    >
                      <lucide-icon name="x" />
                    </a>
                  </div>
                </span>

                <div v-else-if="props.column.field == 'status'">
                  <span
                    v-if="props.row.status == 'completed'"
                    class="status-badge completed"
                  >{{$t('complete')}}</span>
                  <span
                    v-else-if="props.row.status == 'not_started'"
                    class="status-badge not-started"
                  >{{$t('Not_Started')}}</span>
                  <span
                    v-else-if="props.row.status == 'progress'"
                    class="status-badge in-progress"
                  >{{$t('In_Progress')}}</span>

                  <span
                    v-else-if="props.row.status == 'cancelled'"
                    class="status-badge cancelled"
                  >{{$t('Cancelled')}}</span>

                  <span
                    v-else-if="props.row.status == 'hold'"
                    class="status-badge on-hold"
                  >{{$t('On_Hold')}}</span>
                </div>
              </template>
            </vue-good-table>
          </div>
        </div>
      </transition>

      <!-- Kanban View -->
      <transition name="fade-slide">
        <div v-if="viewMode === 'kanban'" class="kanban-container">
          <div class="kanban-board">
            <!-- Not Started Column -->
            <div class="kanban-column not-started-col">
              <div class="column-header">
                <div class="header-left">
                  <div class="header-icon">
                    <lucide-icon name="pause" />
                  </div>
                  <h3 class="header-title">{{$t('Not_Started')}}</h3>
                </div>
                <span class="header-count">{{getProjectsByStatus('not_started').length}}</span>
              </div>
              <div class="column-body">
                <transition-group name="card-list" tag="div" class="cards-wrapper">
                  <div 
                    v-for="project in getProjectsByStatus('not_started')" 
                    :key="project.id"
                    class="project-card"
                  >
                    <div class="card-top">
                      <h4 class="card-title">{{project.title}}</h4>
                      <div class="card-menu">
                        <router-link
                          :to="'/app/projects/edit/'+project.id"
                          class="card-action edit"
                          v-b-tooltip.hover
                          title="Edit"
                        >
                          <lucide-icon name="pencil" />
                        </router-link>
                        <a
                          @click="Remove_Project(project.id)"
                          class="card-action delete"
                          v-b-tooltip.hover
                          title="Delete"
                        >
                          <lucide-icon name="x" />
                        </a>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="user" />
                          {{$t('Customer')}}
                        </span>
                        <span class="info-value">{{project.client_name}}</span>
                      </div>
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="building" />
                          {{$t('Company')}}
                        </span>
                        <span class="info-value">{{project.company_name}}</span>
                      </div>
                      <div class="date-range">
                        <div class="date-item start">
                          <lucide-icon name="calendar-days" />
                          <span>{{project.start_date}}</span>
                        </div>
                        <div class="date-separator">→</div>
                        <div class="date-item end">
                          <lucide-icon name="clock" />
                          <span>{{project.end_date}}</span>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <span class="status-tag not-started">{{$t('Not_Started')}}</span>
                    </div>
                  </div>
                </transition-group>
                <div v-if="getProjectsByStatus('not_started').length === 0" class="empty-column">
                  <lucide-icon name="package-open" />
                  <p>No projects yet</p>
                </div>
              </div>
            </div>

            <!-- In Progress Column -->
            <div class="kanban-column in-progress-col">
              <div class="column-header">
                <div class="header-left">
                  <div class="header-icon">
                    <lucide-icon name="loader" />
                  </div>
                  <h3 class="header-title">{{$t('In_Progress')}}</h3>
                </div>
                <span class="header-count">{{getProjectsByStatus('progress').length}}</span>
              </div>
              <div class="column-body">
                <transition-group name="card-list" tag="div" class="cards-wrapper">
                  <div 
                    v-for="project in getProjectsByStatus('progress')" 
                    :key="project.id"
                    class="project-card"
                  >
                    <div class="card-top">
                      <h4 class="card-title">{{project.title}}</h4>
                      <div class="card-menu">
                        <router-link
                          :to="'/app/projects/edit/'+project.id"
                          class="card-action edit"
                          v-b-tooltip.hover
                          title="Edit"
                        >
                          <lucide-icon name="pencil" />
                        </router-link>
                        <a
                          @click="Remove_Project(project.id)"
                          class="card-action delete"
                          v-b-tooltip.hover
                          title="Delete"
                        >
                          <lucide-icon name="x" />
                        </a>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="user" />
                          {{$t('Customer')}}
                        </span>
                        <span class="info-value">{{project.client_name}}</span>
                      </div>
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="building" />
                          {{$t('Company')}}
                        </span>
                        <span class="info-value">{{project.company_name}}</span>
                      </div>
                      <div class="date-range">
                        <div class="date-item start">
                          <lucide-icon name="calendar-days" />
                          <span>{{project.start_date}}</span>
                        </div>
                        <div class="date-separator">→</div>
                        <div class="date-item end">
                          <lucide-icon name="clock" />
                          <span>{{project.end_date}}</span>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <span class="status-tag in-progress">{{$t('In_Progress')}}</span>
                    </div>
                  </div>
                </transition-group>
                <div v-if="getProjectsByStatus('progress').length === 0" class="empty-column">
                  <lucide-icon name="package-open" />
                  <p>No projects yet</p>
                </div>
              </div>
            </div>

            <!-- On Hold Column -->
            <div class="kanban-column on-hold-col">
              <div class="column-header">
                <div class="header-left">
                  <div class="header-icon">
                    <lucide-icon name="pause" />
                  </div>
                  <h3 class="header-title">{{$t('On_Hold')}}</h3>
                </div>
                <span class="header-count">{{getProjectsByStatus('hold').length}}</span>
              </div>
              <div class="column-body">
                <transition-group name="card-list" tag="div" class="cards-wrapper">
                  <div 
                    v-for="project in getProjectsByStatus('hold')" 
                    :key="project.id"
                    class="project-card"
                  >
                    <div class="card-top">
                      <h4 class="card-title">{{project.title}}</h4>
                      <div class="card-menu">
                        <router-link
                          :to="'/app/projects/edit/'+project.id"
                          class="card-action edit"
                          v-b-tooltip.hover
                          title="Edit"
                        >
                          <lucide-icon name="pencil" />
                        </router-link>
                        <a
                          @click="Remove_Project(project.id)"
                          class="card-action delete"
                          v-b-tooltip.hover
                          title="Delete"
                        >
                          <lucide-icon name="x" />
                        </a>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="user" />
                          {{$t('Customer')}}
                        </span>
                        <span class="info-value">{{project.client_name}}</span>
                      </div>
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="building" />
                          {{$t('Company')}}
                        </span>
                        <span class="info-value">{{project.company_name}}</span>
                      </div>
                      <div class="date-range">
                        <div class="date-item start">
                          <lucide-icon name="calendar-days" />
                          <span>{{project.start_date}}</span>
                        </div>
                        <div class="date-separator">→</div>
                        <div class="date-item end">
                          <lucide-icon name="clock" />
                          <span>{{project.end_date}}</span>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <span class="status-tag on-hold">{{$t('On_Hold')}}</span>
                    </div>
                  </div>
                </transition-group>
                <div v-if="getProjectsByStatus('hold').length === 0" class="empty-column">
                  <lucide-icon name="package-open" />
                  <p>No projects yet</p>
                </div>
              </div>
            </div>

            <!-- Completed Column -->
            <div class="kanban-column completed-col">
              <div class="column-header">
                <div class="header-left">
                  <div class="header-icon">
                    <lucide-icon name="check" />
                  </div>
                  <h3 class="header-title">{{$t('complete')}}</h3>
                </div>
                <span class="header-count">{{getProjectsByStatus('completed').length}}</span>
              </div>
              <div class="column-body">
                <transition-group name="card-list" tag="div" class="cards-wrapper">
                  <div 
                    v-for="project in getProjectsByStatus('completed')" 
                    :key="project.id"
                    class="project-card"
                  >
                    <div class="card-top">
                      <h4 class="card-title">{{project.title}}</h4>
                      <div class="card-menu">
                        <router-link
                          :to="'/app/projects/edit/'+project.id"
                          class="card-action edit"
                          v-b-tooltip.hover
                          title="Edit"
                        >
                          <lucide-icon name="pencil" />
                        </router-link>
                        <a
                          @click="Remove_Project(project.id)"
                          class="card-action delete"
                          v-b-tooltip.hover
                          title="Delete"
                        >
                          <lucide-icon name="x" />
                        </a>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="user" />
                          {{$t('Customer')}}
                        </span>
                        <span class="info-value">{{project.client_name}}</span>
                      </div>
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="building" />
                          {{$t('Company')}}
                        </span>
                        <span class="info-value">{{project.company_name}}</span>
                      </div>
                      <div class="date-range">
                        <div class="date-item start">
                          <lucide-icon name="calendar-days" />
                          <span>{{project.start_date}}</span>
                        </div>
                        <div class="date-separator">→</div>
                        <div class="date-item end">
                          <lucide-icon name="clock" />
                          <span>{{project.end_date}}</span>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <span class="status-tag completed">{{$t('complete')}}</span>
                    </div>
                  </div>
                </transition-group>
                <div v-if="getProjectsByStatus('completed').length === 0" class="empty-column">
                  <lucide-icon name="package-open" />
                  <p>No projects yet</p>
                </div>
              </div>
            </div>

            <!-- Cancelled Column -->
            <div class="kanban-column cancelled-col">
              <div class="column-header">
                <div class="header-left">
                  <div class="header-icon">
                    <lucide-icon name="x" />
                  </div>
                  <h3 class="header-title">{{$t('Cancelled')}}</h3>
                </div>
                <span class="header-count">{{getProjectsByStatus('cancelled').length}}</span>
              </div>
              <div class="column-body">
                <transition-group name="card-list" tag="div" class="cards-wrapper">
                  <div 
                    v-for="project in getProjectsByStatus('cancelled')" 
                    :key="project.id"
                    class="project-card"
                  >
                    <div class="card-top">
                      <h4 class="card-title">{{project.title}}</h4>
                      <div class="card-menu">
                        <router-link
                          :to="'/app/projects/edit/'+project.id"
                          class="card-action edit"
                          v-b-tooltip.hover
                          title="Edit"
                        >
                          <lucide-icon name="pencil" />
                        </router-link>
                        <a
                          @click="Remove_Project(project.id)"
                          class="card-action delete"
                          v-b-tooltip.hover
                          title="Delete"
                        >
                          <lucide-icon name="x" />
                        </a>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="user" />
                          {{$t('Customer')}}
                        </span>
                        <span class="info-value">{{project.client_name}}</span>
                      </div>
                      <div class="info-row">
                        <span class="info-label">
                          <lucide-icon name="building" />
                          {{$t('Company')}}
                        </span>
                        <span class="info-value">{{project.company_name}}</span>
                      </div>
                      <div class="date-range">
                        <div class="date-item start">
                          <lucide-icon name="calendar-days" />
                          <span>{{project.start_date}}</span>
                        </div>
                        <div class="date-separator">→</div>
                        <div class="date-item end">
                          <lucide-icon name="clock" />
                          <span>{{project.end_date}}</span>
                        </div>
                      </div>
                    </div>
                    <div class="card-footer">
                      <span class="status-tag cancelled">{{$t('Cancelled')}}</span>
                    </div>
                  </div>
                </transition-group>
                <div v-if="getProjectsByStatus('cancelled').length === 0" class="empty-column">
                  <lucide-icon name="package-open" />
                  <p>No projects yet</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- Enhanced Filter Sidebar -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow sidebar-class="modern-sidebar">
      <div class="sidebar-content">
        <b-row>
          <!-- Start Date  -->
          <b-col md="12">
            <b-form-group :label="$t('start_date')" class="modern-form-group">
              <b-form-input type="date" v-model="Filter_start_date" class="modern-input"></b-form-input>
            </b-form-group>
          </b-col>

           <!-- End Date  -->
           <b-col md="12">
            <b-form-group :label="$t('Finish_Date')" class="modern-form-group">
              <b-form-input type="date" v-model="Filter_end_date" class="modern-input"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Client  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')" class="modern-form-group">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_Client"
                :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
                class="modern-select"
              />
            </b-form-group>
          </b-col>

          <!-- company  -->
          <b-col md="12">
            <b-form-group :label="$t('Company')" class="modern-form-group">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Company')"
                v-model="Filter_Company"
                :options="companies.map(companies => ({label: companies.name, value: companies.id}))"
                class="modern-select"
              />
            </b-form-group>
          </b-col>

           <!-- Status  -->
           <b-col md="12">
            <b-form-group :label="$t('Status')" class="modern-form-group">
              <v-select
                v-model="Filter_status"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="
                      [
                        {label: 'Completed', value: 'completed'},
                        {label: 'Not Started', value: 'not_started'},
                        {label: 'In Progress', value: 'progress'},
                        {label: 'Cancelled', value: 'cancelled'},
                        {label: 'On Hold', value: 'hold'},
                      ]"
                class="modern-select"
              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Projects(serverParams.page)"
              variant="primary"
              size="md"
              block
              class="modern-btn"
            >
              <lucide-icon name="filter" />
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger" size="md" block class="modern-btn">
              <lucide-icon name="power" />
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  metaInfo: {
    title: "Projects"
  },
  data() {
    return {
      isLoading: true,
      viewMode: 'kanban', // 'list' or 'kanban'
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      totalRows: "",
      search: "",
      limit: "10",
      Filter_start_date: "",
      Filter_end_date: "",
      Filter_status: "",
      Filter_Client: "",
      Filter_Company: "",
      projects: [],
      clients: [],
      companies: [],
      count_not_started: "",
      count_in_progress: "",
      count_cancelled: "",
      count_completed: "",
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    columns() {
      return [
        {
          label: this.$t("title"),
          field: "title",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label:  this.$t("Company"),
          field: "company_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("start_date"),
          field: "start_date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Finish_Date"),
          field: "end_date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Status"),
          field: "status",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },

  methods: {
    // Get projects by status for Kanban view
    getProjectsByStatus(status) {
      return this.projects.filter(project => project.status === status);
    },

    //---------------------- Projects PDF -------------------------------\\
    Project_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e) {}
      pdf.setFont("Vazirmatn", "normal");

      const headers = [
        self.$t("title"),
        self.$t("Customer"),
        self.$t("Company"),
        self.$t("start_date"),
        self.$t("Finish_Date")
      ];

      const body = (self.projects || []).map(project => ([
        project.title,
        project.client_name,
        project.company_name,
        project.start_date,
        project.end_date
      ]));

      const marginX = 40;
      const rtl =
        (self.$i18n && ['ar','fa','ur','he'].includes(self.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      autoTable(pdf, {
        head: [headers],
        body: body,
        startY: 110,
        theme: 'striped',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 9, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [63,81,181], textColor: 255 },
        alternateRowStyles: { fillColor: [245,247,250] },
        didDrawPage: (d) => {
          const pageW = pdf.internal.pageSize.getWidth();
          const pageH = pdf.internal.pageSize.getHeight();

          // Header banner
          pdf.setFillColor(63,81,181);
          pdf.rect(0, 0, pageW, 60, 'F');

          // Title
          pdf.setTextColor(255);
          pdf.setFont('Vazirmatn', 'bold');
          pdf.setFontSize(16);
          const title = self.$t('Project_List') || 'Project List';
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' })
              : pdf.text(title, marginX, 38);

          // Reset text color
          pdf.setTextColor(33);

          // Footer page numbers
          pdf.setFontSize(8);
          const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
          rtl ? pdf.text(pn, marginX, pageH - 14, { align: 'left' })
              : pdf.text(pn, pageW - marginX, pageH - 14, { align: 'right' });
        }
      });

      pdf.save("Project_List.pdf");
    },

    //------ update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Projects(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Projects(1);
      }
    },


    //------ Event Sort Change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "client_name") {
        field = "client_id";
      } else if (params[0].field == "company_name") {
        field = "company_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_Projects(this.serverParams.page);
    },

    //------ Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Projects(this.serverParams.page);
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_start_date = "";
      this.Filter_end_date = "";
      this.Filter_status = "";
      this.Filter_Client = "";
      this.Filter_Company = "";
      this.Get_Projects(this.serverParams.page);
    },

    // Simply replaces null values with strings=''
    setToStrings() {
      if (this.Filter_Client === null) {
        this.Filter_Client = "";
      } else if (this.Filter_Company === null) {
        this.Filter_Company = "";
      }
    },

    //------------------------------------------------ Get All projects -------------------------------\\
    Get_Projects(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "projects?page=" +
            page +
            "&status=" +
            this.Filter_status +
            "&client_id=" +
            this.Filter_Client +
            "&start_date=" +
            this.Filter_start_date +
            "&end_date=" +
            this.Filter_end_date +
            "&company_id=" +
            this.Filter_Company +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.projects = response.data.projects;
          this.companies = response.data.companies;
          this.clients = response.data.clients;
          this.totalRows = response.data.totalRows;
          this.count_not_started = response.data.count_not_started;
          this.count_in_progress = response.data.count_in_progress;
          this.count_cancelled = response.data.count_cancelled;
          this.count_completed = response.data.count_completed;

          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //------------------------------- Remove projects -------------------------\\

    Remove_Project(id) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("projects/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("event_delete_projects");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

  },

  //----------------------------- Created function-------------------
  created: function() {
    this.Get_Projects(1);

    Fire.$on("event_delete_projects", () => {
      setTimeout(() => {
        // Complete the animation of theprogress bar.
        NProgress.done();
        this.Get_Projects(this.serverParams.page);
      }, 500);
    });
  }
};
</script>

<style scoped lang="scss">
// ============================================
// MODERN PROJECTS PAGE - PREMIUM DESIGN
// ============================================

.projects-page-modern {
  padding: 1.5rem;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
  min-height: 100vh;
}

.page-wrapper {
  max-width: 1920px;
  margin: 0 auto;
}

// ============================================
// ENHANCED STATS DASHBOARD
// ============================================
.stats-dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  position: relative;
  background: white;
  border-radius: 16px;
  padding: 1.75rem;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);

  &:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  }

  .stat-decoration {
    position: absolute;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    opacity: 0.08;
    top: -30px;
    right: -30px;
  }

  .stat-icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;

    .stat-icon {
      font-size: 28px;
      color: white;
    }
  }

  .stat-content {
    flex: 1;
    z-index: 1;
  }

  .stat-value {
    font-size: 2.25rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .stat-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  &.completed {
    .stat-icon-wrapper {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    .stat-decoration {
      background: #10b981;
    }
  }

  &.not-started {
    .stat-icon-wrapper {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    .stat-decoration {
      background: #f59e0b;
    }
  }

  &.in-progress {
    .stat-icon-wrapper {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }
    .stat-decoration {
      background: #3b82f6;
    }
  }

  &.cancelled {
    .stat-icon-wrapper {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    .stat-decoration {
      background: #ef4444;
    }
  }
}

// ============================================
// MODERN CONTROL BAR
// ============================================
.control-bar {
  background: white;
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  flex-wrap: wrap;
  gap: 1rem;
}

.control-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.view-switcher {
  position: relative;
  background: #f1f5f9;
  border-radius: 12px;
  padding: 0.35rem;
  display: flex;
  gap: 0.35rem;

  .view-btn {
    position: relative;
    z-index: 2;
    background: transparent;
    border: none;
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;

    i {
      font-size: 1.1rem;
    }

    &.active {
      color: #667eea;
    }

    &:hover {
      color: #667eea;
    }
  }

  .active-indicator {
    position: absolute;
    top: 0.35rem;
    left: 0.35rem;
    width: calc(50% - 0.35rem);
    height: calc(100% - 0.7rem);
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1;
  }
}

.control-right {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.action-btn {
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.625rem 1.25rem;
  font-weight: 600;
  font-size: 0.875rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;

  i {
    font-size: 1rem;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  &.filter-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
  }

  &.pdf-btn:hover {
    border-color: #10b981;
    color: #10b981;
    background: #d1fae5;
  }

  &.excel-btn:hover {
    border-color: #ef4444;
    color: #ef4444;
    background: #fee2e2;
  }

  &.add-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;

    &:hover {
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    }
  }
}

// Remove focus outlines on click for buttons within this page
.projects-page-modern {
  .action-btn,
  .view-btn,
  .modern-btn,
  .action-icon,
  .card-action,
  button,
  a.action-btn {
    &:focus,
    &:focus-visible,
    &:active:focus {
      outline: none !important;
      box-shadow: none !important;
    }
  }
}

// ============================================
// LIST VIEW CONTAINER
// ============================================
.list-view-container {
  animation: fadeSlideIn 0.5s ease;
}

.table-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.modern-table {
  ::v-deep {
    .vgt-table {
      border: none;
      
      thead {
        background: #f8fafc;
        
        th {
          border: none;
          padding: 1rem;
          font-weight: 700;
          color: #334155;
          text-transform: uppercase;
          font-size: 0.75rem;
          letter-spacing: 0.05em;
        }
      }
      
      tbody {
        tr {
          border-bottom: 1px solid #f1f5f9;
          transition: all 0.2s ease;
          
          &:hover {
            background: #f8fafc;
          }
          
          td {
            padding: 1rem;
            color: #475569;
            font-size: 0.9rem;
          }
        }
      }
    }
  }
}

.action-buttons-cell {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

.action-icon {
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;

  &.edit {
    color: #10b981;
    background: #d1fae5;

    &:hover {
      background: #10b981;
      color: white;
      transform: scale(1.1);
    }
  }

  &.delete {
    color: #ef4444;
    background: #fee2e2;

    &:hover {
      background: #ef4444;
      color: white;
      transform: scale(1.1);
    }
  }
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  &.completed {
    background: #d1fae5;
    color: #065f46;
  }

  &.not-started {
    background: #fed7aa;
    color: #92400e;
  }

  &.in-progress {
    background: #dbeafe;
    color: #1e40af;
  }

  &.cancelled {
    background: #fee2e2;
    color: #991b1b;
  }

  &.on-hold {
    background: #e5e7eb;
    color: #374151;
  }
}

// ============================================
// KANBAN CONTAINER
// ============================================
.kanban-container {
  animation: fadeSlideIn 0.5s ease;
}

.kanban-board {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
  align-items: start;
}

.kanban-column {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  transition: all 0.3s ease;

  &:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  }
}

.column-header {
  padding: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 2px solid #f1f5f9;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.header-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  
  i {
    font-size: 1.4rem;
    color: white;
  }
}

.not-started-col .header-icon {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.in-progress-col .header-icon {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.on-hold-col .header-icon {
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
}

.completed-col .header-icon {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.cancelled-col .header-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.header-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
}

.header-count {
  width: 32px;
  height: 32px;
  background: #f1f5f9;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: #475569;
  font-size: 0.875rem;
}

.column-body {
  padding: 1rem;
  max-height: calc(100vh - 340px);
  overflow-y: auto;

  &::-webkit-scrollbar {
    width: 6px;
  }

  &::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
  }

  &::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;

    &:hover {
      background: #94a3b8;
    }
  }
}

.cards-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.project-card {
  background: #fafbfc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    border-color: #cbd5e1;
  }
}

.card-top {
  padding: 1.25rem;
  background: white;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.card-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.4;
  flex: 1;
  overflow-wrap: break-word;
}

.card-menu {
  display: flex;
  gap: 0.5rem;
  opacity: 0;
  transition: opacity 0.3s ease;

  .project-card:hover & {
    opacity: 1;
  }
}

.card-action {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;

  i {
    font-size: 0.9rem;
  }

  &.edit {
    color: #10b981;
    background: #d1fae5;

    &:hover {
      background: #10b981;
      color: white;
    }
  }

  &.delete {
    color: #ef4444;
    background: #fee2e2;

    &:hover {
      background: #ef4444;
      color: white;
    }
  }
}

.card-body {
  padding: 1.25rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
  gap: 0.75rem;

  &:last-of-type {
    margin-bottom: 0;
  }
}

.info-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  gap: 0.5rem;

  i {
    font-size: 0.9rem;
    opacity: 0.7;
  }
}

.info-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  text-align: right;
  overflow-wrap: break-word;
  max-width: 55%;
}

.date-range {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #f1f5f9;
}

.date-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;

  i {
    font-size: 0.85rem;
    opacity: 0.7;
  }

  &.start {
    flex: 1;
  }

  &.end {
    flex: 1;
  }
}

.date-separator {
  color: #cbd5e1;
  font-weight: 600;
}

.card-footer {
  padding: 1rem 1.25rem;
  background: white;
  border-top: 1px solid #f1f5f9;
}

.status-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  &.completed {
    background: #d1fae5;
    color: #065f46;
  }

  &.not-started {
    background: #fed7aa;
    color: #92400e;
  }

  &.in-progress {
    background: #dbeafe;
    color: #1e40af;
  }

  &.cancelled {
    background: #fee2e2;
    color: #991b1b;
  }

  &.on-hold {
    background: #e5e7eb;
    color: #374151;
  }
}

.empty-column {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: #94a3b8;

  i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.3;
  }

  p {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 500;
  }
}

// ============================================
// MODERN SIDEBAR
// ============================================
.modern-sidebar {
  ::v-deep {
    .b-sidebar-header {
      padding: 1.5rem;
      border-bottom: 2px solid #f1f5f9;

      .close {
        font-size: 1.5rem;
        opacity: 0.5;
        transition: opacity 0.3s ease;

        &:hover {
          opacity: 1;
        }
      }
    }

    // Remove Bootstrap focus ring for buttons inside the sidebar
    .btn:focus,
    .btn:active:focus,
    .btn.focus {
      outline: none !important;
      box-shadow: none !important;
    }
  }
}

.sidebar-content {
  padding: 1.5rem;
}

.modern-form-group {
  margin-bottom: 1.5rem;

  ::v-deep {
    label {
      font-weight: 600;
      color: #334155;
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
    }
  }
}

.modern-input {
  border-radius: 10px;
  border: 2px solid #e2e8f0;
  padding: 0.75rem 1rem;
  transition: all 0.3s ease;

  &:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
}

.modern-select {
  ::v-deep {
    .vs__dropdown-toggle {
      border-radius: 10px;
      border: 2px solid #e2e8f0;
      padding: 0.5rem 1rem;
    }

    .vs__search {
      padding: 0.25rem 0;
    }

    &.vs--open .vs__dropdown-toggle {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
  }
}

.modern-btn {
  border-radius: 10px;
  font-weight: 600;
  padding: 0.75rem 1.5rem;
  transition: all 0.3s ease;
  border: none;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  i {
    margin-right: 0.5rem;
  }
}

// ============================================
// ANIMATIONS
// ============================================
@keyframes fadeSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.4s ease;
}

.fade-slide-enter,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(20px);
}

.card-list-enter-active,
.card-list-leave-active {
  transition: all 0.4s ease;
}

.card-list-enter,
.card-list-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

.card-list-move {
  transition: transform 0.4s ease;
}

// ============================================
// RESPONSIVE DESIGN
// ============================================
@media (max-width: 1400px) {
  .kanban-board {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  }
}

@media (max-width: 991px) {
  .stats-dashboard {
    grid-template-columns: repeat(2, 1fr);
  }

  .control-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .control-left,
  .control-right {
    width: 100%;
    justify-content: center;
  }

  .kanban-board {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .projects-page-modern {
    padding: 1rem;
  }

  .stats-dashboard {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .stat-card {
    padding: 1.25rem;

    .stat-value {
      font-size: 1.75rem;
    }
  }

  .view-switcher {
    width: 100%;

    .view-btn {
      flex: 1;
      justify-content: center;
    }
  }

  .control-right {
    width: 100%;
    flex-direction: column;

    .action-btn {
      width: 100%;
      justify-content: center;
    }
  }

  .card-menu {
    opacity: 1;
  }

  .info-value {
    max-width: 60%;
  }
}
</style>
