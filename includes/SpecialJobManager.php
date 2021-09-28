<?php

use MediaWiki\Logger\LoggerFactory;

class SpecialJobManager extends SpecialPage {
	function __construct() {
		parent::__construct( 'JobManager', 'editinterface' );
	}

	function execute( $par ) {
		global $wgUser;
		$this->checkPermissions();

		$output = $this->getOutput();
		$request = $this->getRequest();

		$output->addHTML('<h1>' . $this->msg('jobmanager') . '</h1>');
		$output->addHTML('<p>' . $this->msg('jobmanager-summary') . '</p>');

		$request_type = $request->getText( 'type' );

		if ( !empty( $request_type ) ) {
			$output->addHTML('<h2>' . $this->msg('jobmanager-processing') . '</h2>');

			if ( $request_type == "all" ) {
				$request_type = false;
				$output->addHTML('<p>' . $this->msg('jobmanager-jobtype') . ': (' . $this->msg('jobmanager-processing-all') . ')</p>');
			} else {
				$output->addHTML('<p>' . $this->msg('jobmanager-jobtype') . ': ' . $request_type . '</p>');
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

			$this->runJobs($request_type, $maxJobs, false, true);
		}

		//--- create the job queue report ---
		$output->addHTML('<h2>' . $this->msg('jobmanager-reporting') . '</h2>');
		// cycle through the array, printing a table that details how many jobs are waiting to be processed of each type
		$output->addHTML('<table><tr><th>' . $this->msg('jobmanager-jobtype') . '</th><th>' . $this->msg('jobmanager-jobcount') . '</th><th>' . $this->msg('jobmanager-action') . '</th></tr>');

		$jobs = JobQueueGroup::singleton()->getQueueSizes();
		if ( !empty( $jobs ) ) {
			$jobcount = 0;
			$job_keys = array_keys( $jobs );
			foreach( $job_keys as $job_key ) {
				$output->addHTML('<tr><td>' . $job_key . '</td><td>' . $jobs[$job_key] . '</td><td>');
				$output->addWikiText("[" . $request->getFullRequestURL() . "?type=" . $job_key . "&maxJobs=100 Run " . $job_key . " jobs now!]");
				$output->addHTML('</td></tr>');	
				$jobcount += $jobs[$job_key];
			}
			$output->addHTML('<tr><th>' . $this->msg('jobmanager-totalcount') . '</th><td>' . $jobcount . '</td><td>');
			$output->addHTML( Linker::link( Title::newFromText( "Special:JobManager" ), "Run all jobs now!", [], ["type" => "all", "maxJobs" => 100] ) );
			$output->addHTML('</td></tr>');
		} else {
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

	/**
	 * @param string $s
	 */
	public function debugInternal( $s ) {
//		TODO: Add proper logging for jobs that were run...
//		$this->getOutput()->addHTML( $s );
	}

}
