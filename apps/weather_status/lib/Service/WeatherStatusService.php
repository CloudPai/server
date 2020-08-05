<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\WeatherStatus\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Http\Client\IClientService;

/**
 * Class StatusService
 *
 * @package OCA\WeatherStatus\Service
 */
class WeatherStatusService {


	/**
	 * StatusService constructor.
	 *
	 * @param ITimeFactory $timeFactory
	 * @param PredefinedStatusService $defaultStatusService,
	 */
	public function __construct(ITimeFactory $timeFactory,
								IClientService $clientService,
								IConfig $config,
								IL10N $l10n,
								string $userId) {
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->clientService = $clientService;
        $this->client = $clientService->newClient();
	}

	public function setMode(int $mode): array {
		$this->config->setUserValue($this->userId, 'weather_status', 'mode', $mode);
		return ['success' => true];
	}

	/**
	 */
	public function setLocation(string $address = '', $lat = null, $lon = null): array {
		if ($lat !== null and $lon !== null) {
			$this->config->setUserValue($this->userId, 'weather_status', 'lat', $lat);
			$this->config->setUserValue($this->userId, 'weather_status', 'lon', $lon);
			$address = $this->resolveLocation($lat, $lon);
			$address = $address ? $address : $this->l10n->t('Unknown address');
			$this->config->setUserValue($this->userId, 'weather_status', 'address', $address);
			return [
				'address' => $address,
			];
		} else if ($address !== '') {
			return $this->setAddress($address);
		} else {
			return ['success' => false];
		}
	}

	private function resolveLocation($lat, $lon) {
		$params = [
			'lat' => $lat,
			'lon' => $lon,
			'addressdetails' => 1,
			'format' => 'json',
		];
		$url = 'https://nominatim.openstreetmap.org/reverse';
		$result = $this->requestJSON($url, $params);
		if (isset($result['display_name'])) {
			return $result['display_name'];
		}
		return null;
	}

	public function setAddress(string $address): array {
		$addressInfo = $this->searchForAddress($address);
		if (isset($addressInfo['display_name']) and isset($addressInfo['lat']) and isset($addressInfo['lon'])) {
			$this->config->setUserValue($this->userId, 'weather_status', 'address', $addressInfo['display_name']);
			$this->config->setUserValue($this->userId, 'weather_status', 'lat', $addressInfo['lat']);
			$this->config->setUserValue($this->userId, 'weather_status', 'lon', $addressInfo['lon']);
			$this->config->setUserValue($this->userId, 'weather_status', 'mode', 2);
			return [
				'lat' => $addressInfo['lat'],
				'lon' => $addressInfo['lon'],
				'address' => $addressInfo['display_name'],
			];
		} else {
			return ['success' => false];
		}
	}

	private function searchForAddress(string $address): array {
		$params = [
			'format' => 'json',
			'addressdetails' => '1',
			'extratags' => '1',
			'namedetails' => '1',
			'limit' => '1',
		];
		$url = 'https://nominatim.openstreetmap.org/search/' . $address;
		$results = $this->requestJSON($url, $params);
		if (is_array($results) and count($results) > 0) {
			return $results[0];
		}
		return ['error' => $this->l10n->t('No result.')];
	}

	public function getLocation(): array {
		$lat = $this->config->getUserValue($this->userId, 'weather_status', 'lat', '');
		$lon = $this->config->getUserValue($this->userId, 'weather_status', 'lon', '');
		$address = $this->config->getUserValue($this->userId, 'weather_status', 'address', '');
		$mode = $this->config->getUserValue($this->userId, 'weather_status', 'mode', 1);
		return [
			'lat' => $lat,
			'lon' => $lon,
			'address' => $address,
			'mode' => intval($mode),
		];
	}

	public function getForecast(): array {
		$lat = $this->config->getUserValue($this->userId, 'weather_status', 'lat', '');
		$lon = $this->config->getUserValue($this->userId, 'weather_status', 'lon', '');
		if (is_numeric($lat) and is_numeric($lon)) {
			return $this->forecastRequest(floatval($lat), floatval($lon));
		} else {
			return ['success' => false];
		}
	}

	private function forecastRequest(float $lat, float $lon, int $nbValues = 10): array {
		$params = [
			'lat' => $lat,
			'lon' => $lon,
		];
		$url = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';
		$weather = $this->requestJSON($url, $params);
		if (isset($weather['properties']) and isset($weather['properties']['timeseries']) and is_array($weather['properties']['timeseries'])) {
			return array_slice($weather['properties']['timeseries'], 0, $nbValues);
		}
		return ['error' => $this->l10n->t('Malformed JSON data.')];
	}

	private function requestJSON($url, $params = []) {
		try {
            $options = [
                'headers' => [
                    'User-Agent' => 'Nextcloud weather status'
                ],
            ];

            if (count($params) > 0) {
				$paramsContent = http_build_query($params);
				$url .= '?' . $paramsContent;
            }

			$response = $this->client->get($url, $options);
            $body = $response->getBody();
            $respCode = $response->getStatusCode();

            if ($respCode >= 400) {
                return ['error' => $this->l10n->t('Error')];
            } else {
				return json_decode($body, true);
            }
        } catch (\Exception $e) {
            $this->logger->warning($url . 'API error : ' . $e, array('app' => $this->appName));
            $response = $e->getResponse();
			$headers = $response->getHeaders();
			return ['error' => $e];
		}
	}

}
