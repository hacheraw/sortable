<?php
declare(strict_types=1);

namespace Sortable\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;

/**
 * Sortable behavior.
 *
 * Permite ordenar filas de una tabla mediante un campo numérico
 */
class SortableBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'field' => 'position', // "order" es una palabra reservada, no debería usarse como columna en MySQL
        'start' => 1, // Primer elemento de la lista (desde dónde se empieza a contar)
        'step' => 1 // Cuánto sumar o restar
    ];

    /**
     * Initialize hook
     *
     * If events are specified - do *not* merge them with existing events,
     * overwrite the events to listen on
     *
     * @param array $config The config for this behavior.
     * @return void
     */
    public function initialize(array $config): void
    {
        if (empty($config['fields'])) {
            return;
        }

        $this->setConfig($config, false);
    }

    /**
     * Mueve un elemento al inicio
     *
     * @param \App\Model\Entity $id
     */
    public function toTop($id): bool
    {
        try {

            $field = $this->_config['field'];
            $row = $this->_table->get($id, ['fields' => ['id', $field]]);
            $currentVal = $row->{$field};
            $newVal = $this->_config['start'];

            if ($currentVal == $newVal) {
                return true;
            }

            // Suma un paso a todos los que estén por debajo
            $this->_change($currentVal, false);

            // Lo establece como el primero
            $row->{$field} = $newVal;
            $this->_table->save($row);

        } catch (\Throwable $th) {
            //TODO: Logear error
            return false;
        }

        return true;
    }

    /**
     * Mueve un elemento al final
     *
     * @param \App\Model\Entity $id
     */
    public function toBottom($id): bool
    {
        try {

            $field = $this->_config['field'];
            $row = $this->_table->get($id, ['fields' => ['id', $field]]);
            $currentVal = $row->{$field};
            $newVal = $this->getLast();

            if (!$field) {
                return false;
            }

            if ($currentVal == $newVal) {
                return true;
            }

            // Resta un paso a todos los que estén por encima
            $this->_change($currentVal);

            // Lo establece como el último
            $row->{$field} = $newVal;
            $this->_table->save($row);

        } catch (\Throwable $th) {
            //TODO: Logear error
            return false;
        }

        return true;
    }

    /**
     * Mueve un elemento a otra posición
     *
     * @param \App\Model\Entity $id
     * @param int $newVal
     * @param bool $moveOwn
     */
    public function move($id, $newVal, $moveOwn = true): bool
    {
        try {

            $step = $this->_config['step'];
            $field = $this->_config['field'];
            $row = $this->_table->get($id, ['fields' => ['id', $field]]);
            $currentVal = $row->{$field};

            if ($newVal == $currentVal) {
                return true; // No hacemos nada
            } else if ($newVal < $currentVal) {
                // Crea hueco para el movimiento; no modifica el original
                $this->_change([$newVal, $currentVal - $step], false);
            } else {
                // Crea hueco para el movimiento; no modifica el original
                $this->_change([$currentVal + $step, $newVal]);
            }

            // Le asigna la nueva posición
            if ($moveOwn) {
                $row->{$field} = $newVal;
                $this->_table->save($row);
            }

        } catch (\Throwable $th) {
            //TODO: Logear error
            return false;
        }

        return true;
    }

    /**
     * Devuelve el valor mínimo establecido
     *
     * @return int|float
     */
    public function getStart()
    {
        return $this->_config['start'];
    }

    /**
     * Devuelve el valor siguiente al más alto
     * Útil a la hora de crear nuevas entradas
     *
     * @return int|float
     */
    public function getNew()
    {
        return $this->getLast() + $this->_config['step'];
    }

    /**
     * Devuelve el valor que se ha de sumar o restar
     *
     * @return int|float
     */
    public function getStep()
    {
        return $this->_config['step'];
    }

    /**
     * Devuelve el valor más alto
     *
     * @return int|float
     */
    public function getLast()
    {
        $field = $this->_config['field'];
        return $this->_table->find('all', [
            'fields' => ['id', $field],
            'order' => ["{$field}" => 'DESC']
        ])->first()->{$field};
    }

    /**
     * Resta o suma un paso al valor de un campo.
     *
     * @param int|array $value el nuevo valor o un array con dos valores
     * @param bool $substract por defecto resta
     * @return void
     */
    private function _change($value, $substract = true)
    {
        $step = $this->_config['step'];
        $field = $this->_config['field'];
        $operator = $substract ? '-' : '+'; // Resta o suma
        $expression = new QueryExpression("`{$field}` = `{$field}` {$operator} {$step}");

        if (!is_array($value)) {
            // Modifica todos los que estén por encima o por debajo
            $operator = $substract ? '>' : '<';
            $this->_table->updateAll([$expression], ["{$field} {$operator}" => $value]);
        } else {
            // Modifica los que están dentro del rango
            $expression2 = new QueryExpression("`{$field}` BETWEEN {$value[0]} AND {$value[1]}");
            $this->_table->updateAll([$expression], [$expression2]);
        }
    }

    /**
     * Mueve todos los valores para insertar uno nuevo en medio de la lista
     *
     * @param int|array $value la posición donde se insertará
     * @return void
     */
    private function _insert($value)
    {
        $step = $this->_config['step'];
        $field = $this->_config['field'];
        $expression = new QueryExpression("`{$field}` = `{$field}` + {$step}");
        $this->_table->updateAll([$expression], ["{$field} >=" => $value]);
    }

    /**
     * Before save listener.
     *
     * @param \Cake\Event\EventInterface $event The beforeSave event that was fired
     * @param \Cake\Datasource\EntityInterface $entity the entity that is going to be saved
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity)
    {
        $default = $this->getNew();
        $field = $this->_config['field'];
        if ($entity->isNew()) { // Si es una nueva fila
            if ($entity->{$field} != $default) { // Si no se inserta al final
                $this->_insert($entity->{$field});
            }
        } else if ($entity->isDirty($field)) { // Si se ha modificado el orden
            $this->move($entity->id, $entity->{$field}, false);
        }
    }
}
