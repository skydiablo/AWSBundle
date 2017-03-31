<?php

namespace SkyDiablo\AWSBundle\Command\ElasticBeanstalk;

use Aws\ElasticBeanstalk\ElasticBeanstalkClient;
use Aws\ElasticBeanstalk\Exception\ElasticBeanstalkException;
use Aws\Result;
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
    const OPTION_AWS_EB_ENVIRONMENT_NAME = 'eb-env';
    const OPTION_AWS_EB_ENVIRONMENT_ID = 'eb-env-id';
    const OPTION_FORCE_RUN = 'force-run';
    const OPTION_INSTANCE_GROUP = 'instance-group';
    const OPTION_ENV_INSTANCE_GROUP = 'env-instance-group';
    const OPTION_INSTANCE_WAIT = 'instance-wait';
    const OPTION_ALREADY_RUN_ERROR_CODE = 'already-run-error-code';
    const ENVIRONMENT_TYPE_NAME = 'EnvironmentName';
    const ENVIRONMENT_TYPE_ID = 'EnvironmentId';

    protected function configure()
    {
        $this
            ->addOption(self::OPTION_AWS_EB_ENVIRONMENT_NAME, null, InputOption::VALUE_OPTIONAL, 'AWS ElasticBeanstalk Environment Name')
            ->addOption(self::OPTION_AWS_EB_ENVIRONMENT_ID, null, InputOption::VALUE_OPTIONAL, 'AWS ElasticBeanstalk Environment ID')
            ->addOption(self::OPTION_FORCE_RUN, null, InputOption::VALUE_NONE, 'Force run command, without instance check.')
            ->addOption(self::OPTION_INSTANCE_GROUP, null, InputOption::VALUE_OPTIONAL, 'Allow to group same command calls in different scopes.')
            ->addOption(self::OPTION_ENV_INSTANCE_GROUP, null, InputOption::VALUE_NONE, 'Use environment as instance group.')
            ->addOption(self::OPTION_INSTANCE_WAIT, null, InputOption::VALUE_NONE, 'Wait till other command call is done.')
            ->addOption(self::OPTION_ALREADY_RUN_ERROR_CODE, null, InputOption::VALUE_OPTIONAL, 'Error code if command already run', 0);
        $code = function (InputInterface $input, OutputInterface $output) {
            $forceRun = $input->getOption(self::OPTION_FORCE_RUN);
            if ($awsEbEnvironment = $input->getOption(self::OPTION_AWS_EB_ENVIRONMENT_NAME)) {
                $awsEbEnvironmentType = self::ENVIRONMENT_TYPE_NAME;
            } elseif ($awsEbEnvironment = $input->getOption(self::OPTION_AWS_EB_ENVIRONMENT_ID)) {
                $awsEbEnvironmentType = self::ENVIRONMENT_TYPE_ID;
            } else {
                throw new \InvalidArgumentException(sprintf('AWS EB environment name or id option is necessary, use "--%s XXX"', implode(' XXX | --', [
                    self::OPTION_AWS_EB_ENVIRONMENT_NAME, self::OPTION_AWS_EB_ENVIRONMENT_ID
                ])));
            }
            if ($group = $input->getOption(self::OPTION_INSTANCE_GROUP)) {
                $group = sprintf(self::GROUP_AFFIX_FORMAT, $this->getName(), $group);
            } elseif ($input->getOption(self::OPTION_ENV_INSTANCE_GROUP)) {
                $group = sprintf(self::GROUP_AFFIX_FORMAT, $this->getName(), $this->getContainer()->getParameter('kernel.environment'));
            }
            $wait = (bool)$input->getOption(self::OPTION_INSTANCE_WAIT);
            if ($forceRun || ($this->lock($group, $wait) && $this->imTheFirst($awsEbEnvironment, $awsEbEnvironmentType))) {
                return $this->execute($input, $output); // run normal flow...
            }
            // exit command
            return (int)$input->getOption(self::OPTION_ALREADY_RUN_ERROR_CODE); // != 0 for error
        };
        $this->setCode($code);
    }

    /**
     * @param string $environment
     * @param string $type
     * @return bool
     * @throws \Exception
     */
    private function imTheFirst(string $environment, string $type)
    {
        $res = null;
        /** @var ElasticBeanstalkClient $ebClient */
        $ebClient = $this->getContainer()->get(SkyDiabloAWSExtension::SERVICE_ID_COMMAND_ELASTIC_BEANSTALK_CLIENT);
        try {
            $res = $ebClient->describeEnvironmentResources([
                $type => $environment
            ]);
        } catch (ElasticBeanstalkException $e) {
            // todo: handle in a proper way?
            throw $e;
        }
        if ($res instanceof Result) {
            $instanceIds = array_map(function (array $data) {
                return $data['Id'] ?? PHP_INT_MAX; // extract all Ids
            }, (array)$res->search('EnvironmentResources.Instances'));
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