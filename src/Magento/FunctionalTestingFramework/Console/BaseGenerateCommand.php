<?php
// @codingStandardsIgnoreFile
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console;

use Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\FunctionalTestingFramework\Util\Filesystem\DirSetupUtil;
use Magento\FunctionalTestingFramework\Util\TestGenerator;
use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;
use Magento\FunctionalTestingFramework\Suite\Handlers\SuiteObjectHandler;

class BaseGenerateCommand extends Command
{
    /**
     * Configures the base command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->addOption(
            'remove',
            'r',
            InputOption::VALUE_NONE,
            'remove previous generated suites and tests'
        )->addOption(
            "force",
            'f',
            InputOption::VALUE_NONE,
            'force generation and running of tests regardless of Magento Instance Configuration'
        )->addOption(
            "allow-skipped",
            'a',
            InputOption::VALUE_NONE,
            'Allows MFTF to generate and run skipped tests.'
        )->addOption(
            'debug',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Run extra validation when generating and running tests. Use option \'none\' to turn off debugging -- 
             added for backward compatibility, will be removed in the next MAJOR release',
            MftfApplicationConfig::LEVEL_DEFAULT
        );
    }

    /**
     * Remove GENERATED_DIR if exists when running generate:tests.
     *
     * @param OutputInterface $output
     * @param bool $verbose
     * @return void
     */
    protected function removeGeneratedDirectory(OutputInterface $output, bool $verbose)
    {
        $generatedDirectory = TESTS_MODULE_PATH . DIRECTORY_SEPARATOR . TestGenerator::GENERATED_DIR;

        if (file_exists($generatedDirectory)) {
            DirSetupUtil::rmdirRecursive($generatedDirectory);
            if ($verbose) {
                $output->writeln("removed files and directory $generatedDirectory");
            }
        }
    }

    /**
     * Returns an array of test configuration to be used as an argument for generation of tests
     * @param array $tests
     * @return false|string
     * @throws \Magento\FunctionalTestingFramework\Exceptions\XmlException
     */
    protected function getTestAndSuiteConfiguration(array $tests)
    {
        $testConfiguration['tests'] = null;
        $testConfiguration['suites'] = null;
        $testsReferencedInSuites = SuiteObjectHandler::getInstance()->getAllTestReferences();
        $suiteToTestPair = [];

        foreach($tests as $test) {
            if (array_key_exists($test, $testsReferencedInSuites)) {
                $suites = $testsReferencedInSuites[$test];
                foreach ($suites as $suite) {
                    $suiteToTestPair[] = "$suite:$test";
                }
            }
            // configuration for tests
            else {
                $testConfiguration['tests'][] = $test;
            }
        }
        // configuration for suites
        foreach ($suiteToTestPair as $pair) {
            list($suite, $test) = explode(":", $pair);
            $testConfiguration['suites'][$suite][] = $test;
        }
        $testConfigurationJson = json_encode($testConfiguration);
        return $testConfigurationJson;
    }

    /**
     * Returns an array of test configuration to be used as an argument for generation of tests
     * This function uses group or suite names for generation
     * @return false|string
     * @throws \Magento\FunctionalTestingFramework\Exceptions\XmlException
     */
    protected function getGroupAndSuiteConfiguration(array $groupOrSuiteNames)
    {
        $result['tests'] = [];
        $result['suites'] = [];

        $groups = [];
        $suites = [];

        $allSuites = SuiteObjectHandler::getInstance()->getAllObjects();
        $testsInSuites = SuiteObjectHandler::getInstance()->getAllTestReferences();

        foreach ($groupOrSuiteNames as $groupOrSuiteName) {
            if (array_key_exists($groupOrSuiteName, $allSuites)) {
                $suites[] = $groupOrSuiteName;
            } else {
                $groups[] = $groupOrSuiteName;
            }
        }

        foreach ($suites as $suite) {
            $result['suites'][$suite] = [];
        }

        foreach ($groups as $group) {
            $testsInGroup = TestObjectHandler::getInstance()->getTestsByGroup($group);

            $testsInGroupAndNotInAnySuite = array_diff(
                array_keys($testsInGroup),
                array_keys($testsInSuites)
            );

            $testsInGroupAndInAnySuite = array_diff(
                array_keys($testsInGroup),
                $testsInGroupAndNotInAnySuite
            );

            foreach ($testsInGroupAndInAnySuite as $testInGroupAndInAnySuite) {
                $suiteName = $testsInSuites[$testInGroupAndInAnySuite][0];
                if (array_search($suiteName, $suites) !== false) {
                    // Suite is already being called to run in its entirety, do not filter list
                    continue;
                }
                $result['suites'][$suiteName][] = $testInGroupAndInAnySuite;
            }

            $result['tests'] = array_merge(
                $result['tests'],
                $testsInGroupAndNotInAnySuite
            );
        }

        if (empty($result['tests'])) {
            $result['tests'] = null;
        }
        if (empty($result['suites'])) {
            $result['suites'] = null;
        }

        $json = json_encode($result);
        return $json;
    }
}
