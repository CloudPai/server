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
								string $userId) {
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->userId = $userId;
		$this->clientService = $clientService;
        $this->client = $clientService->newClient();
	}

	/**
	 */
	public function setLocation(string $address = '', $lat = null, $lon = null): array {
		if ($lat !== null and $lon !== null) {
			$this->config->setUserValue($this->userId, 'weather_status', 'lat', $lat);
			$this->config->setUserValue($this->userId, 'weather_status', 'lon', $lon);
			error_log($this->userId.' => '.$lat.'||'.$lon);
		} else if ($address !== '') {
			$this->setAddress($address);
		} else {
			return ['success' => false];
		}
		return ['success' => true];
	}

	public function getLocation(): array {
		$lat = $this->config->getUserValue($this->userId, 'weather_status', 'lat', '');
		$lon = $this->config->getUserValue($this->userId, 'weather_status', 'lon', '');
		$address = $this->config->getUserValue($this->userId, 'weather_status', 'address', '');
		return [
			'lat' => $lat,
			'lon' => $lon,
			'address' => $address,
		];
	}

	public function getForecast(): array {
		$lat = $this->config->getUserValue($this->userId, 'weather_status', 'lat', '');
		$lon = $this->config->getUserValue($this->userId, 'weather_status', 'lon', '');
		if (is_numeric($lat) and is_numeric($lon)) {
			return $this->request(floatval($lat), floatval($lon));
		} else {
			return ['success' => false];
		}
	}

	private function request(float $lat, float $lon, int $nbValues = 10): array {
		$params = [
			'lat' => $lat,
			'lon' => $lon,
		];
		try {
            $url = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';
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
				$weather = json_decode($body, true);
				if (isset($weather['properties']) and isset($weather['properties']['timeseries']) and is_array($weather['properties']['timeseries'])) {
					return array_slice($weather['properties']['timeseries'], 0, $nbValues);
				}
                return ['error' => $this->l10n->t('Malformed JSON data.')];
            }
        } catch (\Exception $e) {
            $this->logger->warning('Reddit API error : '.$e, array('app' => $this->appName));
            $response = $e->getResponse();
			$headers = $response->getHeaders();
			return ['error' => $e];
		}
	}

}
