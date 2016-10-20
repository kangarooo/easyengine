<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


/**
 * Manage sites.
 *
 * ## EXAMPLES
 *
 *     # Create site
 *     $ ee site create example.com
 *     Success: Created example.com site.
 *
 *     # Update site
 *     $ ee site update example.com
 *     Success: Updated example.com site.
 *
 *     # Delete site
 *     $ ee site delete example.com
 *     Success: Deleted example.com site.
 *
 * @package easyengine
 */
class Stack_Command extends EE_Command {

	/**
	 * Create site.
	 *
	 * ## OPTIONS
	 *
	 *
	 * [--web]
	 * : To install web.
	 *
	 * [--nginx]
	 * : To install nginx.
	 *
	 * [--php]
	 * : To install nginx.
	 *
	 * ## EXAMPLES
	 *
	 *      # Create site.
	 *      $ ee site create example.com
	 *
	 */
	public function install( $args, $assoc_args ) {

		list( $site_name ) = $args;
		$apt_packages = array();
		$packages = array();

		if (!empty($assoc_args['no_diplay_message'])){
			$disp_msg = false;
		}else{
			$disp_msg = true;
		}

		if( !empty( $assoc_args['pagespeed'] ) ) {
			EE_CLI::error( $site_name . 'Pagespeed support has been dropped since EasyEngine v3.6.0' );
			EE_CLI::error( $site_name . 'Please run command again without `--pagespeed`' );
			EE_CLI::error( $site_name . 'For more details, read - https://easyengine.io/blog/disabling-pagespeed/' );
		}

	if(!empty( $assoc_args['all'] )){
		$category['web'] = True;
		$category['admin'] = True;
	}
	$stack = array();
	if ($category['web'] == true){
			$stack['nginx']= true;
			$stack['php']= true;
			$stack['mysql']= true;
			$stack['wpcli']= true;
			$stack['postfix']= true;
	}
	if ($category['admin'] == true){
		$stack['nginx']= true;
		$stack['php']= true;
		$stack['mysql']= true;
		$stack['adminer']= true;
		$stack['phpmyadmin']= true;
		$category['utils']= true;
	}

	// if ($category['mail'] == true){
	// todo:
	// }

	if (!empty($stack['redis'])){
		if(!EE_Apt_Get::is_installed('redis-server')){

			$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('redis'));
	}
	}else{
			EE::success("Redis already installed");
		}


