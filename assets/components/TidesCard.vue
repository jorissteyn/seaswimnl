<template>
    <div class="conditions-card tides">
        <h2>Tides</h2>
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
                <dd>
                    {{ data.previous.timeFormatted }} ({{ formatHeight(data.previous.heightCm) }})
                    <span v-tooltip="previousTideTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div v-if="data.next" class="condition-item tide-next">
                <dt>Next {{ data.next.typeLabel }}</dt>
                <dd>
                    {{ data.next.timeFormatted }} ({{ formatHeight(data.next.heightCm) }})
                    <span v-tooltip="nextTideTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div v-if="data.nextHigh && (!data.next || data.next.type !== 'high')" class="condition-item">
                <dt>Next High Tide</dt>
                <dd>
                    {{ data.nextHigh.timeFormatted }} ({{ formatHeight(data.nextHigh.heightCm) }})
                    <span v-tooltip="nextHighTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div v-if="data.nextLow && (!data.next || data.next.type !== 'low')" class="condition-item">
                <dt>Next Low Tide</dt>
                <dd>
                    {{ data.nextLow.timeFormatted }} ({{ formatHeight(data.nextLow.heightCm) }})
                    <span v-tooltip="nextLowTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div v-if="waterHeight !== null" class="condition-item">
                <dt>Current Water Height</dt>
                <dd>
                    {{ formatWaterHeight(waterHeight) }}
                    <span v-tooltip="waterHeightTooltip" class="info-icon">ⓘ</span>
                </dd>
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
        waterHeightRaw: {
            type: Object,
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
        stationLine() {
            if (!this.data.location) return '';
            const station = this.data.station;
            if (station && station.distanceKm >= 2) {
                return `[RWS] ${this.data.location.name} (${this.data.location.id}), ${station.distanceKm} km away`;
            }
            return `[RWS] ${this.data.location.name} (${this.data.location.id})`;
        },
        previousTideTooltip() {
            if (!this.data.previous) return '';
            return this.buildTideTooltip(this.data.previous.heightCm);
        },
        nextTideTooltip() {
            if (!this.data.next) return '';
            return this.buildTideTooltip(this.data.next.heightCm);
        },
        nextHighTooltip() {
            if (!this.data.nextHigh) return '';
            return this.buildTideTooltip(this.data.nextHigh.heightCm);
        },
        nextLowTooltip() {
            if (!this.data.nextLow) return '';
            return this.buildTideTooltip(this.data.nextLow.heightCm);
        },
        waterHeightTooltip() {
            if (!this.data.location) return '';
            let tooltip = this.stationLine;
            if (this.waterHeightRaw) {
                const raw = this.waterHeightRaw;
                tooltip += `\n\n${raw.code} | ${raw.compartiment} | ${raw.value} ${raw.unit}`;
            }
            return tooltip;
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
        // Calculate current position on the wave (0 to 1) based on water height
        currentProgress() {
            // Use water height to determine position on the cosine curve
            if (this.waterHeight !== null) {
                const waterHeightCm = this.waterHeight * 100;
                const midHeight = (this.lowTideHeight + this.highTideHeight) / 2;
                const halfRange = (this.highTideHeight - this.lowTideHeight) / 2;
                if (halfRange === 0) return 0.5;

                // Normalize height to [-1, 1] and clamp
                const normalized = Math.max(-1, Math.min(1, (waterHeightCm - midHeight) / halfRange));

                // Invert cosine to find progress on curve
                // Rising: progress 0→1 as height goes low→high (normalized -1→1)
                // Falling: progress 0→1 as height goes high→low (normalized 1→-1)
                if (this.isRising) {
                    return Math.acos(-normalized) / Math.PI;
                } else {
                    return Math.acos(normalized) / Math.PI;
                }
            }

            // Fall back to time-based progress
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
            const midY = this.graphHeight / 2; // NAP reference (0 cm)

            // Fall back to wave-based position if no water height
            if (this.waterHeight === null) {
                const progress = this.currentProgress;
                const phase = this.isRising ? 0 : Math.PI;
                const waveY = Math.cos(progress * Math.PI + phase);
                const amplitude = (this.lowTideY - this.highTideY) / 2;
                return (this.highTideY + this.lowTideY) / 2 + waveY * amplitude;
            }

            // Position based on actual water height, centered at NAP (0)
            const waterHeightCm = this.waterHeight * 100;

            if (waterHeightCm <= 0) {
                // Below NAP: scale toward lowTideY
                if (this.lowTideHeight >= 0) return midY;
                const ratio = waterHeightCm / this.lowTideHeight;
                return midY + ratio * (this.lowTideY - midY);
            } else {
                // Above NAP: scale toward highTideY
                if (this.highTideHeight <= 0) return midY;
                const ratio = waterHeightCm / this.highTideHeight;
                return midY - ratio * (midY - this.highTideY);
            }
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
        buildTideTooltip(heightCm) {
            let tooltip = this.stationLine;
            // Tide predictions use WATHTE with astronomisch process type
            tooltip += `\n\nWATHTE | OW | ${heightCm} cm`;
            return tooltip;
        },
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
