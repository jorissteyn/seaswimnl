<template>
    <div class="conditions-card metrics">
        <h2>Swim Metrics</h2>
        <dl class="conditions-list">
            <div class="condition-item">
                <dt>Safety <span v-tooltip="safetyTooltip" class="info-icon">ⓘ</span></dt>
                <dd>
                    <span :class="['safety-badge', data.safetyScore]">
                        {{ data.safetyLabel }}
                    </span>
                </dd>
            </div>
            <div class="condition-item">
                <dt>Comfort Index <span v-tooltip="comfortTooltip" class="info-icon">ⓘ</span></dt>
                <dd class="comfort-value">
                    <span class="comfort-bar">
                        <span class="comfort-fill" :style="{ width: (data.comfortIndex * 10) + '%' }"></span>
                    </span>
                    <span>{{ data.comfortIndex }}/10</span>
                </dd>
            </div>
        </dl>
    </div>
</template>

<script>
export default {
    name: 'SwimMetrics',
    props: {
        data: {
            type: Object,
            required: true,
        },
    },
    computed: {
        safetyTooltip() {
            let tooltip = `Thresholds:
🟢 Green: Safe conditions
🟡 Yellow: Water <15°C, waves >1m, or wind >20 km/h
🔴 Red: Water <10°C, waves >2m, or wind >40 km/h`;

            if (this.data.safetyDescription) {
                tooltip += `\n\nCurrent: ${this.data.safetyDescription}`;
            }
            return tooltip;
        },
        comfortTooltip() {
            return `Weighted score (1-10) based on:
• Water temp (40%) - ideal 18-22°C
• Air temp (20%) - ideal 20-25°C
• Wind (20%) - calm <10 km/h best
• Sunpower (10%) - ideal 300-600 W/m²
• Waves (10%) - calm <0.3m best

8-10 Excellent | 6-7 Good | 4-5 Fair | 1-3 Poor`;
        },
    },
};
</script>

<style scoped>
.comfort-value {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.comfort-bar {
    width: 80px;
    height: 6px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    overflow: hidden;
}

.comfort-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--color-danger) 0%, var(--color-warning) 50%, var(--color-success) 100%);
    border-radius: 3px;
    transition: width 0.3s ease;
}
</style>
