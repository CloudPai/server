<?php
	\OCP\Util::addScript('dashboard', 'dashboard');
	if (\OC::$server->getAppManager()->isEnabledForUser('weather_status')) {
		\OCP\Util::addScript('weather_status', 'weather-status');
	}
?>
<div id="app"></div>
