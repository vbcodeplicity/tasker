<?php
namespace G4\Tasker;

use G4\Tasker\Model\Mapper\Mysql\Task as TaskMapper;

class Manager extends TimerAbstract
{
    const TIME_FORMAT = 'Y-m-d H:i:s';

    private $_tasks;

    private $_options;

    private $_runner;

    private $_limit;

    /**
     *
     * @var G4\Tasker\Model\Mapper\Mysql\Task
     */
    private $_taskMapper;

    public function __construct()
    {
        $this->_timerStart();

        $this->_taskMapper = new TaskMapper();

        $this->_limit = Consts::LIMIT_DEFAULT;
    }

    public function run()
    {
        $this->_taskMapper->transactionBegin();
        try {
            $this
                ->_reserveTasks()
                ->_getReservedTasks();
        } catch (\Exception $e) {
            $this->_taskMapper->transactionRollback();
            return $this;
        }
        $this->_taskMapper->transactionCommit();
        $this
            ->_runTasks();
    }

    private function _reserveTasks()
    {
        $this->_taskMapper->reserveTasks($this->_limit);
        return $this;
    }

    private function _getReservedTasks()
    {
        $this->_tasks = $this->_taskMapper->getReservedTasks($this->_limit);
        return $this;
    }

    private function _runTasks()
    {
        if($this->_tasks->count() > 0) {
            $forker = new Forker();
            $forker->setRunner($this->getRunner());

            foreach ($this->_tasks as $task) {

                usleep(isset($this->_options['delay']) ? $this->_options['delay'] : 0);

                // begin transaction
                $this->_taskMapper->transactionBegin();

                // mark task as working
                $task->setStatus(Consts::STATUS_WORKING);

                $this->_taskMapper->update($task);

                $this->addOption('id', $task->getId());

                try {
                    $forker
                        ->setOptions($this->getOptions())
                        ->fork();
                } catch (\Exception $e) {
                    // rollback
                    $this->_taskMapper->transactionRollback();
                    // log message here
                    continue;
                }

                // commit
                $this->_taskMapper->transactionCommit();
            }
        }

        $this
            ->_timerStop()
            ->_writeLog();
    }

    private function _writeLog()
    {
        echo "Started: " . date(self::TIME_FORMAT, $this->_getTimerStart()) . "\n";
        echo "Execution time: " . ($this->_getTotalTime()) . "\n";
    }

    public function getRunner()
    {
        return $this->_runner;
    }

    public function setRunner($value)
    {
        $this->_runner = $value;
        return $this;
    }

    public function getOptions()
    {
        return $this->_options;
    }

    public function setOptions(array $value)
    {
        $this->_options = $value;
        return $this;
    }

    public function addOption($key, $value)
    {
        $this->_options[$key] = $value;
        return $this;
    }

    public function getLimit()
    {
        return $this->_limit;
    }

    public function setLimit($value)
    {
        $this->_limit = $value;
        return $this;
    }
}