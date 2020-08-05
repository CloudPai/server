<!--
  - @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
  - @author Julien Veyssier <eneiluj@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<li :class="{inline: inline }">
		<div id="weather-status-menu-item">
			<Actions
				id="weather-status-menu-item__subheader"
				:default-icon="weatherIcon"
				:menu-title="visibleMessage">
				<ActionButton
					icon="icon-crosshair"
					:close-after-click="true"
					@click="onBrowserLocationClick">
					{{ $t('weather_status', 'Detect location') }}
				</ActionButton>
				<ActionButton
					icon="icon-settings"
					:close-after-click="true"
					@click="onPersonalAddressClick">
					{{ $t('weather_status', 'Use personal settings location') }}
				</ActionButton>
				<ActionButton
					icon="icon-rename"
					:close-after-click="true"
					@click="onCustomAddressClick">
					{{ $t('weather_status', 'Set custom location') }}
				</ActionButton>
			</Actions>
		</div>
	</li>
</template>

<script>
// import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { Actions, ActionButton } from '@nextcloud/vue'
import * as network from './services/weatherStatusService'
// import { generateUrl } from '@nextcloud/router'

const MODE_BROWSER_LOCATION = 1
const MODE_MANUAL_LOCATION = 2

export default {
	name: 'App',
	components: {
		Actions, ActionButton,
	},
	props: {
		inline: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			loading: true,
			mode: MODE_BROWSER_LOCATION,
			address: null,
			lat: null,
			lon: null,
			forecasts: [],
			loop: null,
			icons: {
				clearsky_day: 'icon-clearsky-day',
				clearsky_night: 'icon-clearsky-night',
				cloudy: 'icon-cloudy',
				fair_day: 'icon-fair-day',
				fair_night: 'icon-fair-night',
				partlycloudy_day: 'icon-partlycloudy-day',
				partlycloudy_night: 'icon-partlycloudy-night',
				fog: 'icon-fog',
				lightrain: 'icon-lightrain',
				rain: 'icon-rain',
				rainshowers_day: 'icon-rainshowers-day',
				rainshowers_night: 'icon-rainshowers-night',
				lightrainshowers_day: 'icon-light-rainshowers-day',
				lightrainshowers_night: 'icon-light-rainshowers-night',
				heavyrainshowers_day: 'icon-heavy-rainshowers-day',
				heavyrainshowers_night: 'icon-heavy-rainshowers-night',
			},
		}
	},
	computed: {
		texts() {
			return {
				clearsky_day: this.$t('weather_status', 'Clear sky'),
				clearsky_night: this.$t('weather_status', 'Clear sky'),
				cloudy: this.$t('weather_status', 'Cloudy'),
				fair_day: this.$t('weather_status', 'Fair day'),
				fair_night: this.$t('weather_status', 'Fair night'),
				partlycloudy_day: this.$t('weather_status', 'Partly cloudy'),
				partlycloudy_night: this.$t('weather_status', 'Partly cloudy'),
				fog: this.$t('weather_status', 'Foggy'),
				lightrain: this.$t('weather_status', 'Light rain'),
				rain: this.$t('weather_status', 'Rain'),
				rainshowers_day: this.$t('weather_status', 'Rain showers'),
				rainshowers_night: this.$t('weather_status', 'Rain showers'),
				lightrainshowers_day: this.$t('weather_status', 'Light rain showers'),
				lightrainshowers_night: this.$t('weather_status', 'Light rain showers'),
				heavyrainshowers_day: this.$t('weather_status', 'Heavy rain showers'),
				heavyrainshowers_night: this.$t('weather_status', 'Heavy rain showers'),
			}
		},
		sixHoursTempForecast() {
			return this.forecasts.length > 5 ? this.forecasts[5].data.instant.details.air_temperature : ''
		},
		sixHoursWeatherForecast() {
			return this.forecasts.length > 5 ? this.forecasts[5].data.next_1_hours.summary.symbol_code : ''
		},
		weatherIcon() {
			if (this.loading) {
				return 'icon-loading-small'
			} else {
				return this.sixHoursWeatherForecast ? this.icons[this.sixHoursWeatherForecast] : 'icon-info'
			}
		},
		weatherText() {
			return this.sixHoursWeatherForecast ? this.texts[this.sixHoursWeatherForecast] : '???'
		},
		/**
		 * The message displayed in the top right corner
		 *
		 * @returns {String}
		 */
		visibleMessage() {
			return this.sixHoursWeatherForecast ? this.sixHoursTempForecast + '° ' + this.weatherText : ''
		},
	},
	mounted() {
		// bootstrap: try to get location from browser
		this.bootstrap()
	},
	methods: {
		async bootstrap() {
			try {
				const loc = await network.getLocation()
				this.lat = loc.lat
				this.lon = loc.lon
				this.address = loc.address
				this.mode = loc.mode

				console.debug('in bootstrap. mode=' + this.mode + ' latlng: ' + this.lat + ' ' + this.lon + ' address: ' + this.address)
				if (this.mode === MODE_BROWSER_LOCATION) {
					this.askBrowserLocation()
				} else if (this.mode === MODE_MANUAL_LOCATION) {
					this.startLoop()
				}
			} catch (err) {
				showError(this.$t('weather_status', 'There was an error getting the weather status information.'))
				console.debug(err)
			}
		},
		startLoop() {
			clearInterval(this.loop)
			console.debug('in start loop mode=' + this.mode + ' latlng: ' + this.lat + ' ' + this.lon + ' address: ' + this.address)
			if (this.lat && this.lon) {
				this.loop = setInterval(() => this.getForecast(), 60 * 1000 * 60)
				this.getForecast()
			}
		},
		askBrowserLocation() {
			this.loading = true
			if (navigator.geolocation && window.isSecureContext) {
				navigator.geolocation.getCurrentPosition((position) => {
					this.lat = position.coords.latitude
					this.lon = position.coords.longitude
					this.saveMode(MODE_BROWSER_LOCATION)
					this.mode = MODE_BROWSER_LOCATION
					this.saveLocation(this.lat, this.lon)
				},
				(error) => {
					console.debug('location permission refused')
					console.debug(error)
					this.saveMode(MODE_MANUAL_LOCATION)
					this.mode = MODE_MANUAL_LOCATION
					this.startLoop()
				})
			} else {
				console.debug('no secure context!')
				this.saveMode(MODE_MANUAL_LOCATION)
				this.mode = MODE_MANUAL_LOCATION
				this.startLoop()
			}
		},
		async getForecast() {
			try {
				this.forecasts = await network.fetchForecast()
			} catch (err) {
				showError(this.$t('weather_status', 'There was an error getting the forecasts.'))
				console.debug(err)
			}
			this.loading = false
		},
		async setAddress(address) {
			this.loading = true
			try {
				const loc = await network.setAddress(address)
				this.lat = loc.lat
				this.lon = loc.lon
				this.address = loc.address
				this.mode = MODE_MANUAL_LOCATION
				this.startLoop()
			} catch (err) {
				showError(this.$t('weather_status', 'There was an error setting the location address.'))
				console.debug(err)
				this.loading = false
			}
		},
		async saveLocation(lat, lon) {
			try {
				await network.setLocation(lat, lon)
				this.startLoop()
			} catch (err) {
				showError(this.$t('weather_status', 'There was an error setting the location.'))
				console.debug(err)
			}
		},
		async saveMode(mode) {
			try {
				await network.setMode(mode)
			} catch (err) {
				showError(this.$t('weather_status', 'There was an error saving the mode.'))
				console.debug(err)
			}
		},
		onCustomAddressClick() {
			this.setAddress('manuuuu')
		},
		onBrowserLocationClick() {
			this.askBrowserLocation()
		},
		onPersonalAddressClick() {
			console.debug('NYI')
		},
	},
}
</script>

