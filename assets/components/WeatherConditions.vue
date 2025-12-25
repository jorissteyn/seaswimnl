<template>
    <div class="conditions-card weather">
        <h2>Weather Conditions</h2>
        <dl class="conditions-list">
            <div class="condition-item">
                <dt>Air Temperature</dt>
                <dd>
                    {{ formatTemp(data.airTemperature) }}
                    <span v-tooltip="temperatureTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div class="condition-item">
                <dt>Wind</dt>
                <dd class="wind-display">
                    <span v-if="data.windSpeed !== null" :class="['beaufort-badge', beaufortClass]">
                        Bft {{ getBeaufort(data.windSpeed) }}
                    </span>
                    <span class="wind-speed">{{ formatWindSpeedOnly(data.windSpeed) }}</span>
                    <span v-if="data.windDirection" class="compass" :title="data.windDirection">
                        <span class="compass-letter compass-n">N</span>
                        <span class="compass-letter compass-e">E</span>
                        <span class="compass-letter compass-s">S</span>
                        <span class="compass-letter compass-w">W</span>
                        <span class="compass-arrow" :style="{ transform: `rotate(${getWindDegrees(data.windDirection)}deg)` }"></span>
                    </span>
                    <span v-tooltip="windTooltip" class="info-icon">ⓘ</span>
                </dd>
            </div>
            <div class="condition-item">
                <dt>Sunpower</dt>
                <dd class="sunpower-display">
                    <template v-if="data.sunpower !== null">
                        <span class="sunpower-bar">
                            <span class="sunpower-fill" :style="{ width: sunpowerPercent + '%' }"></span>
                            <span class="sunpower-marker" :style="{ left: sunpowerPercent + '%' }"></span>
                        </span>
                        <span class="sun-icon">☀</span>
                        <span class="sunpower-value">{{ Math.round(data.sunpower) }} W/m²</span>
                        <span v-tooltip="sunpowerTooltip" class="info-icon">ⓘ</span>
                    </template>
                    <template v-else>N/A</template>
                </dd>
            </div>
        </dl>
        <p class="timestamp">Last updated: {{ formatTime(data.measuredAt) }}</p>
    </div>
</template>

<script>
export default {
    name: 'WeatherConditions',
    props: {
        data: {
            type: Object,
            required: true,
        },
    },
    computed: {
        stationLine() {
            if (!this.data.station) return '';
            const distance = this.data.station.distanceKm !== null && this.data.station.distanceKm >= 2 ? `, ${this.data.station.distanceKm} km away` : '';
            return `${this.data.station.name} (${this.data.station.code})${distance}`;
        },
        temperatureTooltip() {
            return this.buildTooltip(this.data.airTemperatureRaw);
        },
        windTooltip() {
            const speedRaw = this.data.windSpeedRaw;
            const dirRaw = this.data.windDirectionRaw;
            let tooltip = this.stationLine;
            if (speedRaw) {
                tooltip += `\n\n${speedRaw.field} | ${speedRaw.value} ${speedRaw.unit}`;
            }
            if (dirRaw) {
                tooltip += `\n${dirRaw.field} | ${dirRaw.value}`;
            }
            return tooltip;
        },
        sunpowerTooltip() {
            return this.buildTooltip(this.data.sunpowerRaw);
        },
        beaufortClass() {
            if (this.data.windSpeed === null) return '';
            const bft = this.getBeaufort(this.data.windSpeed);
            if (bft <= 3) return 'bft-calm';
            if (bft <= 5) return 'bft-moderate';
            if (bft <= 7) return 'bft-strong';
            return 'bft-severe';
        },
        sunpowerPercent() {
            if (this.data.sunpower === null) return 0;
            // Scale: 0-1000 W/m² maps to 0-100%
            return Math.min(100, Math.max(0, (this.data.sunpower / 1000) * 100));
        },
    },
    methods: {
        buildTooltip(raw) {
            let tooltip = this.stationLine;
            if (raw) {
                tooltip += `\n\n${raw.field} | ${raw.value} ${raw.unit}`;
            }
            return tooltip;
        },
        formatTemp(value) {
            return value !== null ? `${value}°C` : 'N/A';
        },
        formatWindSpeedOnly(speed) {
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
