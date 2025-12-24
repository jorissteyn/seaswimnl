<template>
    <div class="conditions-card water">
        <h2>
            Water Conditions
            <span v-if="data.location" v-tooltip="locationTooltip" class="info-icon">ⓘ</span>
        </h2>
        <dl class="conditions-list">
            <div class="condition-item">
                <dt>Temperature</dt>
                <dd>{{ formatTemp(data.temperature) }}</dd>
            </div>
            <div class="condition-item">
                <dt>Wind on water <span v-if="data.location" v-tooltip="locationTooltip" class="info-icon">ⓘ</span></dt>
                <dd class="wind-display">
                    <template v-if="data.windSpeed !== null">
                        <span :class="['beaufort-badge', beaufortClass]">
                            Bft {{ getBeaufort(data.windSpeed) }}
                        </span>
                        <span class="wind-speed">{{ formatWindSpeed(data.windSpeed) }}</span>
                        <span v-if="data.windDirection" class="compass" :title="data.windDirection">
                            <span class="compass-letter compass-n">N</span>
                            <span class="compass-letter compass-e">E</span>
                            <span class="compass-letter compass-s">S</span>
                            <span class="compass-letter compass-w">W</span>
                            <span class="compass-arrow" :style="{ transform: `rotate(${getWindDegrees(data.windDirection)}deg)` }"></span>
                        </span>
                    </template>
                    <template v-else>N/A</template>
                </dd>
            </div>
            <div class="condition-item">
                <dt>Wave Height</dt>
                <dd>
                    {{ formatMeters(data.waveHeight) }}
                    <span v-if="data.waveHeightBuoy" v-tooltip="waveHeightTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div class="condition-item">
                <dt>Wave Period</dt>
                <dd>
                    {{ formatSeconds(data.wavePeriod) }}
                    <span v-if="data.wavePeriodStation" v-tooltip="wavePeriodTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div class="condition-item">
                <dt>Wave Direction</dt>
                <dd>
                    {{ formatWaveDirection(data.waveDirection, data.waveDirectionCompass) }}
                    <span v-if="data.waveDirectionStation" v-tooltip="waveDirectionTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
        </dl>
        <p class="timestamp">Last updated: {{ formatTime(data.measuredAt) }}</p>
    </div>
</template>

<script>
export default {
    name: 'WaterConditions',
    props: {
        data: {
            type: Object,
            required: true,
        },
    },
    computed: {
        locationTooltip() {
            if (!this.data.location) return '';
            return `RWS station: ${this.data.location.name} (${this.data.location.id})`;
        },
        waveHeightTooltip() {
            if (!this.data.waveHeightBuoy) return '';
            const buoy = this.data.waveHeightBuoy;
            return `Measured at ${buoy.name} (${buoy.id}), ${buoy.distanceKm} km away`;
        },
        wavePeriodTooltip() {
            if (!this.data.wavePeriodStation) return '';
            const station = this.data.wavePeriodStation;
            return `Measured at ${station.name} (${station.id}), ${station.distanceKm} km away`;
        },
        waveDirectionTooltip() {
            if (!this.data.waveDirectionStation) return '';
            const station = this.data.waveDirectionStation;
            return `Measured at ${station.name} (${station.id}), ${station.distanceKm} km away`;
        },
        beaufortClass() {
            if (this.data.windSpeed === null) return '';
            const bft = this.getBeaufort(this.data.windSpeed);
            if (bft <= 3) return 'bft-calm';
            if (bft <= 5) return 'bft-moderate';
            if (bft <= 7) return 'bft-strong';
            return 'bft-severe';
        },
    },
    methods: {
        formatTemp(value) {
            return value !== null ? `${value}°C` : 'N/A';
        },
        formatMeters(value) {
            return value !== null ? `${value}m` : 'N/A';
        },
        formatSeconds(value) {
            return value !== null ? `${value}s` : 'N/A';
        },
        formatWaveDirection(degrees, compass) {
            if (degrees === null) return 'N/A';
            return `${compass} (${Math.round(degrees)}°)`;
        },
        formatWindSpeed(speed) {
            if (speed === null) return 'N/A';
            return `${Math.round(speed)} km/h`;
        },
        getWindDegrees(direction) {
            const directions = {
                'N': 0, 'NNO': 22.5, 'NO': 45, 'ONO': 67.5,
                'O': 90, 'OZO': 112.5, 'ZO': 135, 'ZZO': 157.5,
                'Z': 180, 'ZZW': 202.5, 'ZW': 225, 'WZW': 247.5,
                'W': 270, 'WNW': 292.5, 'NW': 315, 'NNW': 337.5,
                // English variants
                'NNE': 22.5, 'NE': 45, 'ENE': 67.5,
                'E': 90, 'ESE': 112.5, 'SE': 135, 'SSE': 157.5,
                'S': 180, 'SSW': 202.5, 'SW': 225, 'WSW': 247.5,
            };
            return directions[direction] || 0;
        },
        getBeaufort(kmh) {
            const ms = kmh / 3.6;
            if (ms < 0.3) return 0;
            if (ms < 1.6) return 1;
            if (ms < 3.4) return 2;
            if (ms < 5.5) return 3;
            if (ms < 8.0) return 4;
            if (ms < 10.8) return 5;
            if (ms < 13.9) return 6;
            if (ms < 17.2) return 7;
            if (ms < 20.8) return 8;
            if (ms < 24.5) return 9;
            if (ms < 28.5) return 10;
            if (ms < 32.7) return 11;
            return 12;
        },
        formatTime(isoString) {
            if (!isoString) return 'N/A';
            return new Date(isoString).toLocaleString();
        },
    },
};
</script>