<style lang="scss">
.icon-clearsky-day {
	background-image: url(./../img/sun.svg);
}
.icon-clearsky-night {
	background-image: url(./../img/moon.svg);
}
.icon-cloudy {
	background-image: url(./../img/cloud-cloud.svg);
}
.icon-fair-day {
	background-image: url(./../img/sun-cloud.svg);
}
.icon-fair-night {
	background-image: url(./../img/moon-cloud.svg);
}
.icon-partlycloudy-day {
	background-image: url(./../img/sun-small-cloud.svg);
}
.icon-partlycloudy-night {
	background-image: url(./../img/moon-small-cloud.svg);
}
.icon-fog {
	background-image: url(./../img/fog.svg);
}
.lightrain {
	background-image: url(./../img/light-rain.svg);
}
.icon-rain {
	background-image: url(./../img/heavy-rain.svg);
}
.icon-light-rainshowers-day {
	background-image: url(./../img/sun-cloud-light-rain.svg);
}
.icon-light-rainshowers-night {
	background-image: url(./../img/moon-cloud-light-rain.svg);
}
.icon-rainshowers-day {
	background-image: url(./../img/sun-cloud-rain.svg);
}
.icon-rainshowers-night {
	background-image: url(./../img/moon-cloud-rain.svg);
}
.icon-heavy-rainshowers-day {
	background-image: url(./../img/sun-cloud-heavy-rain.svg);
}
.icon-heavy-rainshowers-night {
	background-image: url(./../img/moon-cloud-heavy-rain.svg);
}
.icon-crosshair {
    background-color: var(--color-main-text);
    padding: 0 !important;
    mask: url(./../img/cross.svg) no-repeat;
    mask-size: 18px 18px;
    mask-position: center;
    -webkit-mask: url(./../img/cross.svg) no-repeat;
    -webkit-mask-size: 18px 18px;
    -webkit-mask-position: center;
    min-width: 44px !important;
    min-height: 44px !important;
}

li:not(.inline) #weather-status-menu-item {
	&__header {
		display: block;
		align-items: center;
		color: var(--color-main-text);
		padding: 10px 12px 5px 12px;
		box-sizing: border-box;
		opacity: 1;
		white-space: nowrap;
		width: 100%;
		text-align: center;
		max-width: 250px;
		text-overflow: ellipsis;
		min-width: 175px;
	}

	&__subheader {
		width: 100%;

		> button {
			background-color: var(--color-main-background);
			background-size: 16px;
			border: 0;
			border-radius: 0;
			font-weight: normal;
			font-size: 0.875em;
			padding-left: 40px;

			&:hover,
			&:focus {
				box-shadow: inset 4px 0 var(--color-primary-element);
			}
		}
	}
}

.inline #weather-status-menu-item__subheader {
	width: 100%;

	> button {
		background-color: var(--color-main-background);
		background-size: 16px;
		border: 0;
		border-radius: var(--border-radius-pill);
		font-weight: normal;
		font-size: 0.875em;
		padding-left: 40px;

		&:hover,
		&:focus {
			background-color: var(--color-background-hover);
		}

		&.icon-loading-small {
			&::after {
				left: 21px;
			}
		}
	}
}

	li {
		list-style-type: none;
	}
</style>
