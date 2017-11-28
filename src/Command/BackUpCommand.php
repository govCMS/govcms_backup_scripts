<?php
namespace Backup\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use GuzzleHttp\Client;

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
        $day_of_week = date('l');
        print "Running govCMS Backups for ".$day_of_week.".";

        $client = new Client([
            'base_uri' => 'https://www.govcms.acsitefactory.com/api/v1/',
            'auth' => [$input->getOption('api-username'), $input->getOption('api-key')],
        ]);
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
            if($day_of_week == 'Sunday') {
                $site_list[] = $result;
            } else if(isset($result->is_primary) && $result->is_primary) {
                $site_list[] = $result;
            }
        }
        print "\nUsing ".sizeof($site_list)." sites.\n";

    }

    function endsWith($haystack, $needle) {
        $length = strlen($needle);

        return $length === 0 ||
            (substr($haystack, -$length) === $needle);
    }
}

?>
