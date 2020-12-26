<?php
declare(strict_types=1);

namespace Sortable\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Database\Expression\QueryExpression;

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
        'field' => 'position' // "order" es una palabra reservada, no debería usarse como columna en MySQL
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
            $newVal = 0;

            if ($currentVal == $newVal) {
                return true;
            }

            // Suma 1 a todos los que estén por debajo
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
            $newVal = $this->_table->find('all', [
                'fields' => ['id', $field],
                'order' => ["{$field}" => 'DESC']
            ])->first()->{$field};

            if (!$field) {
                return false;
            }

            if ($currentVal == $newVal) {
                return true;
            }

            // Resta 1 a todos los que estén por encima
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
     */
    public function move($id, $newVal): bool
    {
        try {

            $field = $this->_config['field'];
            $row = $this->_table->get($id, ['fields' => ['id', $field]]);
            $currentVal = $row->{$field};

            if ($newVal == $currentVal) {
                return true; // No hacemos nada
            } else if ($newVal < $currentVal) {
                // Crea hueco para el movimiento; no modifica el original
                $this->_change([$newVal, $currentVal - 1], false);
            } else {
                // Crea hueco para el movimiento; no modifica el original
                $this->_change([$currentVal + 1, $newVal]);
            }

            // Le asigna la nueva posición
            $row->{$field} = $newVal;
            $this->_table->save($row);

        } catch (\Throwable $th) {
            //TODO: Logear error
            return false;
        }

        return true;
    }

    /**
     * Resta o suma 1 al valor de un campo.
     *
     * @param int|array $value el nuevo valor o un array con dos valores
     * @param bool $substract por defecto resta
     * @return void
     */
    private function _change($value, $substract = true)
    {
        $field = $this->_config['field'];
        $operator = $substract ? '-' : '+'; // Resta o suma
        $expression = new QueryExpression("`{$field}` = `{$field}` {$operator} 1");

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
}