	if (!empty($stack['nginx'])){
		EE::debug("Setting apt_packages variable for Nginx");
		if(!EE_Apt_Get::is_installed('nginx-custom')){
			if(!(EE_Apt_Get::is_installed('nginx-plus')||EE_Apt_Get::is_installed('nginx'))){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('nginx'));
			}else{
					if(EE_Apt_Get::is_installed('nginx-plus')){
						EE::success("NGINX PLUS Detected ...");
						$apt[]="nginx-plus";
						$apt=array_merge($apt,EE_Variables::get_package_list('nginx'));
						self::post_pref($apt, $packages);
					}elseif(EE_Apt_Get::is_installed('nginx')){
						EE:success("EasyEngine detected a previously installed Nginx package. ".
						"It may or may not have required modules. ".
						"\nIf you need help, please create an issue at https://github.com/EasyEngine/easyengine/issues/ \n");
						$apt[]="nginx";
						$apt=array_merge($apt,EE_Variables::get_package_list('nginx'));
						self::post_pref($apt, $packages);
					}
			}
		}else{
			EE::debug("Nginx Stable already installed");
		}
	}
	if (!empty($stack['php'])){
		EE::debug("Setting apt_packages variable for PHP");
		if(!(EE_Apt_Get::is_installed('php5-fpm')||EE_Apt_Get::is_installed('php5.6-fpm'))){
			if(EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
			}else{
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php'));
			}
		}else{
			EE::success("PHP already installed");
		}
	}

	if (!empty($stack['php'] && EE_OS::ee_platform_distro() == 'debian')){
		if (EE_OS::ee_platform_codename() == 'jessie'){
			EE::debug("Setting apt_packages variable for PHP 7.0");
			if(!EE_Apt_Get::is_installed('php7.0-fpm')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
				if(!EE_Apt_Get::is_installed('php5-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php'));
				}
			}else{
				EE::success("PHP 7.0 already installed");
			}
		}
	}


	if (!empty($stack['php'] && !EE_OS::ee_platform_codename() == 'debian')){
		if (EE_OS::ee_platform_codename() == 'trusty'||EE_OS::ee_platform_codename() == 'xenial'){
			EE::debug("Setting apt_packages variable for PHP 7.0");
			if(!EE_Apt_Get::is_installed('php7.0-fpm')){
				$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php7.0'));
				if(!EE_Apt_Get::is_installed('php5.6-fpm')){
					$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('php5.6'),EE_Variables::get_package_list('phpextra'));
				}
			}else{
				EE::success("PHP 7.0 already installed");
			}
		}
	}

	if (!empty($stack['mysql'])){
		EE::debug("Setting apt_packages variable for MySQL");
		if (!EE::exec_cmd_output("mysqladmin ping", $message = 'Looking for active mysql connection', $exit_on_error = false)){
			$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('mysql'));
			$packages = array_merge($packages, array("mysqltunner"));
		}else{
			EE::success("MySQL connection is already alive");
		}
	}


	if (!empty($stack['postfix'])){
		EE::debug("Setting apt_packages variable for Postfix");
		if(!EE_Apt_Get::is_installed('postfix')){
			$apt_packages = array_merge($apt_packages,EE_Variables::get_package_list('postfix'));
		}else{
			EE::success("Postfix is already installed");
		}
	}

	if (!empty($stack['wpcli'])){
		EE::debug("Setting packages variable for WP-CLI");
		if (!EE::exec_cmd_output("which wp", $message = 'Looking wp-cli preinstalled', $exit_on_error = false)){
			$packages = array_merge($packages, array("wpcli"));
		}
	else{
			EE::success("WP-CLI is already installed");
		}
	}

	if (!empty($stack['phpmyadmin'])){
		EE::debug("Setting packages variable for phpMyAdmin");
			$packages = array_merge($packages, array("phpmyadmin"));
	}

	if (!empty($stack['phpredisadmin'])){
		EE::debug("Setting packages variable for phpRedisAdmin");
			$packages = array_merge($packages, array("phpredisadmin"));
	}

	if (!empty($stack['adminer'])){
		EE::debug("Setting packages variable for Adminer");
			$packages = array_merge($packages, array("adminer"));
	}

	if (!empty($category['utils'])){
		EE::debug("Setting packages variable for utils");
			$packages = array_merge($packages, array("phpmemcacheadmin","opcache","rtcache-clean", "opcache-gui","ocp","webgrind","perconna-toolkit","anemometer"));
	}

	if(!empty($apt_packages)||!empty($packages)){
		EE::debug("Calling pre_pref");
		self::pre_pref($apt_packages);
		if(!empty($apt_packages)){
			EE_OS::add_swap();
			EE::success("Updating apt-cache, please wait...");
			EE_Apt_Get::update();
			EE_Apt_Get::install($apt_packages);
		}
		if(!empty($packages)){
			EE::debug("Downloading following: " .implode(' ',$packages));
			EE_Utils::download($packages);
		}
		EE::debug("Calling post_pref");
		self::post_pref($apt_packages, $packages);
		if(in_array('redis-server',$apt_packages)){
			if (is_file("/etc/redis/redis.conf")){
				$system_mem_info = EE_OS::get_system_mem_info();
				if($system_mem_info['MemTotal'] < 512){
					EE::debug("Setting maxmemory variable to" . (int)$system_mem_info['MemTotal']*1024*1024*0.1. " in redis.conf");
					EE::exec_cmd_output("sed -i s/# maxmemory/maxmemory " . (int)$system_mem_info['MemTotal']*(1024*1024*0.1) ."/' /etc/redis/redis.conf", $message = '', $exit_on_error = false);
					EE::exec_cmd_output("sed -i 's/# maxmemory-policy.*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf", $message = 'Setting maxmemory-policy variable to allkeys-lru in redis.conf', $exit_on_error = false);
					EE_Service::restart_service( 'redis-server' );
				}
				else{
					EE::debug("Setting maxmemory variable to" . (int)$system_mem_info['MemTotal']*1024*1024*0.2 . " in redis.conf");
					EE::exec_cmd_output("sed -i s/# maxmemory/maxmemory " . (int)$system_mem_info['MemTotal']*(1024*1024*0.2) ."/' /etc/redis/redis.conf", $message = '', $exit_on_error = false);
					EE::exec_cmd_output("sed -i 's/# maxmemory-policy.*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf", $message = 'Setting maxmemory-policy variable to allkeys-lru in redis.conf', $exit_on_error = false);
					EE_Service::restart_service( 'redis-server' );
				}
			}
		}

		if ($disp_msg){
			EE::success("Successfully installed packages");
		}

	}

	}


	public static function pre_pref($apt_packages){


		// Pre settings to do before installation packages

		if (in_array(EE_Variables::get_package_list('postfix'), $apt_packages) ) {
			echo "Pre-seeding Postfix";
			try{

				EE::exec_cmd_output('echo "postfix postfix/main_mailer_type string \'Internet Site\'" | debconf-set-selections', $message = '', $exit_on_error = false);
				EE::exec_cmd_output('echo "postfix postfix/mailname string $(hostname -f)" | debconf-set-selections', $message = '', $exit_on_error = false);
			}catch(Exception $e){
				echo "Failed to intialize postfix package";
			}
		}

		if (in_array(EE_Variables::get_package_list('mysql'), $apt_packages) ) {
			EE::log("Adding repository for MySQL, please wait...");
			$mysql_pref = "Package: *\nPin: origin sfo1.mirrors.digitalocean.com\nPin-Priority: 1000\n";
			$mysql_pref_file   = fopen("/etc/apt/preferences.d/MariaDB.pref", "w" );
			fwrite( $mysql_pref_file, $mysql_pref );

			EE_Repo::add(EE_Variables::get_mysql_repo());

				EE::debug("Adding key for Mysql");
				if (EE_OS::ee_platform_codename() != 'xenial'){
					EE_Repo::add_key('0xcbcb082a1bb943db',"keyserver.ubuntu.com");

				}else {
					EE_Repo::add_key('0xF1656F24C74CD1D8',"keyserver.ubuntu.com");
				}

			$char = EE_Utils::random_string(8);
			EE::debug("Pre-seeding MySQL");
			EE::debug("echo \"mariadb-server-10.1 mysql-server/root_password " .
				"password \" | debconf-set-selections");
			$reset_pwd = 'echo "mariadb-server-10.1" "mysql-server/root_password_again" password ' . $char .' |  "debconf-set-selections" ';
			EE::exec_cmd($reset_pwd);
			$mysql_config = '[client]\n user = root\n password = '. $char;
            EE::debug('Writting configuration into MySQL file');
            $conf_path = "/etc/mysql/conf.d/my.cnf";
		    ee_file_dump($conf_path,$mysql_config);
			EE::debug("Setting my.cnf permission");
		    ee_file_chmod("/etc/mysql/conf.d/my.cnf",0600);

		}

		if ( in_array(EE_Variables::get_package_list('nginx'), $apt_packages)) {
			EE::log("Adding repository for NGINX, please wait...");
			EE_Repo::add(EE_Variables::get_nginx_repo());
			EE_Repo::add_key('3050AC3CD2AE6F03');
		}

		if (in_array(EE_Variables::get_package_list('php7.0'), $apt_packages) || in_array(EE_Variables::get_package_list('php5.6'), $apt_packages)) {
				EE::log("Adding repository for PHP, please wait...");
			    EE_Repo::add(EE_Variables::get_php_repo());
			if ('debian' == EE_OS::ee_platform_distro()){
				EE_Repo::add_key('89DF5277');
				}
		}
		if ( in_array(EE_Variables::get_package_list('redis'), $apt_packages)) {
			EE::log("Adding repository for REDIS, please wait...");
			EE_Repo::add(EE_Variables::get_redis_repo());
			if ('debian' == EE_OS::ee_platform_distro()){
				EE_Repo::add_key('3050AC3CD2AE6F03');
			}
		}
		
	}



}

EE::add_command( 'stack', 'Stack_Command' );
