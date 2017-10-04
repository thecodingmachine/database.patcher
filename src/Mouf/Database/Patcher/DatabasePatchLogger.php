<?php


namespace Mouf\Database\Patcher;


use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Mouf\Utils\Patcher\Dumper\DumperInterface;

class DatabasePatchLogger implements SQLLogger
{

    /**
     * @var DumperInterface
     */
    private $dumper;

    /**
     * @var AbstractPlatform
     */
    private $platform;

    public function __construct(DumperInterface $dumper, AbstractPlatform $platform)
    {

        $this->dumper = $dumper;
        $this->platform = $platform;
    }

    /**
     * Code borrowed from Zend Framework 2. Thanks guys. => https://github.com/tburschka/zf2-doctrine-sql-logger/blob/1.0.0/src/ZF2DoctrineSQLLogger/ZF2DoctrineSQLLogger.php
     *
     * @param string $sql
     * @param array $params
     * @param array $types
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        if (strpos($sql, 'SELECT ') === 0) {
            return;
        }
        if (strpos($sql, 'SHOW ') === 0) {
            return;
        }

        $errors = '';
        $assembled = $sql;
        if(!empty($params)) {
            foreach ($params as $key => $param) {
                if ($param === null) {
                    $assembled = implode('NULL', explode('?', $assembled, 2));
                } else {
                    $type = $this->mapType($types, $key, $param);
                    if (null === $type) {
                        $errors .= 'Param could not be prepared: key: "' . $key
                            . '", value "' .  var_export($param, true) . '"!'."\n";
                        $assembled = implode('?', explode('?', $assembled, 2));
                    } else {
                        $value = $type->convertToDatabaseValue($param, $this->platform);
                        $assembled = implode($this->prepareValue($type, $value), explode('?', $assembled, 2));
                    }
                }
            }
        }
        $this->dumper->dumpPatch($errors.$assembled.';');
    }

    /**
     * @param $type Type
     * @param $value mixed
     * @return mixed
     */
    protected function prepareValue($type, $value)
    {
        if (is_object($type)) {
            switch(get_class($type)) {
                case 'Doctrine\DBAL\Types\SimpleArrayType':
                    break;
                default:
                    $value = var_export($value, true);
                    break;
            }
        } else {
            $value = var_export($value, true);
        }
        return $value;
    }

    /**
     * @param $types mixed
     * @param $key mixed
     * @param $param mixed
     * @return Type|null
     */
    protected function mapType($types, $key, $param)
    {
        // map type name by doctrine types map
        $name = $this->mapByTypesMap($types, $key);
        // map type name for known numbers
        if (is_null($name)) {
            $name = $this->mapByKeyNumber($key);
        }
        // map type name for known param type
        if (is_null($name)) {
            $name = $this->mapByParamType($param);
        }
        // if type could not be mapped, return null
        if (is_null($name)) {
            return null;
        }
        return Type::getType($name);
    }

    /**
     * Map by Doctrine DBAL types map
     * @param $types
     * @param $key
     * @return null|string
     */
    protected function mapByTypesMap($types, $key)
    {
        $typesMap = Type::getTypesMap();
        if (array_key_exists($key, $types) && array_key_exists($types[$key], $typesMap)) {
            $name = $types[$key];
        } else {
            $name = null;
        }
        return $name;
    }

    /**
     * @param $key
     * @return null|string
     */
    protected function mapByKeyNumber($key)
    {
        switch($key) {
            case 2:
                $name = Type::STRING;
                break;
            case 102:
                $name = Type::SIMPLE_ARRAY;
                break;
            default:
                $name = null;
                break;
        }
        return $name;
    }

    /**
     * @param $param
     * @return null|string
     */
    protected function mapByParamType($param)
    {
        switch(gettype($param)) {
            case 'array':
                $name = Type::SIMPLE_ARRAY;
                break;
            case 'string':
                $name = Type::STRING;
                break;
            case 'integer':
                $name = Type::INTEGER;
                break;
            default:
                $name = null;
                break;
        }
        return $name;
    }

    /**
     * Marks the last started query as stopped. This can be used for timing of queries.
     *
     * @return void
     */
    public function stopQuery()
    {
    }
}
