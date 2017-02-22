<?php

namespace SkyDiablo\AWSBundle\Command\ElasticBeanstalk;

use Aws\ElasticBeanstalk\ElasticBeanstalkClient;
use Aws\ElasticBeanstalk\Exception\ElasticBeanstalkException;
use GuzzleHttp\Client AS HTTPClient;
use SkyDiablo\AWSBundle\DependencyInjection\SkyDiabloAWSExtension;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author SkyDiablo <skydiablo@gmx.net>
 * Class SingleInstanceCommand
 */
abstract class SingleInstanceCommand extends ContainerAwareCommand
{

    use LockableTrait;

    const GROUP_AFFIX_FORMAT = '%s_%s';
    const AWS_INSTANCE_META_URL = 'http://instance-data/latest/meta-data/instance-id';
    const OPTION_AWS_EB_ENVIRONMENT = 'eb-env';
    const OPTION_FORCE_RUN = 'force-run';
    const OPTION_INSTANCE_GROUP = 'instance-group';
    const OPTION_ENV_INSTANCE_GROUP = 'env-instance-group';
    const OPTION_INSTANCE_WAIT = 'instance-wait';

    protected function configure()
    {
        $this
            ->addOption(self::OPTION_AWS_EB_ENVIRONMENT, null, InputOption::VALUE_REQUIRED, 'AWS ElasticBeanstalk Environment Name')
            ->addOption(self::OPTION_FORCE_RUN, null, InputOption::VALUE_NONE, 'Force run command, without instance check.')
            ->addOption(self::OPTION_INSTANCE_GROUP, null, InputOption::VALUE_OPTIONAL, 'Allow to group same command calls in different scopes.')
            ->addOption(self::OPTION_ENV_INSTANCE_GROUP, null, InputOption::VALUE_NONE, 'Use environment as instance group.')
            ->addOption(self::OPTION_INSTANCE_WAIT, null, InputOption::VALUE_NONE, 'Wait till other command call is done.');
        $code = function (InputInterface $input, OutputInterface $output) {
            $forceRun = $input->getOption(self::OPTION_FORCE_RUN);
            $environmentName = $input->getOption(self::OPTION_AWS_EB_ENVIRONMENT);
            if ($group = $input->getOption(self::OPTION_INSTANCE_GROUP)) {
                $group = sprintf(self::GROUP_AFFIX_FORMAT, $this->getName(), $group);
            } elseif ($input->getOption(self::OPTION_ENV_INSTANCE_GROUP)) {
                $group = sprintf(self::GROUP_AFFIX_FORMAT, $this->getName(), $this->getContainer()->getParameter('kernel.environment'));
            }
            $wait = (bool)$input->getOption(self::OPTION_INSTANCE_WAIT);
            if ($forceRun || ($this->lock($group, $wait) && $this->imTheFirst($environmentName))) {
                return $this->execute($input, $output); // run normal flow...
            }
            // exit command
            return 100; // != 0 for error
        };
        $this->setCode($code);
    }

    /**
     * @param $environmentName
     * @return bool
     * @throws \Exception
     */
    private function imTheFirst($environmentName)
    {
        $res = null;
        /** @var ElasticBeanstalkClient $ebClient */
        $ebClient = $this->getContainer()->get(SkyDiabloAWSExtension::SERVICE_ID_COMMAND_ELASTIC_BEANSTALK_CLIENT);
        try {
            $res = $ebClient->describeEnvironmentResources([
                'EnvironmentName' => $environmentName
            ]);
        } catch (ElasticBeanstalkException $e) {
            // todo: handle in a proper way?
            throw $e;
        }
        if ($res) {
            $instanceIds = array_map(function (array $data) {
                return $data['Id']; // extract all Ids
            }, $res['EnvironmentResources']['Instances']);
            sort($instanceIds); // sort Ids
            $firstInstanceId = reset($instanceIds); // get first id
            $httpClient = new HTTPClient();
            $instanceId = null;
            try {
                $instanceId = $httpClient->get(self::AWS_INSTANCE_META_URL)->getBody()->getContents();
            } catch (\Exception $e) {
                //todo: handle in a proper way?
                throw $e;
            }
            $instanceId = trim($instanceId); // sicher is sicher...
            return (strcasecmp($instanceId, $firstInstanceId) === 0);
        }
        return false;
    }

}