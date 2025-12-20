<template>
    <div class="conditions-card weather">
        <h2>Weather Conditions</h2>
        <dl class="conditions-list">
            <div class="condition-item">
                <dt>Air Temperature</dt>
                <dd>{{ formatTemp(data.airTemperature) }}</dd>
            </div>
            <div class="condition-item">
                <dt>Wind</dt>
                <dd>{{ formatWind(data.windSpeed, data.windDirection) }}</dd>
            </div>
            <div class="condition-item">
                <dt>UV Index</dt>
                <dd>{{ formatUV(data.uvIndex, data.uvLevel) }}</dd>
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
    methods: {
        formatTemp(value) {
            return value !== null ? `${value}°C` : 'N/A';
        },
        formatWind(speed, direction) {
            if (speed === null) return 'N/A';
            const dir = direction ? ` ${direction}` : '';
            return `${Math.round(speed)} km/h${dir}`;
        },
        formatUV(value, level) {
            if (value === null) return 'N/A';
            return `${value} (${level})`;
        },
        formatTime(isoString) {
            if (!isoString) return 'N/A';
            return new Date(isoString).toLocaleString();
        },
    },
};
</script>
