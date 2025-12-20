<template>
    <div class="conditions-card tides">
        <h2>Tides</h2>
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
        </dl>
        <p class="timestamp">Heights relative to NAP</p>
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
    },
    methods: {
        formatHeight(cm) {
            return `${cm > 0 ? '+' : ''}${Math.round(cm)} cm`;
        },
    },
};
</script>
