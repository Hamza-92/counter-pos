<template>
  <button @click="exportToExcel" :class="buttonClass" type="button">
    <slot></slot>
  </button>
</template>

<script>
import * as XLSX from 'xlsx';

export default {
  name: 'ExcelExport',
  props: {
    data: {
      type: Array,
      required: true
    },
    columns: {
      type: Array,
      required: true
    },
    fileName: {
      type: String,
      default: 'export'
    },
    fileType: {
      type: String,
      default: 'xlsx'
    },
    sheetName: {
      type: String,
      default: 'Sheet1'
    },
    // Support both class and buttonClass for flexibility
    class: {
      type: String,
      default: ''
    }
  },
  computed: {
    buttonClass() {
      return this.class || 'btn btn-sm btn-outline-danger ripple m-1';
    }
  },
  methods: {
    exportToExcel() {
      try {
        // Map data to match column structure
        const exportData = this.data.map(row => {
          const newRow = {};
          this.columns.forEach(col => {
            newRow[col.label] = row[col.field] !== undefined ? row[col.field] : '';
          });
          return newRow;
        });

        // Create worksheet
        const ws = XLSX.utils.json_to_sheet(exportData);
        
        // Create workbook
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, this.sheetName);
        
        // Generate file
        XLSX.writeFile(wb, `${this.fileName}.${this.fileType}`);
        
        this.$emit('success');
      } catch (error) {
        console.error('Error exporting to Excel:', error);
        this.$emit('error', error);
      }
    }
  }
};
</script>

