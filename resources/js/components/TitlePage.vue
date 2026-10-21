<script setup>
const props = defineProps({
  title: String,
  subtitle: String,
  client: String,
  project: String,
  version: String,
  dateSubmitted: String,
  submittedBy: String,
  submittedTo: String,
})
const table = [
  { id: 'Client', value: props.client },
  { id: 'Project', value: props.project },
  { id: 'Date Submitted', value: props.dateSubmitted },
  { id: 'Version', value: props.version },
  { id: 'Submitted by', value: props.submittedBy },
  { id: 'Submitted to', value: props.submittedTo },
]
</script>

<template>
  <div class="flex h-full w-full flex-1 flex-col">
    <div class="flex h-full grow items-center text-[30pt] font-extrabold text-[#0b6374]">
      {{ title }}<br />
      {{ subtitle }}
    </div>
    <div class="shrink-0">
      <table class="w-full">
        <tbody>
          <tr v-for="{ id, value } in table" :key="id" class="text-[10pt] text-bene-gray">
            <td class="py-2 font-bold">{{ id }}</td>
            <td class="py-2 font-normal">{{ value }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped></style>
