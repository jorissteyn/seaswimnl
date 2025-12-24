<template>
    <div class="conditions-card tides">
        <h2>
            Tides
            <span v-if="data.location" v-tooltip="locationTooltip" class="info-icon">ⓘ</span>
        </h2>
        <div v-if="canShowGraph" class="tide-graph">
            <svg :viewBox="`0 0 ${graphWidth} ${graphHeight}`" preserveAspectRatio="xMidYMid meet">
                <!-- Wave curve -->
                <path :d="wavePath" class="tide-wave" fill="none" stroke-width="2" />
                <!-- NAP reference line (0 cm) -->
                <line
                    :x1="0"
                    :y1="graphHeight / 2"
                    :x2="graphWidth"
                    :y2="graphHeight / 2"
                    class="current-height-line"
                    stroke-width="2"
                    stroke-dasharray="4,4"
                />
                <!-- Current position marker -->
                <circle
                    :cx="currentPositionX"
                    :cy="currentPositionY"
                    r="6"
                    class="current-position"
                />
                <!-- Time labels - bottom corners -->
                <text :x="5" :y="graphHeight - 5" class="tide-label">{{ previousTimeLabel }}</text>
                <text :x="graphWidth - 5" :y="graphHeight - 5" class="tide-label" text-anchor="end">{{ nextTimeLabel }}</text>
                <!-- Height labels - outside wave area based on wave direction -->
                <text :x="isRising ? waveLeft - 5 : waveRight + 5" :y="lowTideY + 4" class="tide-label" :text-anchor="isRising ? 'end' : 'start'">{{ formatHeight(lowTideHeight) }}</text>
                <text :x="isRising ? waveRight + 5 : waveLeft - 5" :y="highTideY + 4" class="tide-label" :text-anchor="isRising ? 'start' : 'end'">{{ formatHeight(highTideHeight) }}</text>
            </svg>
        </div>
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
    data() {
        return {
            graphWidth: 300,
            graphHeight: 105,
            topPadding: 15,
            bottomPadding: 25,
            leftPadding: 45,
            rightPadding: 45,
        };
    },
    computed: {
        locationTooltip() {
            if (!this.data.location) return '';
            return `RWS station: ${this.data.location.name} (${this.data.location.id})`;
        },
        canShowGraph() {
            return this.data.previous && this.data.next;
        },
        previousTime() {
            return this.data.previous ? new Date(this.data.previous.time) : null;
        },
        nextTime() {
            return this.data.next ? new Date(this.data.next.time) : null;
        },
        previousTimeLabel() {
            return this.data.previous?.timeFormatted || '';
        },
        nextTimeLabel() {
            return this.data.next?.timeFormatted || '';
        },
        isRising() {
            // Rising if previous was low tide
            return this.data.previous?.type === 'low';
        },
        lowTideHeight() {
            if (!this.data.previous || !this.data.next) return 0;
            return this.isRising ? this.data.previous.heightCm : this.data.next.heightCm;
        },
        highTideHeight() {
            if (!this.data.previous || !this.data.next) return 0;
            return this.isRising ? this.data.next.heightCm : this.data.previous.heightCm;
        },
        // Wave area boundaries
        waveLeft() {
            return this.leftPadding;
        },
        waveRight() {
            return this.graphWidth - this.rightPadding;
        },
        waveWidth() {
            return this.waveRight - this.waveLeft;
        },
        lowTideY() {
            return this.graphHeight - this.bottomPadding;
        },
        highTideY() {
            return this.topPadding;
        },
        // Calculate current position on the wave (0 to 1)
        currentProgress() {
            if (!this.previousTime || !this.nextTime) return 0.5;
            const now = new Date();
            const totalDuration = this.nextTime - this.previousTime;
            const elapsed = now - this.previousTime;
            return Math.max(0, Math.min(1, elapsed / totalDuration));
        },
        currentPositionX() {
            return this.waveLeft + this.currentProgress * this.waveWidth;
        },
        currentPositionY() {
            // Use cosine for smooth wave, phase depends on rising/falling
            const progress = this.currentProgress;
            // For rising tide: start at bottom (low), end at top (high)
            // For falling tide: start at top (high), end at bottom (low)
            // cos(0)=1 maps to lowTideY (bottom), cos(π)=-1 maps to highTideY (top)
            const phase = this.isRising ? 0 : Math.PI;
            const waveY = Math.cos(progress * Math.PI + phase);
            // Map from [-1, 1] to [highTideY, lowTideY]
            const midY = (this.highTideY + this.lowTideY) / 2;
            const amplitude = (this.lowTideY - this.highTideY) / 2;
            return midY + waveY * amplitude;
        },
        wavePath() {
            const points = [];
            const steps = 50;
            for (let i = 0; i <= steps; i++) {
                const progress = i / steps;
                const x = this.waveLeft + progress * this.waveWidth;
                // Cosine wave - phase determines direction
                const phase = this.isRising ? 0 : Math.PI;
                const waveY = Math.cos(progress * Math.PI + phase);
                const midY = (this.highTideY + this.lowTideY) / 2;
                const amplitude = (this.lowTideY - this.highTideY) / 2;
                const y = midY + waveY * amplitude;
                points.push(`${i === 0 ? 'M' : 'L'} ${x} ${y}`);
            }
            return points.join(' ');
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
