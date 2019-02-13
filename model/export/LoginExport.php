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
 * Copyright (c) 2019 (original work) Open Assessment Technologies SA
 *
 */

namespace oat\taoResultExports\model\export;

use core_kernel_classes_Resource;
use oat\generis\model\GenerisRdf;
use oat\generis\model\OntologyAwareTrait;
use oat\generis\model\OntologyRdfs;
use oat\oatbox\filesystem\File;
use oat\oatbox\filesystem\FileSystemService;
use oat\oatbox\service\ConfigurableService;
use oat\oatbox\user\User;
use oat\taoDelivery\model\execution\DeliveryExecution;
use oat\taoDelivery\model\execution\ServiceProxy;
use oat\taoLti\models\classes\user\LtiUserService;
use oat\taoLti\models\classes\user\UserService;
use oat\taoResultServer\models\classes\ResultManagement;
use oat\taoResultServer\models\classes\ResultServerService;

class LoginExport extends ConfigurableService
{
    use OntologyAwareTrait;

    const SERVICE_ID = 'taoResultExports/LoginExport';

    /** @var core_kernel_classes_Resource[] */
    private $deliveries;

    /** @var string  */
    private $prefix;

    /**
     * The resource to handle the temporary file
     *
     * @var resource
     */
    protected $exportFile;

    /**
     * Filesystem File to manage exported file storage
     *
     * @var File
     */
    protected $flySystemFile;

    /**
     * @var bool   On FALSE there will not be timestamp in the filename
     */
    private $allowTimestampInFilename = true;

    /** @var bool */
    private $withHeaders = false;

    /**
     * @param core_kernel_classes_Resource[] $deliveries
     *
     * @return self
     */
    public function setDeliveries($deliveries)
    {
        $this->deliveries = $deliveries;

        return $this;
    }

    public function setPrefix($prefix)
    {
        if ($this->allowTimestampInFilename) {
            $this->prefix = rtrim($prefix,'_').'_';
        } else {
            $this->prefix = rtrim($prefix,'_');
        }

        return $this;
    }

    public function setAllowTimestampInFilename($allowTimestamp = true)
    {
        $this->allowTimestampInFilename = $allowTimestamp;

        return $this;
    }

    public function setWithHeaders($withHeaders = false)
    {
        $this->withHeaders = $withHeaders;

        return $this;
    }

    /**
     * Generate array with statistics for all deliveries
     *
     * @return \common_report_Report
     *
     * @throws \common_Exception
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     * @throws \qtism\data\storage\php\PhpStorageException
     */
    public function export()
    {
        $report = \common_report_Report::createSuccess();

        // Initialize temporary file.
        $tmpLocation = tempnam(sys_get_temp_dir(), 'invalsiexport');
        $this->exportFile = fopen($tmpLocation, 'w+');

        if ($this->withHeaders === true) {
            $this->writeToCsv($this->getHeaders());
        }

        $rowsReport = $this->getRows();
        $report->add($rowsReport);

        if ($report->containsError()) {
            $report->setType(\common_report_Report::TYPE_ERROR);
            $report->setMessage('Some errors occurred during export :');
        }

        $rows = $rowsReport->getData();

        // Write as stream and close temporary file.
        $exportFile = $this->createExportFile();
        $report->setMessage($rows . ' row(s) exported to ' . $exportFile->getPrefix() . '.');
        rewind($this->exportFile);
        $exportFile->write($this->exportFile);

        fclose($this->exportFile);
        unlink($tmpLocation);

        return $report;
    }


    /**
     * Create the filename for the export
     *
     * If there is a daily mode then add the date as subfolder
     *
     * @return mixed
     */
    protected function createExportFile()
    {
        $directory = date('Y_m_d') . DIRECTORY_SEPARATOR . gethostname() . DIRECTORY_SEPARATOR;

        $postfix = $this->allowTimestampInFilename ? date('His') : '';

        $filename = $this->prefix. $postfix .'.csv';

        /** @var File $file */
        $file = $this->getServiceLocator()
            ->get(FileSystemService::SERVICE_ID)
            ->getDirectory(AllBookletsExport::FILESYSTEM_ID)
            ->getFile($directory . $filename);

        if ($this->allowTimestampInFilename === false && $file->exists()) {
            $file->delete();
        }

        return $file;
    }

    /**
     * Get all the rows of all executions of all deliveries
     *
     * @return \common_report_Report
     *
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     * @throws \qtism\data\storage\php\PhpStorageException
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    private function getRows()
    {
        $report = \common_report_Report::createSuccess();

        /** @var UserService $userService */
        $userService = $this->getServiceManager()->get(UserService::SERVICE_ID);

        $i = 0;
        /** @var core_kernel_classes_Resource $delivery */
        foreach($this->deliveries as $delivery){
            $deliveryUri = $delivery->getUri();

            /** @var ResultManagement $storage */
            $storage = $this->getServiceLocator()->get(ResultServerService::SERVICE_ID)->getResultStorage($delivery);

            if(!$storage instanceof ResultManagement) {
                continue;
            }

            foreach($storage->getResultByDelivery([$deliveryUri]) as $result) {
                /** @var DeliveryExecution $execution */
                $execution = $this->getServiceLocator()->get(ServiceProxy::SERVICE_ID)->getDeliveryExecution($result['deliveryResultIdentifier']);
                $user = $userService->getUserById($execution->getUserIdentifier());

                $userLogin = $this->getUserLogin($user);

                if (empty($userLogin)) {
                    $report->setType(\common_report_Report::TYPE_ERROR);
                    $report->setMessage('Impossible to get row for theses delivery ' . $delivery . 'and execution ' . $execution->getIdentifier());

                    return $report;
                }

                $this->writeToCsv([$userLogin]);
                $i++;
            }
        }

        $report->setData($i);

        return $report;
    }

    private function getUserLogin(User $user)
    {
        // TAO login
        $login = $user->getPropertyValues(GenerisRdf::PROPERTY_USER_LOGIN);

        if (empty($login)) {
            // LTI login
            $login = $user->getPropertyValues(LtiUserService::PROPERTY_USER_LTIKEY);
        }

        if (!empty($login)) {
            return array_shift($login) ?: '';
        }

        // None of the above? :)
        $label = $user->getPropertyValues(OntologyRdfs::RDFS_LABEL);

        return array_shift($label) ?: '';
    }

    private function getHeaders()
    {
        return [
            'login',
        ];
    }

    /**
     * Write the row provided in the csv file
     *
     * @param array $row
     *
     * @throws \qtism\data\storage\php\PhpStorageException
     */
    private function writeToCsv(array $row)
    {
        // Write the row using PHP's default CSV configuration.
        if (fputcsv($this->exportFile, $row) === false) {
            \common_Logger::w('Fail to write in the csv file : ' . implode(',', $row));
        }
    }
}
