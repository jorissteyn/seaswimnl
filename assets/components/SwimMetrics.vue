<template>
    <div class="conditions-card metrics">
        <h2>Swim Metrics</h2>
        <dl class="conditions-list">
            <div class="condition-item">
                <dt>Safety</dt>
                <dd>
                    <span :class="['safety-badge', data.safetyScore]">
                        {{ data.safetyLabel }}
                    </span>
                </dd>
            </div>
            <div class="condition-item">
                <dt>Conditions</dt>
                <dd>{{ data.safetyDescription }}</dd>
            </div>
            <div class="condition-item">
                <dt>Recommendation</dt>
                <dd>{{ data.recommendationLabel }}</dd>
            </div>
            <div class="condition-item">
                <dt>Details</dt>
                <dd>{{ data.recommendationExplanation }}</dd>
            </div>
            <div class="condition-item">
                <dt>Comfort Index</dt>
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
