<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA
 *
 */

namespace oat\taoResultExports\model\export;

use oat\oatbox\filesystem\File;
use oat\oatbox\filesystem\FileSystemService;
use oat\oatbox\service\ConfigurableService;
use oat\oatbox\service\ServiceManager;
use oat\taoDelivery\model\execution\ServiceProxy;
use oat\taoDeliveryRdf\model\DeliveryAssemblyService;
use oat\taoResultServer\models\classes\ResultManagement;
use oat\taoResultServer\models\classes\ResultServerService;
use qtism\data\AssessmentTest;
use qtism\data\ExtendedAssessmentItemRef;
use qtism\data\state\OutcomeDeclaration;
use qtism\data\state\ResponseDeclaration;
use qtism\data\storage\php\PhpDocument;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class AllBookletsExport extends ConfigurableService
{
    use ServiceLocatorAwareTrait;

    const SERVICE_ID = 'taoResultExports/BookletsExport';

    const IDENTIFIER_STRATEGY_ITEMREFIDENTIFIER = 0;
    const IDENTIFIER_STRATEGY_TITLE = 1;
    const IDENTIFIER_STRATEGY_LABEL = 2;
    const IDENTIFIER_STRATEGY_IDENTIFIER = 3;
    
    const VARIABLE_POLICY_ALL = 0;
    const VARIABLE_POLICY_RESPONSE = 1;
    const VARIABLE_POLICY_OUTCOME = 2;
    
    const MATCH_TYPE_COLUMN = 0;
    const MATCH_TYPE_ROW = 1;
    const MATCH_TYPE_MATRIX = 2;

    const FILESYSTEM_ID = 'resultExport';
    const FILESYSTEM_NAME = 'taoResultExports';

    const NOT_REQUIRED_OPTION = 'notRequired';
    const NOT_ATTEMPTED_OPTION = 'notAttempted';
    const NOT_RESPONDED_OPTION = 'notResponded';

    /**
     * Order and expected columns
     * @var array
     */
    private $expectedVariables = [
        'duration',
        'numAttempts',
    ];

    private $variablesByDeliveries = [];
    
    private $matchTypeByDeliveries = [];
    
    private $matchSetsByDeliveries = [];
    
    private $hottextTypeByDeliveries = [];

    private $choiceTypeByDeliveries = [];
    
    private $orderTypeByDeliveries = [];
    
    private $assessmentItemRefIdentifierMapping = [];
    
    private $identifierStrategy;
    
    private $variablePolicy = self::VARIABLE_POLICY_ALL;
    
    private $variableBlacklist = [];
    
    private $forcedItemIdentifiers = [];
    
    private $alternateMissingDataEncodings = [];
    
    private $globalAlternateMissingDataEncodings = [];

    /**
     * @var array
     */
    private $deliveries;

    private $headers = null;

    /** @var string  */
    private $prefix;

    /**
     * Filesystem File to manage exported file storage
     *
     * @var File
     */
    protected $exportFile;
    
    protected $flySystemFile;

    /**
     * @param string $identifierStrategy
     */
    public function setIdentifierStrategy($identifierStrategy)
    {
        $this->identifierStrategy = $identifierStrategy;
    }

    /**
     * @param array $deliveries
     */
    public function setDeliveries($deliveries)
    {
        $this->deliveries = $deliveries;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = rtrim($prefix,'_').'_';;
    }


    
    public function setVariablePolicy($variablePolicy)
    {
        $this->variablePolicy = $variablePolicy;
    }
    
    public function setVariableBlacklist(array $blacklist)
    {
        $this->variableBlacklist = $blacklist;
    }

    public function addVariableToBlacklist($variableName)
    {
        $this->variableBlacklist[] = $variableName;
    }

    /**
     * Generate array with statistics for all deliveries
     *
     * @return \common_report_Report
     *
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     * @throws \qtism\data\storage\php\PhpStorageException
     */
    public function export()
    {
        $report = \common_report_Report::createSuccess();
        $startTime = microtime(true);

        // Initialize temporary file.
        $tmpLocation = tempnam(sys_get_temp_dir(), 'invalsiexport');
        $this->exportFile = fopen($tmpLocation, 'w+');

        $headers = $this->getHeaders();
        $this->writeToCsv($headers, true);

        $rowsReport = $this->getRows();
        $report->add($rowsReport);

        if ($report->containsError()) {
            $report->setType(\common_report_Report::TYPE_ERROR);
            $report->setMessage('Some errors occurred during export :');
        }

        $endTime = microtime(true);
        $totalTime = self::formatTime($endTime - $startTime);

        $rows = $rowsReport->getData();
        $report->setMessage("${rows} row(s) exported in ${totalTime}.");
        
        // Write as stream and close temporary file.
        $filename = $this->prefix. date('His') .'.csv';
        $this->flySystemFile = $this->getServiceLocator()
            ->get(FileSystemService::SERVICE_ID)
            ->getDirectory(self::FILESYSTEM_ID)
            ->getFile(date('Y_m_d') . gethostname() .'/'. $filename);
            
        rewind($this->exportFile);
        $this->flySystemFile->write($this->exportFile);
        
        fclose($this->exportFile);
        unlink($tmpLocation);
        
        return $report;
    }

    /**
     * @return \oat\oatbox\filesystem\File
     */
    public function getFile()
    {
        return $this->flySystemFile;
    }

    /**
     * Get the list of headers for all deliveries that we want to export
     *
     * @return array $headers
     * @throws \qtism\data\storage\php\PhpStorageException
     */
    private function getHeaders()
    {
        if (is_null($this->headers)) {
            $headers = array('ID', 'IDFORM', 'STARTTIME', 'FINISHTIME');
            $report = \common_report_Report::createSuccess();


            /** @var \tao_models_classes_service_FileStorage $storageService */
            $storageService = $this->getServiceManager()->get(\tao_models_classes_service_FileStorage::SERVICE_ID);

            foreach ($this->deliveries as $delivery) {
                $this->assessmentItemRefIdentifierMapping[$delivery->getUri()] = [];
                
                //Get item list
                $deliveryUri = $delivery->getUri();
                $this->variablesByDeliveries[$deliveryUri] = [];
                $this->matchTypeByDeliveries[$deliveryUri] = [];
                $this->hottextTypeByDeliveries[$deliveryUri] = [];
                $this->choiceTypeByDeliveries[$deliveryUri] = [];
                $directories = $delivery->getPropertyValues(new \core_kernel_classes_Property(DeliveryAssemblyService::PROPERTY_DELIVERY_DIRECTORY));
                
                foreach ($directories as $directory) {
                    $dir = $storageService->getDirectoryById($directory);
                    $files = $dir->getFlyIterator(3);
                    
                    /** @var File $file */
                    foreach ($files as $file) {
                        if ($file->getBasename() === 'compact-test.php') {
                            $phpDocument = new PhpDocument();
                            $phpDocument->loadFromString($file->read());
                            /** @var AssessmentTest $assementTest */
                            $assementTest = $phpDocument->getDocumentComponent();
                            $components = $assementTest->getComponentsByClassName('assessmentItemRef');

                            /** @var ExtendedAssessmentItemRef $component */
                            foreach ($components as $component) {
                                $href = explode('|', $component->getHref());
                                $private = $href[2];
                                $itemJsonDir = $storageService->getDirectoryById($private);
                                $itemJsonFiles = $itemJsonDir->getFlyIterator(3);
                                $choices = array();

                                foreach ($itemJsonFiles as $itemJsonFile) {
                                    if ($itemJsonFile->getBasename() === 'item.json') {
                                        $itemJson = json_decode($itemJsonFile->read());
                                        $identifier = $this->getAssessmentItemRefIdentifier($delivery, $component, $itemJson, $this->identifierStrategy);
                                        $this->assessmentItemRefIdentifierMapping[$delivery->getUri()][$component->getIdentifier()] = $identifier;
                                        $interactions = AllBookletsExportUtils::getInteractions($itemJson);
                                        
                                        if (isset($interactions['matchInteraction'])) {
                                            foreach ($interactions['matchInteraction'] as $responseIdentifier => $jsonInteraction) {
                                                $headerResponseIdentifier = $identifier . '-' . $responseIdentifier;
                                                $matchSets = AllBookletsExportUtils::getMatchSets($jsonInteraction, $responseIdentifier);
                                                
                                                if (AllBookletsExportUtils::isMatchByColumn($jsonInteraction)) {
                                                    // By column strategy.
                                                    $strategy = self::MATCH_TYPE_COLUMN;
                                                    $choices[$identifier . $responseIdentifier] = $matchSets[0];

                                                } elseif (AllBookletsExportUtils::isMatchByRow($jsonInteraction)) {
                                                    // By row strategy.
                                                    $strategy = self::MATCH_TYPE_ROW;
                                                    $choices[$identifier . $responseIdentifier] = $matchSets[1];
                                                } else {
                                                    // Matrix strategy.
                                                    $strategy = self::MATCH_TYPE_MATRIX;
                                                    $choices[$identifier . $responseIdentifier] = $matchSets;
                                                }
                                                
                                                $this->matchTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] = $strategy;
                                                $this->matchSetsByDeliveries[$deliveryUri][$headerResponseIdentifier] = $matchSets;

                                            }
                                        }
                                        
                                        if (isset($interactions['gapMatchInteraction'])) {
                                            foreach ($interactions['gapMatchInteraction'] as $responseIdentifier => $jsonInteraction) {
                                                $headerResponseIdentifier = $identifier . '-' . $responseIdentifier;
                                                $this->matchTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] = self::MATCH_TYPE_ROW;
                                                
                                                $matchSets = AllBookletsExportUtils::getMatchSets($jsonInteraction, $responseIdentifier);
                                                $this->matchSetsByDeliveries[$deliveryUri][$headerResponseIdentifier] = $matchSets;
                                                $choices[$identifier . $responseIdentifier] = $matchSets[0];
                                            }
                                        }
                                        
                                        if (isset($interactions['hottextInteraction'])) {
                                            foreach ($interactions['hottextInteraction'] as $responseIdentifier => $jsonInteraction) {
                                                $headerResponseIdentifier = $identifier . '-' . $responseIdentifier;
                                                $this->hottextTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] = true;
                                                
                                                $matchSets = AllBookletsExportUtils::getMatchSets($jsonInteraction, $responseIdentifier);
                                                $choices[$identifier . $responseIdentifier] = $matchSets[0];
                                            }
                                        }
                                        
                                        if (isset($interactions['choiceInteraction'])) {
                                            foreach ($interactions['choiceInteraction'] as $responseIdentifier => $jsonInteraction) {
                                                $headerResponseIdentifier = $identifier . '-' . $responseIdentifier;
                                                
                                                if (isset($jsonInteraction->attributes->maxChoices) && $jsonInteraction->attributes->maxChoices !== 1) {
                                                    // Multiple cardinality (similar to hottext)
                                                    $this->hottextTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] = true;
                                                    $matchSets = AllBookletsExportUtils::getMatchSets($jsonInteraction, $responseIdentifier);
                                                    $choices[$identifier . $responseIdentifier] = $matchSets[0];
                                                } else {
                                                    // Single cardinality
                                                    $matchSets = AllBookletsExportUtils::getMatchSets($jsonInteraction, $responseIdentifier);
                                                    $this->choiceTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] = $matchSets[0];
                                                }
                                            }
                                        }
                                        
                                        if (isset($interactions['inlineChoiceInteraction'])) {
                                            foreach ($interactions['inlineChoiceInteraction'] as $responseIdentifier => $jsonInteraction) {
                                                $headerResponseIdentifier = $identifier . '-' . $responseIdentifier;
                                                $matchSets = AllBookletsExportUtils::getMatchSets($jsonInteraction, $responseIdentifier);
                                                $this->choiceTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] = $matchSets[0];
                                            }
                                        }
                                        
                                        if (isset($interactions['orderInteraction'])) {
                                            foreach ($interactions['orderInteraction'] as $responseIdentifier => $jsonInteraction) {
                                                $headerResponseIdentifier = $identifier . '-' . $responseIdentifier;
                                                $matchSets = AllBookletsExportUtils::getMatchSets($jsonInteraction, $responseIdentifier);
                                                $this->orderTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] = $matchSets[0];
                                                $choices[$identifier . $responseIdentifier] = $matchSets[0];
                                            }
                                        }

                                        if (isset($interactions['mediaInteraction'])) {
                                            foreach (array_keys($interactions['mediaInteraction']) as $responseIdentifier) {
                                                $this->addVariableToBlacklist($identifier . '-' . $responseIdentifier);
                                            }
                                        }

                                        if (isset($interactions['customInteraction'])) {
                                            foreach (array_keys($interactions['customInteraction']) as $responseIdentifier) {
                                                $this->addVariableToBlacklist($identifier . '-' . $responseIdentifier);
                                            }
                                        }

                                        break;
                                    }
                                }
                                
                                $outcomes = $component->getOutcomeDeclarations();
                                $responses = $component->getResponseDeclarations();
                                
                                if ($this->variablePolicy === self::VARIABLE_POLICY_ALL || $this->variablePolicy === self::VARIABLE_POLICY_RESPONSE) {
                                    /** @var ResponseDeclaration $response */
                                    foreach ($responses as $response) {
                                        $headerResponseIdentifier = $identifier . '-' . $response->getIdentifier();
                                        
                                        if ($this->isVariableNameBlacklisted($response->getIdentifier(), $headerResponseIdentifier) === false) {
                                            
                                            if (isset($choices[$identifier . $response->getIdentifier()])) {
                                                // Non default response variable structure (e.g. matchInteraction, gapMatchInteraction, ...)
                                                if (isset($this->matchSetsByDeliveries[$deliveryUri][$headerResponseIdentifier]) && isset($this->matchTypeByDeliveries[$deliveryUri][$headerResponseIdentifier]) && $this->matchTypeByDeliveries[$deliveryUri][$headerResponseIdentifier] === self::MATCH_TYPE_MATRIX) {
                                                    // Match Matrix specific case.
                                                    $alpha = range('a', 'z');
                                                    $matchSets = $this->matchSetsByDeliveries[$deliveryUri][$headerResponseIdentifier];
                                                    foreach ($matchSets[1] as $choiceId1) {
                                                        $alphaId1 = $alpha[AllBookletsExportUtils::matchSetIndex($choiceId1, $matchSets)];
                                                        
                                                        foreach ($matchSets[0] as $choiceId2) {
                                                            $alphaId2 = $alpha[AllBookletsExportUtils::matchSetIndex($choiceId2, $matchSets)];
                                                            $this->variablesByDeliveries[$delivery->getUri()][] = "${headerResponseIdentifier}-${alphaId1}-${alphaId2}";
                                                        }
                                                    }
                                                } else {
                                                    foreach ($choices[$identifier . $response->getIdentifier()] as $choiceId) {
                                                        if (isset($this->matchSetsByDeliveries[$deliveryUri][$headerResponseIdentifier])) {
                                                            $alpha = range('a', 'z');
                                                            $choiceId = $alpha[AllBookletsExportUtils::matchSetIndex($choiceId, $this->matchSetsByDeliveries[$deliveryUri][$headerResponseIdentifier])];
                                                        } elseif (isset($this->choiceMultipleTypeByDeliveries[$deliveryUri][$headerResponseIdentifier])) {
                                                            $alpha = range('a', 'z');
                                                            $choiceId = $alpha[AllBookletsExportUtils::matchSetIndex($choiceId, [$this->choiceMultipleTypeByDeliveries[$deliveryUri][$headerResponseIdentifier]])];
                                                        }
                                                        
                                                        $this->variablesByDeliveries[$delivery->getUri()][] = $headerResponseIdentifier . '-' . $choiceId;
                                                    }
                                                }
                                            } else {
                                                // Default response variable structure.
                                                $this->variablesByDeliveries[$delivery->getUri()][] = $headerResponseIdentifier;
                                            }
                                        }
                                    }
                                }

                                if ($this->variablePolicy === self::VARIABLE_POLICY_ALL || $this->variablePolicy === self::VARIABLE_POLICY_OUTCOME) {
                                    /** @var OutcomeDeclaration $outcome */
                                    foreach ($outcomes as $outcome) {
                                        $headerOutcomeIdentifier = $identifier . '-' . $outcome->getIdentifier();
                                        if ($this->isVariableNameBlacklisted($outcome->getIdentifier(), $headerOutcomeIdentifier) === false) {
                                            $this->variablesByDeliveries[$delivery->getUri()][] = $headerOutcomeIdentifier;
                                        }
                                    }
                                }

                                // Deal with variables that are QTI built-in.
                                foreach($this->expectedVariables as $expectedVariable){
                                    $headerExpectedVariable = $identifier . '-' . $expectedVariable;
                                    if ($this->isVariableNameBlacklisted($expectedVariable, $headerExpectedVariable) === false) {
                                        $this->variablesByDeliveries[$delivery->getUri()][] = $headerExpectedVariable;
                                    }
                                }
                            }
                            break 2;
                        }
                    }
                }

            }

            foreach($this->variablesByDeliveries as $key => $variable){
                $diff = array_diff($variable, $headers);
                if(!empty($diff)){
                    $headers = array_merge($headers, $diff);
                }
            }

            if(empty($headers)){
                $report->setType(\common_report_Report::TYPE_ERROR);
                $report->setMessage('Impossible to get headers for theses deliveries');
                return $report;
            }

            $this->headers = array_unique($headers);
        }
        return $this->headers;

    }

    /**
     * Get all the rows of all executions of all deliveries
     *
     * @return \common_report_Report
     *
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     * @throws \qtism\data\storage\php\PhpStorageException
     */
    private function getRows()
    {

        $report = \common_report_Report::createSuccess();
        
        $exportedResultsCount = 0;
        $i = 0;
        /** @var \core_kernel_classes_Resource $delivery */
        foreach($this->deliveries as $delivery){
            $deliveryUri = $delivery->getUri();
            $storage = $this->getServiceManager()->get(ResultServerService::SERVICE_ID)->getResultStorage($delivery);

            if($storage instanceof ResultManagement){
                $results = $storage->getResultByDelivery([$deliveryUri]);
                // get each row and write it to the csv

                foreach($results as $result){
                    $execution = $execution = ServiceProxy::singleton()->getDeliveryExecution($result['deliveryResultIdentifier']);
                    $row = array();

                    $user = new \core_kernel_classes_Resource($execution->getUserIdentifier());
                    $row['ID'] = $user->getLabel();
                    $row['IDFORM'] = $delivery->getLabel();
                    $row['STARTTIME'] = $this->cleanTimestamp($execution->getStartTime());
                    $row['FINISHTIME'] = (($endTime = $execution->getFinishTime()) !== null) ? $this->cleanTimestamp($endTime) : ''; 

                    $callIds = $storage->getRelatedItemCallIds($execution->getIdentifier());
                    foreach ($callIds as $callId) {
                        
                        $itemResults = $storage->getVariables($callId);
                        $splitCallId = explode('#', $callId);
                        $itemIdentifier = explode('.', $splitCallId[1]);
                        $itemIdentifier = $this->assessmentItemRefIdentifierMapping[$deliveryUri][$itemIdentifier[1]];
                        
                        foreach ($itemResults as $itemResult) {
                            $itemResult = array_pop($itemResult);
                            /** @var \taoResultServer_models_classes_Variable $variable */
                            $variable = $itemResult->variable;
                            $headerIdentifier = $itemIdentifier . '-' . $variable->getIdentifier();
                            
                            // Deal with variable policy...
                            if (($variable instanceof \taoResultServer_models_classes_ResponseVariable && $this->variablePolicy === self::VARIABLE_POLICY_OUTCOME) ||
                                ($variable instanceof \taoResultServer_models_classes_OutcomeVariable && $this->variablePolicy === self::VARIABLE_POLICY_RESPONSE) ||
                                $this->isVariableNameBlacklisted($variable->getIdentifier(), $headerIdentifier)) {
                                continue;
                            }
                            
                            // The variable has to be exported!
                            
                            if (isset($this->variablesByDeliveries[$deliveryUri]) && in_array($headerIdentifier, $this->variablesByDeliveries[$deliveryUri])){
                                // Not modified variable header.
                                $tmpVal = $this->formatVariable($variable);
                                
                                // choiceInteraction (single cardinality), inlineChoiceInteraction.
                                if((isset($this->choiceTypeByDeliveries[$deliveryUri]) && in_array($headerIdentifier, array_keys($this->choiceTypeByDeliveries[$deliveryUri]))) &&
                                    ($index = array_search($tmpVal, $this->choiceTypeByDeliveries[$deliveryUri][$headerIdentifier])) !== false) {
                                    
                                    $row[$headerIdentifier] = $index + 1;
                                } else {
                                    // Default response variable structure (textEntry, extendedTextEntry)
                                    $row[$headerIdentifier] = $tmpVal;
                                }
                            } elseif (isset($this->matchTypeByDeliveries[$deliveryUri]) && in_array($headerIdentifier, array_keys($this->matchTypeByDeliveries[$deliveryUri]))) {
                                // matchInteraction, gapMatchInteraction.
                                $this->setColumnTo($row, $this->variablesByDeliveries[$deliveryUri], $headerIdentifier);
                                
                                $tmpVal = $this->formatVariable($variable);
                                $val = \taoQtiCommon_helpers_Utils::toQtiDatatype('multiple', 'pair', $tmpVal);
                                $alpha = range('a', 'z');
                                
                                if ($val) {
                                    if ($this->matchTypeByDeliveries[$deliveryUri][$headerIdentifier] !== self::MATCH_TYPE_MATRIX) {
                                        foreach ($val as $v) {
                                            // Column or row strategy.
                                            $cmp = ($this->matchTypeByDeliveries[$deliveryUri][$headerIdentifier] === self::MATCH_TYPE_COLUMN) ? $v->getFirst() : $v->getSecond();
                                            $set = ($this->matchTypeByDeliveries[$deliveryUri][$headerIdentifier] === self::MATCH_TYPE_COLUMN) ? $v->getSecond() : $v->getFirst();
                                            
                                            $matchHeaderIdentifier = $headerIdentifier . '-' . $alpha[AllBookletsExportUtils::matchSetIndex($cmp, $this->matchSetsByDeliveries[$deliveryUri][$headerIdentifier])];
                                            $row[$matchHeaderIdentifier] = AllBookletsExportUtils::matchSetIndex($set, $this->matchSetsByDeliveries[$deliveryUri][$headerIdentifier]) + 1;
                                        }
                                    } else {
                                        // Matrix strategy.
                                        foreach ($val as $v) {
                                            $alpha1 = $alpha[AllBookletsExportUtils::matchSetIndex($v->getSecond(), $this->matchSetsByDeliveries[$deliveryUri][$headerIdentifier])];
                                            $alpha2 = $alpha[AllBookletsExportUtils::matchSetIndex($v->getFirst(), $this->matchSetsByDeliveries[$deliveryUri][$headerIdentifier])];
                                            
                                            $matchHeaderIdentifier = "${headerIdentifier}-{$alpha1}-${alpha2}";
                                            $row[$matchHeaderIdentifier] = '1';
                                        }
                                    }
                                }
                            } elseif (isset($this->hottextTypeByDeliveries[$deliveryUri]) && in_array($headerIdentifier, array_keys($this->hottextTypeByDeliveries[$deliveryUri]))) {
                                // hottextInteraction.
                                $this->setColumnTo($row, $this->variablesByDeliveries[$deliveryUri], $headerIdentifier);
                                
                                $tmpVal = $this->formatVariable($variable);
                                $val = \taoQtiCommon_helpers_Utils::toQtiDatatype('multiple', 'identifier', $tmpVal);
                                
                                if ($val) {
                                    foreach ($val as $v) {
                                        $v = trim($v->getValue(), "'");
                                        if (in_array($headerIdentifier . '-' . $v, $this->variablesByDeliveries[$deliveryUri])) {
                                            $row[$headerIdentifier . '-' . $v] = '1';
                                        }
                                    }
                                }
                            } elseif (isset($this->orderTypeByDeliveries[$deliveryUri]) && in_array($headerIdentifier, array_keys($this->orderTypeByDeliveries[$deliveryUri]))) {
                                // orderInteraction.
                                $this->setColumnTo($row, $this->variablesByDeliveries[$deliveryUri], $headerIdentifier);
                                $tmpVal = $this->formatVariable($variable);
                                $val = \taoQtiCommon_helpers_Utils::toQtiDatatype('multiple', 'identifier', $tmpVal);

                                if ($val) {
                                    
                                    for ($j = 0; $j < count($val); $j++) {
                                        $v = trim($val[$j]->getValue(), "'");
                                        
                                        if (in_array($headerIdentifier . '-' . $v, $this->variablesByDeliveries[$deliveryUri])) {
                                            $row[$headerIdentifier . '-' . $v] = $j + 1;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (empty($row)) {
                        $report->setType(\common_report_Report::TYPE_ERROR);
                        $report->setMessage('Impossible to get row for theses delivery ' . $delivery . 'and execution ' . $execution->getIdentifier());
                        return $report;
                    }
                    
                    $this->fillRow($row, $deliveryUri);
                    $this->rowPostProcessing($row, $deliveryUri);
                    
                    $this->writeToCsv($row);
                    
                    $i++;
                    $exportedResultsCount++;
                }
            }

        }
        
        $report->setData($i);

        return $report;
    }

    /**
     * Write the row provided in the csv file
     *
     * @param array $row
     * @param bool $isHeader
     *
     * @throws \qtism\data\storage\php\PhpStorageException
     */
    private function writeToCsv(array $row, $isHeader = false)
    {
        // order the row to match the headers
        
        if (!$isHeader) {
            // Prepare a result row to be written.
            $headers = $this->getHeaders();
            $newRow = [];
            
            foreach($headers as $value) {
                if (isset($row[$value])) {
                    $newRow[] = $row[$value];
                } else {
                    \common_Logger::w('column : ' . $value . ' is missing in row ' .implode(',', $row));
                }
            }
            
            $row = $newRow;
            
        } else {
            // Prepare a header row to be written.
            $this->headerPostProcessing($row);
        }

        // Write the row using PHP's default CSV configuration.
        if (fputcsv($this->exportFile, $row) === false) {
            \common_Logger::w('Fail to write in the csv file : ' . implode(',', $row));
        }
    }

    /**
     * Get the filename
     * @return string
     */
    public function getFilename()
    {
        return $this->flySystemFile->getPrefix();
    }
    
    /**
     * Row Post-processing
     * 
     * Sub-classes can override this method in order to apply some post-processing
     * on exported CSV rows before they are written.
     * 
     * @param array $row A reference on an array representing the a CSV row to be written.
     * @param string $deliveryUri The URI of the $delivery the $row belongs to.
     * @return void
     * 
     */
    protected function rowPostProcessing(array &$row, $deliveryUri)
    {
        // Do nothing, it's just an opportunity to delegate some 
        // post-processing on more specific implementations.
    }

    /**
     * @param array $header
     */
    protected function headerPostProcessing(array &$header)
    {
        // Do nothing, it's just an opportunity to delegate some
        // post-processing on more specific implementations.
    }

    /**
     * Format the variable value to correspond to what invalsi want
     *
     * @param \taoResultServer_models_classes_Variable $variable
     *
     * @return string $variable
     */
    private function formatVariable($variable)
    {
        if($variable instanceof \taoResultServer_models_classes_ResponseVariable){
            if ($variable->getIdentifier() === 'duration') {
                $duration = AllBookletsExportUtils::formatDuration($variable->getValue());
                return ($duration === false) ? '' : $duration;
            } else {
                return $variable->getValue();
            }
        } else {
            return $variable->getValue();
        }
    }

    /**
     * Fill empty cells with correct placeholder.
     * 
     * This method will fill empty cells with the appropriate missing data encoding values.
     * 
     * @param array $row A reference to the array representing the row to fill.
     * @param string $deliveryUri The delivery that the test taker took.
     */
    private function fillRow(&$row, $deliveryUri)
    {
        $neededColumns = array();
        $emptyColumns = array();
        
        foreach ($this->variablesByDeliveries as $delivery => $columns) {
            if ($delivery === $deliveryUri) {
                // Columns that are related to $deliveryUri.
                $neededColumns = $this->variablesByDeliveries[$deliveryUri];
            } else {
                // Columns that are NOT related to $deliveryUri.
                $emptyColumns = array_merge($emptyColumns, $this->variablesByDeliveries[$delivery]);
            }
        }
        
        foreach ($neededColumns as $column) {
            if (!in_array($column, array_keys($row))) {
                $row[$column] = $this->determineMissingDataEncoding($this->getOption(self::NOT_ATTEMPTED_OPTION), $column);
            }
            
            if ($row[$column] === '' || $row[$column] === '[]' || $row[$column] === '<>') {
                $row[$column] = $this->determineMissingDataEncoding($this->getOption(self::NOT_RESPONDED_OPTION), $column);
            }
        }

        foreach ($emptyColumns as $column) {
            if (!in_array($column, array_keys($row))) {
                $row[$column] = $this->determineMissingDataEncoding($this->getOption(self::NOT_REQUIRED_OPTION), $column);
            }
        }
    }
    
    protected function determineMissingDataEncoding($code, $column)
    {
        if (isset($this->alternateMissingDataEncodings[$column]) === true && isset($this->alternateMissingDataEncodings[$column][$code]) === true) {
            
            return $this->alternateMissingDataEncodings[$column][$code];
            
        } elseif (isset($this->globalAlternateMissingDataEncodings[$code]) === true) {
            
            return $this->globalAlternateMissingDataEncodings[$code];
            
        } else {
            
            return $code;
        }
    }
    
    public function addAlternateMissingDataEnconding($originalCode, $newCode, $column = '')
    {
        if (empty($column) === false) {
            if (isset($this->alternateMissingDataEncodings[$column]) === false) {
                $this->alternateMissingDataEncodings[$column] = [];
            }
            
            $this->alternateMissingDataEncodings[$column][$originalCode] = $newCode;
        } else {
            $this->globalAlternateMissingDataEncodings[$originalCode] = $newCode;
        }
    }
    
    private function setColumnTo(array &$row, array $columns, $headerIdentifier, $value = '') {
        foreach ($columns as $col) {
            if (preg_match('/^' . preg_quote($headerIdentifier) . '-/', $col) === 1) {
                $row[$col] = $value;
            }
        }
    }
    
    private function getAssessmentItemRefIdentifier(\core_kernel_classes_Resource $delivery, ExtendedAssessmentItemRef $assessmentItemRef, $itemJson,  $strategy = 'itemRef')
    {
        if (($forcedItemIdentifier = $this->getForcedItemIdentifier($delivery->getUri(), $assessmentItemRef->getIdentifier())) !== false) {
            return $forcedItemIdentifier;
        }
        
        switch ($strategy) {
            case 'label':
            case self::IDENTIFIER_STRATEGY_LABEL:
                return $itemJson->data->attributes->label;
                break;
                
            case 'title':
            case self::IDENTIFIER_STRATEGY_TITLE:
                return $itemJson->data->attributes->title;
                break;
                
            case 'identifier':
            case self::IDENTIFIER_STRATEGY_IDENTIFIER:
                return $itemJson->data->attributes->identifier;
                break;
                
            case self::IDENTIFIER_STRATEGY_ITEMREFIDENTIFIER:
                return $assessmentItemRef->getIdentifier();
                break;
            
            // default = 'itemRef'
            default:
                return $assessmentItemRef->getIdentifier();
        }
    }
    
    private function cleanTimestamp($ts)
    {
        $ts = explode(' ', $ts);
        return $ts[1];
    }
    
    protected function isVariableNameBlacklisted($variableName, $fullVariableName = '')
    {
        if ($this->isWhiteListed($fullVariableName)) {
            return false;
        } else {
            $blackListed = in_array($fullVariableName, $this->variableBlacklist) || in_array($variableName, $this->variableBlacklist);

            return $blackListed;
        }
    }
    
    private static function formatTime($s)
    {
        $h = floor($s / 3600);
        $m = floor(($s / 60) % 60);
        $s = $s % 60;
        
        return "${h}h ${m}m ${s}s";
    }
    
    public function addForcedItemIdentifier($deliveryUri, $assessmentItemRefIdentifier, $forcedIdentifier) {
        $this->forcedItemIdentifiers[$deliveryUri][$assessmentItemRefIdentifier] = $forcedIdentifier;
    }
    
    private function getForcedItemIdentifier($deliveryUri, $assessmentItemRefIdentifier) {
        if (isset($this->forcedItemIdentifiers[$deliveryUri]) && isset($this->forcedItemIdentifiers[$deliveryUri][$assessmentItemRefIdentifier])) {
            return $this->forcedItemIdentifiers[$deliveryUri][$assessmentItemRefIdentifier];
        } else {
            return false;
        }
    }
    
    private function isWhiteListed($fullVariableName) {
        $white = false;
        
        foreach ($this->variableBlacklist as $vbl) {
            
            if ($vbl === "*${fullVariableName}") {
                $white = true;
                break;
            }
        }
        
        return $white;
    }
}
