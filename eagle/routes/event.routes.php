<?php

require_once './eagle/utils/Downloader.php';
require_once './eagle/utils/FileReader.php';
require_once './eagle/utils/Auth.php';
require_once './eagle/utils/Utils.php';

$app->group('/event', function() {

	$this->get('/{url:.*\/}', function($req, $res, $args) {
		header('Location:/event/' . trim($args['url'], '/'));
		exit();
	});

	$this->get('/all', function($req, $res, $args) {
		$events = FileReader::getAllEvents();
		if (!$events)
		{
			Downloader::getAllEvents();
			header('Refresh:0');
			exit();
		}

		foreach ($events as $event) {
			if (strtotime($event->end_date) < time())
			{
				$event->hasOccurred = 'past';
			}
			else if (Utils::isEventOccuring($event))
			{
				$event->hasOccurred = 'current';
			}
		}

		$this->view->render($res, 'eventDirectory.html', [
			'title' => 'Event Directory',
			'events' => $events,
			'user'  => Auth::getLoggedInUser()
		]);
	});

	$this->get('/{event:\d{4}[A-Za-z]{2,5}\d?}', function($req, $res, $args) {
		Auth::redirectIfNotLoggedIn();


		$event = FileReader::getEvent($args['event']);
		if (!$event)
		{
			Downloader::getEvent($args['event']);
			header('Refresh:0');
			exit();
		}

		$teams = FileReader::getTeamsAtEvent($args['event']);

		if (!count($teams))
		{
			Downloader::getTeamsAtEvent($args['event']);
			header('Refresh:0');
			exit();
		}
		// This method name doesn't actually make sense, but whatever
	    if (Utils::isAfterNow(strtotime($event->start_date))) 
	    {
	    	$matches = FileReader::getMatchesAtEvent($args['event'], Utils::isEventOccuring($event));

	        $types = array('f' => 'Finals', 'sf' => 'Semifinals', 'qf' => 'Quarter Final', 'qm' => 'Qualifier');

	        foreach ($matches as $match) 
	        {
				$match->match_type = $types[$match->comp_level];
	        }

	        $rankings = FileReader::getRankingsAtEvent($args['event'], Utils::isEventOccuring($event));

	        $dlstats = FileReader::getStatsAtEvent($args['event'], Utils::isEventOccuring($event));

	        $stats = array();

            $awards = FileReader::getAwardsAtEvent($event->key, Utils::isEventOccuring($event));

	        if (!Utils::isEventOccuring($event))
	        {
	        	if (!$matches)
		        {
					Downloader::getMatchesAtEvent($args['event']);
	                header('Refresh:0');
	                exit();
		        }

    	        if (!$dlstats)
		        {
		            Downloader::getStatsAtEvent($args['event']);
		            header('Refresh:0');
		            exit();
		        }

		        for ($i = 0; $i < count((array)$dlstats->oprs); $i++)
		        {
					$tempTeam = array_keys((array)$dlstats->oprs)[$i];
					$opr = $dlstats->oprs->{$tempTeam};
					$dpr = $dlstats->dprs->{$tempTeam};
					$ccwm = $dlstats->ccwms->{$tempTeam};
					$stats[$tempTeam] = array('number' => $tempTeam, 'opr' => $opr, 'dpr' => $dpr, 'ccwm' => $ccwm);
	            }

		        if (!$rankings)
		        {
					Downloader::getRankingsAtEvent($args['event']);
					header('Refresh:0');
					exit();
		        }

				if (!$awards)
		        {
					Downloader::getAwardsAtEvent($args['event']);
					header('Refresh:0');
					exit();
		        }
	        }
        }
        else
        {
        	$stats = array();
        	$rankings = array();
        	$matches = array();
        	$awards = array();
        }

		$this->view->render($res, 'event.html', [
			'title' => $event->name,
			'event' => $event,
			'teams' => $teams,
			'stats' => $stats,
			'awards' => $awards,
			'rankings' => $rankings,
			'matches' => $matches,
			'user'  => Auth::getLoggedInUser()
		]);
	});

	$this->get('/{event:\d{4}[A-Za-z]{2,5}\d?}/update', function($req, $res, $args) {
		Auth::redirectIfNotLoggedIn();
		FileReader::getEvent($args['event'], true);
		FileReader::getTeamsAtEvent($args['event'], true);
		FileReader::getMatchesAtEvent($args['event'], true);
		FileReader::getStatsAtEvent($args['event'], true);
		FileReader::getRankingsAtEvent($args['event'], true);
		FileReader::getAwardsAtEvent($args['event'], true);
		return $res->withStatus(302)->withHeader('Location', '/event/' . $args['event']);
	});

});