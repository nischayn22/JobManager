<?php

use MediaWiki\Logger\LoggerFactory;

class SpecialJobManager extends SpecialPage {
	function __construct() {
		parent::__construct( 'JobManager', 'editinterface' );
	}

	function execute( $par ) {
		// get the output variable to use
		$output = $this->getOutput();

		// permissions check: if the user doesn't belong to the administrator user group, then prevent access to this page
		if( ! $this->checkPermissions() ) {
			global $wgUser;
			$output->addHTML('<h1>' . $this->msg('jobmanager-error-title') . '</h1>');
			$output->addHTML("<p>" . $this->msg($wgUser->isAnon() ? 'jobmanager-error-nologin' : 'jobmanager-error-docteam' ) . "</p>");
			return;
		}

		$output->addHTML('<h1>' . $this->msg('jobmanager') . '</h1>');
		$output->addHTML('<p>' . $this->msg('jobmanager-summary') . '</p>');

		//--- run jobs is specified in page parameters ---
		$request = $this->getRequest();
		if ( !empty( $request->getText( 'type' ) ) ) {
			$output->addHTML('<h2>' . $this->msg('jobmanager-processing') . '</h2>');

			$type = $request->getText( 'type' );
			if ($type=="all") {
				$type=false;
				$output->addHTML('<p>' . $this->msg('jobmanager-jobtype') . ': (' . $this->msg('jobmanager-processing-all') . ')</p>');
			}
			else {
				$output->addHTML('<p>' . $this->msg('jobmanager-jobtype') . ': ' . $type . '</p>');
			}

			$maxJobs = $request->getText( 'maxJobs' );
			if (!empty($maxJobs)) {
				if ($maxJobs<1) {
					$maxJobs=1;
					$output->addHTML('<p>' . $this->msg('jobmanager-processing-maxjob-lowerlimit') . '</p>');
				}
				elseif ($maxJobs>1000) {
					$maxJobs=1000;
					$output->addHTML('<p>' . $this->msg('jobmanager-processing-maxjob-upperlimit') . '</p>');
				}
				$output->addHTML('<p>' . $this->msg('jobmanager-processing-maxjob') . ': ' . $maxJobs . '</p>');
			}
//			$maxTime = $request->getText( 'maxTime' );
//			$throttle = $request->getText( 'throttle' );

			$this->runJobs($type, $maxJobs, false, true);
		}

		//--- create the job queue report ---
		$output->addHTML('<h2>' . $this->msg('jobmanager-reporting') . '</h2>');
		$jobs = JobQueueGroup::singleton()->getQueueSizes();
		// cycle through the array, printing a table that details how many jobs are waiting to be processed of each type
		$output->addHTML('<table><tr><th>' . $this->msg('jobmanager-jobtype') . '</th><th>' . $this->msg('jobmanager-jobcount') . '</th><th>' . $this->msg('jobmanager-action') . '</th></tr>');
		if ( count($jobs) > 0 ) {
			$jobcount = 0;
			$keys = array_keys( $jobs );
			foreach( $keys as $key ) {
				$output->addHTML('<tr><td>' . $key . '</td><td>' . $jobs[$key] . '</td><td>');
				$output->addWikiText("[" . $request->getFullRequestURL() . "?type=" . $key . "&maxJobs=100 Run " . $key . " jobs now!]");
				$output->addHTML('</td></tr>');	
				$jobcount += $jobs[$key];
			}
			$output->addHTML('<tr><th>' . $this->msg('jobmanager-totalcount') . '</th><td>' . $jobcount . '</td><td>');
			$output->addWikiText("[" . $request->getFullRequestURL() . "?type=all&maxJobs=100 Run all jobs now!]");
			$output->addHTML('</td></tr>');
		}
		else {
			// no jobs in the queue
			$output->addHTML('<tr><td colspan=3><i>' . $this->msg('jobmanager-nojobs') . '</i></td></tr>');
		}
		$output->addHTML('</table>');
	}


/*
runJobs.php
(source: https://www.mediawiki.org/wiki/Manual:RunJobs.php)
	--maxjobs	Maximum number of jobs to run (default is 10,000)
	--maxtime	Maximum amount of wall-clock time (in seconds)
	--procs	Number of processes to use
	--type	Type of job to run. See $wgJobClasses for available job types.
	--wait	Wait for new jobs instead of exiting
	--nothrottle	Ignore the job throttling configuration

	Example: php maintenance/runJobs.php --maxjobs 5 --type refreshLinks

PHP Reference
	https://www.php.net/manual/en/function.exec.php
	exec ( string $command [, array &$output [, int &$return_var ]] ) : string
*/

	function runJobs($type=false, $maxJobs=false, $maxTime=false, $throttle=true) {
		$runner = new JobRunner( LoggerFactory::getInstance( 'runJobs' ) );
		$runner->setDebugHandler( [ $this, 'debugInternal' ] );
		while ( true ) {
			$response = $runner->run( [
				'type'     => $type,
				'maxJobs'  => $maxJobs,
				'maxTime'  => $maxTime,
				'throttle' => $throttle,
			] );

			if (
				$response['reached'] === 'time-limit' ||
				$response['reached'] === 'job-limit' ||
				$response['reached'] === 'memory-limit'
			) {
				break;
			}

			if ( $maxJobs !== false ) {
				$maxJobs -= count( $response['jobs'] );
			}

			sleep( 10 );
		}
		return true;
	}

	function checkPermissions($permissionGroup = 'bureaucrat') {
		global $wgUser;
		return in_array( $permissionGroup, $wgUser->getEffectiveGroups() );
	}

	/**
	 * @param string $s
	 */
	public function debugInternal( $s ) {
//		TODO: Add proper logging for jobs that were run...
//		$this->getOutput()->addHTML( $s );
	}

}
