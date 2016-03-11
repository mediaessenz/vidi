<?php
namespace Fab\Vidi\Service;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Fab\Vidi\Module\ModuleLoader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Fab\Vidi\Domain\Repository\ContentRepositoryFactory;
use Fab\Vidi\Persistence\Matcher;
use Fab\Vidi\Persistence\Order;
use Fab\Vidi\Signal\AfterFindContentObjectsSignalArguments;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * File References service.
 * Find a bunch of file references given by the property name.
 */
class ContentService
{

    /**
     * @var string
     */
    protected $dataType;

    /**
     * @var \Fab\Vidi\Domain\Model\Content[]
     */
    protected $objects = array();

    /**
     * @var int
     */
    protected $numberOfObjects = 0;

    /**
     * Constructor
     *
     * @param string $dataType
     * @return ContentService
     */
    public function __construct($dataType = '')
    {
        if (empty($dataType)) {
            $dataType = $this->getModuleLoader()->getDataType();
        }
        $this->dataType = $dataType;
    }

    /**
     * Fetch the files given an object assuming
     *
     * @param Matcher $matcher
     * @param Order $order The order
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function findBy(Matcher $matcher, Order $order = NULL, $limit = NULL, $offset = NULL)
    {

        // Query the repository.
        $objects = ContentRepositoryFactory::getInstance($this->dataType)->findBy($matcher, $order, $limit, $offset);
        $signalResult = $this->emitAfterFindContentObjectsSignal($objects, $matcher, $order, $limit, $offset);

        // Reset objects variable after possible signal / slot processing.
        $this->objects = $signalResult->getContentObjects();

        // Count number of content objects.
        if ($signalResult->getHasBeenProcessed()) {
            $this->numberOfObjects = $signalResult->getNumberOfObjects();
        } else {
            $this->numberOfObjects = ContentRepositoryFactory::getInstance($this->dataType)->countBy($matcher);
        }

        return $this;
    }

    /**
     * Signal that is called after the content objects have been found.
     *
     * @param array $contentObjects
     * @param \Fab\Vidi\Persistence\Matcher $matcher
     * @param Order $order
     * @param int $limit
     * @param int $offset
     * @return AfterFindContentObjectsSignalArguments
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     * @signal
     */
    protected function emitAfterFindContentObjectsSignal($contentObjects, Matcher $matcher, Order $order = NULL, $limit = 0, $offset = 0)
    {

        /** @var AfterFindContentObjectsSignalArguments $signalArguments */
        $signalArguments = GeneralUtility::makeInstance(AfterFindContentObjectsSignalArguments::class);
        $signalArguments->setDataType($this->dataType)
            ->setContentObjects($contentObjects)
            ->setMatcher($matcher)
            ->setOrder($order)
            ->setLimit($limit)
            ->setOffset($offset)
            ->setHasBeenProcessed(FALSE);

        $signalResult = $this->getSignalSlotDispatcher()->dispatch(ContentService::class, 'afterFindContentObjects', array($signalArguments));
        return $signalResult[0];
    }

    /**
     * Get the Vidi Module Loader.
     *
     * @return ModuleLoader
     */
    protected function getModuleLoader()
    {
        return GeneralUtility::makeInstance(ModuleLoader::class);
    }

    /**
     * Get the SignalSlot dispatcher.
     *
     * @return Dispatcher
     */
    protected function getSignalSlotDispatcher()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        return $objectManager->get(Dispatcher::class);
    }

    /**
     * @return \Fab\Vidi\Domain\Model\Content[]
     */
    public function getObjects()
    {
        return $this->objects;
    }

    /**
     * @return int
     */
    public function getNumberOfObjects()
    {
        return $this->numberOfObjects;
    }
}
