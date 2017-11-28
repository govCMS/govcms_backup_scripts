<?php
namespace Backup\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class BackUpCommand extends Command
{
    protected function configure()
    {
        $this->setName('backup')
            ->setDescription('Downloads and stores the latest backups from govCMS SaaS')
            ->addOption(
                'api-username',
                null,
                InputOption::VALUE_REQUIRED,
                'The ACSF API username'
            )
            ->addOption(
                'api-key',
                null,
                InputOption::VALUE_REQUIRED,
                'The ACSF API key'
            )
            ->addOption(
                'destination',
                null,
                InputOption::VALUE_REQUIRED,
                'The Destination for backup files'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $FULL_DAY_TO_RUN = 'Sunday';
        $day_of_week = date('l');
        $destination = $input->getOption('destination');
        print "Running govCMS Backups for ".$day_of_week.".";

        $client = new Client([
            'base_uri' => 'https://www.govcms.acsitefactory.com/api/v1/',
            'auth' => [$input->getOption('api-username'), $input->getOption('api-key')],
        ]);

        var_dump(json_decode($client->request('GET', 'sites/126/backups/3326/url')->getBody()));
        exit;

        $result_size = 1;
        $temp_site_list = array();
        $page = 1;
        while($result_size > 0) {
            $result = json_decode($client->request('GET', 'sites', array('query' => array('page' => $page, 'limit' => 100)))->getBody());
            $result_size = sizeof($result->sites);
            $temp_site_list = array_merge($temp_site_list, $result->sites);
            $page++;
        }

        print "\nFound ".sizeof($temp_site_list)." sites.\n";
        $site_list = array();
        foreach ($temp_site_list as $key => $value) {
            $result = json_decode($client->request('GET', 'sites/'.$value->id)->getBody());
            if($day_of_week == $FULL_DAY_TO_RUN) {
                $site_list[] = $result;
            } else if(isset($result->is_primary) && $result->is_primary) {
                $site_list[] = $result;
            }
        }
        print "Using ".sizeof($site_list)." sites.\n";

        foreach($site_list as $site) {
            print "\n***************************\n";
            print "Starting Backup of ".$site->site."\n";
            $result = json_decode($client->request('POST', 'sites/'.$site->id.'/backup', array(RequestOptions::JSON => array('components' => array('codebase', 'themes', 'database'))))->getBody());
            $task_id = $result->task_id;
            $running = true;
            $task_exists = true;
            while($running && $task_exists) {
                $task_exists = false;
                print "\nChecking task for completion";
                $task_result = json_decode($client->request('GET', 'tasks')->getBody());
                foreach($task_result as $task) {
                    if($task_id == $task->id) {
                        $task_exists = true;
                        if(!empty($task->error_message) || $task->completed != "0") {
                            //Job's finished
                            print "\nBackup Async Job Completed";
                            $running = false;
                            //Need to get archive url to download.
                            $list_backups = json_decode($client->request('GET', 'sites/'.$site->id.'/backups')->getBody());

                            if(sizeof($list_backups->backups) == 0) {
                                print "\nNo Backups exist";
                                continue;
                            }
                            $the_backup = $list_backups->backups[0];
                            $backup_url = json_decode($client->request('GET', 'sites/'.$site->id.'/backups/'.$the_backup->id.'/url')->getBody());

                            $url = $backup_url->url;
                            print "\nFetching archive of ".$site->site." from ".$url." saving in ".$destination;
                            exec("wget -b -P ".$destination." -O ".$the_backup->file." '$url'");
                        }
                    }
                }
                sleep(30);
            }
            break;
        }

    }

    function endsWith($haystack, $needle) {
        $length = strlen($needle);

        return $length === 0 ||
            (substr($haystack, -$length) === $needle);
    }
}

?>
