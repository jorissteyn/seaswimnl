<template>
    <div class="conditions-card tides">
        <h2>
            Tides
            <span v-if="data.location" v-tooltip="locationTooltip" class="info-icon">ⓘ</span>
        </h2>
        <dl class="conditions-list">
            <div v-if="data.previous" class="condition-item">
                <dt>Previous {{ data.previous.typeLabel }}</dt>
                <dd>{{ data.previous.timeFormatted }} ({{ formatHeight(data.previous.heightCm) }})</dd>
            </div>
            <div v-if="data.next" class="condition-item tide-next">
                <dt>Next {{ data.next.typeLabel }}</dt>
                <dd>{{ data.next.timeFormatted }} ({{ formatHeight(data.next.heightCm) }})</dd>
            </div>
            <div v-if="data.nextHigh && (!data.next || data.next.type !== 'high')" class="condition-item">
                <dt>Next High Tide</dt>
                <dd>{{ data.nextHigh.timeFormatted }} ({{ formatHeight(data.nextHigh.heightCm) }})</dd>
            </div>
            <div v-if="data.nextLow && (!data.next || data.next.type !== 'low')" class="condition-item">
                <dt>Next Low Tide</dt>
                <dd>{{ data.nextLow.timeFormatted }} ({{ formatHeight(data.nextLow.heightCm) }})</dd>
            </div>
            <div v-if="waterHeight !== null" class="condition-item">
                <dt>Current Water Height</dt>
                <dd>{{ formatWaterHeight(waterHeight) }}</dd>
            </div>
        </dl>
        <p class="timestamp">
            Heights relative to NAP<span v-if="measuredAt"> · Last updated: {{ formatTime(measuredAt) }}</span>
        </p>
    </div>
</template>

<script>
export default {
    name: 'TidesCard',
    props: {
        data: {
            type: Object,
            required: true,
        },
        waterHeight: {
            type: Number,
            default: null,
        },
        measuredAt: {
            type: String,
            default: null,
        },
    },
    computed: {
        locationTooltip() {
            if (!this.data.location) return '';
            return `RWS station: ${this.data.location.name} (${this.data.location.id})`;
        },
    },
    methods: {
        formatHeight(cm) {
            return `${cm > 0 ? '+' : ''}${Math.round(cm)} cm`;
        },
        formatWaterHeight(meters) {
            const cm = Math.round(meters * 100);
            return `${cm > 0 ? '+' : ''}${cm} cm`;
        },
        formatTime(isoString) {
            if (!isoString) return '';
            return new Date(isoString).toLocaleString();
        },
    },
};
</script>
