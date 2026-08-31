<?php
declare(strict_types=1);

namespace Kevira\MailGateway;

use Kevira\MailGateway\Admin\Actions;
use Kevira\MailGateway\Admin\Menu;
use Kevira\MailGateway\Admin\Page;
use Kevira\MailGateway\Cli\Commands;
use Kevira\MailGateway\Cli\QueueCommands;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Gateway\ResponseClassifier;
use Kevira\MailGateway\Gateway\Signer;
use Kevira\MailGateway\Gateway\WordPressHttp;
use Kevira\MailGateway\Health\SiteHealth;
use Kevira\MailGateway\Mail\AttachmentLoader;
use Kevira\MailGateway\Mail\HeaderNormalizer;
use Kevira\MailGateway\Mail\Interceptor;
use Kevira\MailGateway\Mail\MessageFactory;
use Kevira\MailGateway\Outbox\Backoff;
use Kevira\MailGateway\Outbox\Encryptor;
use Kevira\MailGateway\Outbox\Lock;
use Kevira\MailGateway\Outbox\Repository;
use Kevira\MailGateway\Outbox\Worker;
use Kevira\MailGateway\Scheduling\WordPressScheduler;
use Kevira\MailGateway\Support\SecureRandom;
use Kevira\MailGateway\Support\SystemClock;

final class Plugin {
	public function register(): void {
		$config      = Config::fromEnvironment();
		$random      = new SecureRandom();
		$client      = new Client( $config, new WordPressHttp(), new Signer( $config, new SystemClock(), $random ), new ResponseClassifier() );
		$repository  = new Repository();
		$scheduler   = new WordPressScheduler();
		$factory     = new MessageFactory( $config, new HeaderNormalizer(), new AttachmentLoader() );
		$interceptor = new Interceptor( $config, $factory, $client, $repository, $scheduler, $random );
		add_filter( 'pre_wp_mail', array( $interceptor, 'intercept' ), 10, 2 );

		$page    = new Page( $config, $client, $repository );
		$menu    = new Menu( $page );
		$actions = new Actions( $repository, $scheduler );
		add_action( 'admin_menu', array( $menu, 'register' ), 40 );
		add_action( 'admin_enqueue_scripts', array( $page, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KEVIRA_MAIL_GATEWAY_FILE ), array( $menu, 'actionLinks' ) );
		add_action( 'admin_post_kevira_mail_gateway_test', array( $actions, 'test' ) );
		add_action( 'admin_post_kevira_mail_gateway_retry', array( $actions, 'retry' ) );
		add_action( 'admin_post_kevira_mail_gateway_refresh', array( $actions, 'refresh' ) );

		$workerFactory = function () use ( $config, $client, $repository, $scheduler ): ?Worker {
			try {
				return new Worker( $repository, new Encryptor( $config->secret() ), $client, new Lock(), new Backoff(), $scheduler );
			} catch ( \Throwable ) {
				return null;
			}
		};
		add_action(
			WordPressScheduler::HOOK,
			static function () use ( $workerFactory ): void {
				$worker = $workerFactory();
				if ( $worker ) {
					$worker->run();
				}
			}
		);

		$siteHealth = new SiteHealth( $config, $client, $repository );
		add_filter( 'site_status_tests', array( $siteHealth, 'register' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$worker = $workerFactory();
			if ( $worker ) {
				\WP_CLI::add_command( 'kevira-mail', new Commands( $config, $client, $repository, $worker ) );
				\WP_CLI::add_command( 'kevira-mail queue', new QueueCommands( $repository, $scheduler ) );
			}
		}
	}
}
