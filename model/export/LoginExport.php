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
use oat\oatbox\service\ConfigurableService;
use oat\oatbox\user\User;
use oat\taoDelivery\model\execution\DeliveryExecution;
use oat\taoDelivery\model\execution\ServiceProxy;
use oat\taoLti\models\classes\user\LtiUserService;
use oat\taoLti\models\classes\user\UserService;
use oat\taoResultExports\model\export\renderer\RendererInterface;
use oat\taoResultServer\models\classes\ResultManagement;
use oat\taoResultServer\models\classes\ResultServerService;

class LoginExport extends ConfigurableService
{
    use OntologyAwareTrait;

    const SERVICE_ID = 'taoResultExports/LoginExport';

    /** @var core_kernel_classes_Resource[] */
    private $deliveries;

    /** @var bool */
    private $withHeaders = false;

    /** @var RendererInterface */
    private $renderer;

    /**
     * @param RendererInterface $renderer
     *
     * @return $this
     */
    public function setRenderer(RendererInterface $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * @param core_kernel_classes_Resource[] $deliveries
     *
     * @return self
     */
    public function setDeliveries(array $deliveries)
    {
        $this->deliveries = $deliveries;

        return $this;
    }

    /**
     * @param bool $withHeaders
     *
     * @return $this
     */
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
     */
    public function export()
    {
        $report = \common_report_Report::createSuccess();

        if ($this->withHeaders === true) {
            $this->renderer->addRow($this->getHeaders());
        }

        $rowsReport = $this->addRows();
        $report->add($rowsReport);

        if ($report->containsError()) {
            $report->setType(\common_report_Report::TYPE_ERROR);
            $report->setMessage('Some errors occurred during export :');
        }

        $rows = $rowsReport->getData();

        // Write as stream and close temporary file.
        $path = $this->renderer->render();
        $report->setMessage($rows . ' row(s) exported to ' . $path . '.');

        return $report;
    }

    /**
     * Get all the rows of all executions of all deliveries
     *
     * @return \common_report_Report
     *
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    private function addRows()
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

                $this->renderer->addRow([$userLogin]);
                $i++;
            }
        }

        $report->setData($i);

        return $report;
    }

    /**
     * Returns the requested user's login.
     *
     * @param User $user
     *
     * @return string
     */
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

    /**
     * Returns the headers.
     *
     * @return array
     */
    private function getHeaders()
    {
        return [
            'login',
        ];
    }
}
