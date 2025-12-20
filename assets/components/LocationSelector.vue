<template>
    <div class="location-selector">
        <label for="location">Select Location:</label>
        <select
            id="location"
            :value="selected?.id"
            :disabled="loading"
            @change="onChange"
        >
            <option value="">-- Choose a location --</option>
            <option
                v-for="location in locations"
                :key="location.id"
                :value="location.id"
            >
                {{ location.name }}
            </option>
        </select>
    </div>
</template>

<script>
export default {
    name: 'LocationSelector',
    props: {
        locations: {
            type: Array,
            required: true,
        },
        selected: {
            type: Object,
            default: null,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['select'],
    methods: {
        onChange(event) {
            const id = event.target.value;
            const location = this.locations.find(l => l.id === id) || null;
            this.$emit('select', location);
        },
    },
};
</script>
