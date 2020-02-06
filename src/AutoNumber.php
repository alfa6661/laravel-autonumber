<?php

namespace Alfa6661\AutoNumber;

use Alfa6661\AutoNumber\Models\AutoNumber as AutoNumberModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class AutoNumber
{
    /**
     * Generate unique name for autonumber identity.
     *
     * @param array $options
     * @return string
     */
    private function generateUniqueName(array $options)
    {
        return md5(serialize($options));
    }

    /**
     * Evaluate autonumber configuration.
     *
     * @param array $overrides
     * @return array
     */
    public function evaluateConfiguration(array $overrides = [])
    {
        $config = array_merge(
            Config::get('autonumber', []),
            $overrides
        );

        if (is_callable($config['format'])) {
            $config['format'] = call_user_func($config['format']);
        }

        foreach ($config as $key => $value) {
            if (is_null($value)) {
                throw new InvalidArgumentException($key.' param cannot be null');
            }
        }

        return $config;
    }

    /**
     * Return the next auto increment number.
     *
     * @param string $name
     * @return int
     */
    private function getNextNumber($name)
    {
        $autoNumber = AutoNumberModel::where('name', $name)->first();

        if ($autoNumber === null) {
            $autoNumber = new AutoNumberModel([
                'name' => $name,
                'number' => 1,
            ]);
        } else {
            $autoNumber->number += 1;
        }

        $autoNumber->save();

        return $autoNumber->number;
    }

    /**
     * Generate auto number.
     *
     * @param Model $model
     * @return bool
     */
    public function generate(Model $model)
    {
        $attributes = [];
        foreach ($model->getAutoNumberOptions() as $attribute => $options) {
            if (is_numeric($attribute)) {
                $attribute = $options;
                $options = [];
            }

            $config = $this->evaluateConfiguration($options);

            $uniqueName = $this->generateUniqueName(
                array_merge(
                    ['class' => get_class($model)],
                    Arr::except($config, ['onUpdate'])
                )
            );

            $autoNumber = $this->getNextNumber($uniqueName);

            if ($length = $config['length']) {
                $autoNumber = str_replace('?', str_pad($autoNumber, $length, '0', STR_PAD_LEFT), $config['format']);
            }

            $model->setAttribute($attribute, $autoNumber);

            $attributes[] = $attribute;
        }

        return $model->isDirty($attributes);
    }
}
