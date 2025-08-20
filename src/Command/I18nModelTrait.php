<?php
declare(strict_types=1);

namespace ADmad\I18n\Command;

use Cake\Console\Arguments;
use Cake\ORM\Table;

/**
 * Trait to get model
 */
trait I18nModelTrait
{
    /**
     * Model instance for saving translation messages.
     *
     * @var \Cake\ORM\Table
     */
    protected Table $_model;

    /**
     * Get translation model.
     *
     * @param \Cake\Console\Arguments $args The Arguments instance
     * @return \Cake\ORM\Table
     */
    protected function _loadModel(Arguments $args): Table
    {
        $model = $args->getOption('model') ?: (defined('static::DEFAULT_MODEL') ? static::DEFAULT_MODEL : 'I18nMessages');
        return $this->_model = $this->getTableLocator()->get($model);
    }
}
