<template>
  <div class="entity-table-wrap">
    <table :class="['entity-table', tableClass]">
      <thead>
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            :class="col.thClass"
          >
            {{ col.label || '' }}
          </th>
        </tr>
      </thead>

      <tbody>
        <slot :columns="columns" :column-count="columnCount" />
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  tableClass: {
    type: String,
    default: '',
  },
})

const columnCount = computed(() => props.columns.length)
</script>
