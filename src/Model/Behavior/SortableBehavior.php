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
        'group' => [], // Si hay asociaciones, hay que poner el foreign_key para que filtre correctamente. Ej: ['post_id']
        'start' => 1, // Primer elemento de la lista (desde dónde se empieza a contar)
        'step' => 1 // Cuánto sumar o restar
    ];

    protected $fields; // Campos para las búsquedas
    protected $row; // Entity a modificar

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
        $this->fields = array_merge(['id', $this->_config['field']], $this->_config['group']);
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
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};
            $newVal = $this->getStart();

            if ($currentVal == $newVal) {
                return true;
            }

            // Suma un paso a todos los que estén por debajo
            $this->_change($currentVal, false);

            // Lo establece como el primero
            $this->row->{$field} = $newVal;
            $this->_table->save($this->row);

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
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};
            $newVal = $this->getLast($this->_getConditions());

            if (!$field) {
                return false;
            }

            if ($currentVal == $newVal) {
                return true;
            }

            // Resta un paso a todos los que estén por encima
            $this->_change($currentVal);

            // Lo establece como el último
            $this->row->{$field} = $newVal;
            $this->_table->save($this->row);

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

            $step = $this->getStep();
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};

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
                $this->row->{$field} = $newVal;
                $this->_table->save($this->row);
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
    public function getNew($conditions = [])
    {
        return $this->getLast($conditions) + $this->getStep();
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
     * @param array $conditions
     * @return int|float
     */
    public function getLast($conditions = [])
    {
        $field = $this->_config['field'];
        $query = $this->_table->find('all', [
            'fields' => $this->fields,
            'order' => ["{$field}" => 'DESC']
        ]);

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        return $query->first()->{$field} ?? 0;
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
        $step = $this->getStep();
        $field = $this->_config['field'];
        $operator = $substract ? '-' : '+'; // Resta o suma
        $expression = new QueryExpression("`{$field}` = `{$field}` {$operator} {$step}");
        $conditions = $this->_getConditions();

        if (!is_array($value)) {
            // Modifica todos los que estén por encima o por debajo
            $operator = $substract ? '>' : '<';
            $this->_table->updateAll([$expression], array_merge($conditions, ["{$field} {$operator}" => $value]));
        } else {
            // Modifica los que están dentro del rango
            $between = ["{$field} >=" => $value[0], "{$field} <=" => $value[1]];
            $conditions = array_merge($between, $conditions);
            $this->_table->updateAll([$expression], $conditions);
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
        $step = $this->getStep();
        $field = $this->_config['field'];
        $expression = new QueryExpression("`{$field}` = `{$field}` + {$step}");
        $this->_table->updateAll([$expression], array_merge($this->_getConditions(), ["{$field} >=" => $value]));
    }

    /**
     * Devuelve condiciones para el Where
     *
     * @param \App\Model\Entity $entity
     * @return void
     */
    private function _getConditions()
    {
        $group = $this->_config['group'];
        $conditions = [];
        foreach ($group as $column) {
            $conditions[$column] = $this->row->{$column};
        }

        return $conditions;
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
        $this->row = $entity;
        $default = $this->getNew($this->_getConditions());
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
