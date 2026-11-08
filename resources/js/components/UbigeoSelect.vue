<script setup>
import { computed, ref } from 'vue';
import catalog from '../data/ubigeo-peru.json';
const props = defineProps({ modelValue: String });
const emit = defineEmits(['update:modelValue']);
const initialDistrict = catalog.distritos.find(d => d.codigo === props.modelValue);
const department = ref(initialDistrict?.departamento || '');
const province = ref(initialDistrict?.provincia || '');
const provinces = computed(() => catalog.provincias.filter(p => p.departamento === department.value));
const districts = computed(() => catalog.distritos.filter(d => d.provincia === province.value));
function resetDepartment() { province.value = ''; emit('update:modelValue', ''); }
</script>
<template>
    <div class="form-grid locations">
        <label>Departamento<select v-model="department" required @change="resetDepartment"><option disabled value="">Selecciona</option><option v-for="item in catalog.departamentos" :key="item.id" :value="item.id">{{ item.nombre }}</option></select></label>
        <label>Provincia<select v-model="province" required :disabled="!department" @change="emit('update:modelValue', '')"><option disabled value="">Selecciona</option><option v-for="item in provinces" :key="item.id" :value="item.id">{{ item.nombre }}</option></select></label>
        <label>Distrito<select :value="modelValue" required :disabled="!province" @change="emit('update:modelValue', $event.target.value)"><option disabled value="">Selecciona</option><option v-for="item in districts" :key="item.id" :value="item.codigo">{{ item.nombre }}</option></select></label>
    </div>
</template>
