<template>
    <div class="swim-metrics">
        <h3>Swim Metrics</h3>

        <div class="metrics-summary">
            <div :class="['safety-indicator', 'safety-' + data.safetyScore]">
                <span class="safety-label">{{ data.safetyLabel }}</span>
                <span class="safety-description">{{ data.safetyDescription }}</span>
            </div>

            <div class="recommendation">
                <span class="recommendation-label">{{ data.recommendationLabel }}</span>
                <span class="recommendation-text">{{ data.recommendationExplanation }}</span>
            </div>
        </div>

        <div class="metrics-details">
            <div class="metric">
                <span class="metric-label">Comfort Index</span>
                <span class="metric-value">
                    <span class="comfort-bar">
                        <span class="comfort-fill" :style="{ width: (data.comfortIndex * 10) + '%' }"></span>
                    </span>
                    {{ data.comfortIndex }}/10 ({{ data.comfortLabel }})
                </span>
            </div>
        </div>
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
.swim-metrics {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.swim-metrics h3 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: #495057;
}

.metrics-summary {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.safety-indicator {
    flex: 1;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
}

.safety-green {
    background: #d4edda;
    color: #155724;
}

.safety-yellow {
    background: #fff3cd;
    color: #856404;
}

.safety-red {
    background: #f8d7da;
    color: #721c24;
}

.safety-label {
    display: block;
    font-weight: bold;
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.safety-description {
    display: block;
    font-size: 0.9rem;
}

.recommendation {
    flex: 1;
    padding: 1rem;
    background: #e9ecef;
    border-radius: 8px;
    text-align: center;
}

.recommendation-label {
    display: block;
    font-weight: bold;
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.recommendation-text {
    display: block;
    font-size: 0.9rem;
    color: #6c757d;
}

.metric {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.metric-label {
    font-weight: 500;
    color: #495057;
}

.metric-value {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.comfort-bar {
    width: 100px;
    height: 8px;
    background: #dee2e6;
    border-radius: 4px;
    overflow: hidden;
}

.comfort-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745 0%, #ffc107 50%, #dc3545 100%);
    transition: width 0.3s ease;
}
</style>
