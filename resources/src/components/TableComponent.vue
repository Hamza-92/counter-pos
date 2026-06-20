<template>
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key || col.field" scope="col">
            {{ col.label || col.key || col.field }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, rIdx) in rows" :key="rIdx">
          <td v-for="col in columns" :key="(col.key || col.field) + '-' + rIdx">
            {{ formatCell(row, col) }}
          </td>
        </tr>
        <tr v-if="!rows || rows.length === 0">
          <td :colspan="columns.length" class="text-center text-muted py-4">
            No data available
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  
</template>

<script>
export default {
  name: "TableComponent",
  props: {
    columns: {
      type: Array,
      required: true,
      default: () => []
    },
    rows: {
      type: Array,
      required: true,
      default: () => []
    }
  },
  methods: {
    formatCell(row, col) {
      const key = col.key || col.field;
      if (!key) return "";
      const value = row[key];
      return value == null ? "" : value;
    }
  }
};
</script>

<style scoped>
/* Keep styles minimal and Bootstrap-friendly */
</style>


