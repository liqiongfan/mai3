<?php

namespace app\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;

/**
 * This is the model class for table "{{%file_upload_config}}".
 *
 * @property integer $id
 * @property integer $type
 * @property string $model_name
 * @property string $attribute
 * @property string $extensions
 * @property integer $min_size
 * @property integer $max_size
 * @property integer $thumb_width
 * @property integer $thumb_height
 * @property integer $tenant_id
 * @property integer $created_by
 * @property integer $created_at
 * @property integer $updated_by
 * @property integer $updated_at
 * @property integer $deleted_by
 * @property integer $deleted_at
 */
class FileUploadConfig extends BaseActiveRecord
{

    public $model_attribute;

    /**
     * Upload file types
     */
    const TYPE_FILE = 0;
    const TYPE_IMAGE = 1;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%file_upload_config}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['type', 'model_attribute', 'extensions', 'min_size', 'max_size'], 'required'],
            ['model_name', 'match', 'pattern' => '/^[a-zA-Z-]+$/'],
            ['extensions', 'match', 'pattern' => '/^[a-z0-9,]+$/'],
            ['attribute', 'match', 'pattern' => '/^[a-zA-Z0-9_]+$/'],
            ['attribute', 'checkAttribute'],
            [['model_name', 'attribute'], 'unique', 'targetAttribute' => ['model_name', 'attribute']],
            [['type', 'min_size', 'max_size', 'thumb_width', 'thumb_height', 'tenant_id', 'created_by', 'created_at', 'updated_by', 'updated_at', 'deleted_by', 'deleted_at'], 'integer'],
            ['min_size', 'default', 'value' => 100],
            ['max_size', 'default', 'value' => 200],
            ['max_size', 'checkMaxSize'],
            [['model_name', 'attribute', 'extensions'], 'string', 'max' => 255],
            ['model_attribute', 'safe'],
        ]);
    }

    public function checkMaxSize($attrirbute, $params)
    {
        if (!$this->hasErrors()) {
            if ($this->max_size < $this->min_size) {
                $this->addError('max_size', '文件最大值不能小于最小值。');
            }
        }
    }

    /**
     * @deprecated since version 1.0
     * Check attribute value is exists in db table columns, and must is `string` type.
     * @param string $attribute
     * @param array $params
     */
    public function checkAttribute($attribute, $params)
    {
        if (!empty($this->model_name) && !empty($this->attribute)) {
            $tableName = Yad::modelName2TableName(self::id2ClassName($this->model_name));
            if ($tableName) {
                $allTableNames = Yii::$app->getDb()->getSchema()->getTableNames('', true);
                if (in_array($tableName, $allTableNames)) {
                    $columns = Yii::$app->getDb()->getTableSchema($tableName)->columns;
                    if (!isset($columns[$this->attribute]) || $columns[$this->attribute]->type !== 'string') {
                        $this->addError($attribute, '{value} 在数据库（' . $tableName . '）中不存在或者字段为非字符型，禁止添加。');
                    }
                } else {
                    $this->addError($attribute, '数据库表（' . $tableName . '）不存在。');
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), [
            'type' => Yii::t('fileUploadConfig', 'Type'),
            'type_text' => Yii::t('fileUploadConfig', 'Type'),
            'model_attribute' => Yii::t('fileUploadConfig', 'Model Attribute'),
            'attribute' => Yii::t('fileUploadConfig', 'Attribute'),
            'extensions' => Yii::t('fileUploadConfig', 'Extensions'),
            'size' => Yii::t('fileUploadConfig', 'Size'),
            'min_size' => Yii::t('fileUploadConfig', 'Min Size'),
            'max_size' => Yii::t('fileUploadConfig', 'Max Size'),
            'thumb' => Yii::t('fileUploadConfig', 'Thumb'),
            'thumb_width' => Yii::t('fileUploadConfig', 'Thumb Width'),
            'thumb_height' => Yii::t('fileUploadConfig', 'Thumb Height'),
        ]);
    }

    public static function typeOptions()
    {
        return [
            self::TYPE_FILE => Yii::t('fileUploadConfig', 'File'),
            self::TYPE_IMAGE => Yii::t('fileUploadConfig', 'Image')
        ];
    }

    public function getType_text()
    {
        $options = self::typeOptions();

        return isset($options[$this->type]) ? $options[$this->type] : null;
    }

    /**
     * 默认配置
     *
     * @return array
     */
    public static function defaultConfig()
    {
        return [
            'extensions' => null,
            'size' => [
                'min' => 1,
                'max' => 204800, // 200 KB
            ],
            'thumb' => [
                'generate' => false,
            ],
        ];
    }

    /**
     * 返回指定的上传配置（可以返回多个）
     *
     * @param array $pairs
     * @return array
     */
    public static function getConfigs($pairs = [])
    {
        $cacheKey = static::className() . __FUNCTION__;
        $cache = Yii::$app->getCache();
        $cacheData = $cache->get($cacheKey);
        if ($cacheData === false) {
            $configs = [];
            foreach ($pairs as $key => $value) {
                $configs[$key . '@' . $value] = self::defaultConfig();
            }
            $rawData = Yii::$app->getDb()->createCommand('SELECT [[type]], [[model_name]], [[attribute]], [[extensions]], [[min_size]], [[max_size]], [[thumb_width]], [[thumb_height]] FROM ' . static::tableName() . ' WHERE [[tenant_id]] = :tenantId AND [[deleted_at]] IS NULL', [':tenantId' => Yad::getTenantId()])->queryAll();
            foreach ($rawData as $data) {
                $key = $data['model_name'] . '@' . $data['attribute'];
                $configs[$key] = [
                    'extensions' => !empty($data['extensions']) ? $data['extensions'] : null,
                    'size' => [
                        'min' => (int) $data['min_size'],
                        'max' => (int) $data['max_size'],
                    ],
                    'thumb' => [
                        'generate' => false,
                    ],
                ];
                if ($data['type'] == self::TYPE_IMAGE && $data['thumb_width'] && $data['thumb_height']) {
                    $configs[$key]['thumb'] = [
                        'generate' => true,
                        'width' => (int) $data['thumb_width'],
                        'height' => (int) $data['thumb_height'],
                    ];
                }
            }

            $cache->set($cacheKey, $configs, 0, new \yii\caching\DbDependency([
                'sql' => 'SELECT MAX(updated_at) FROM ' . static::tableName(),
            ]));

            return $configs;
        } else {
            return $cacheData;
        }
    }

    /**
     * 获取上传文件设置
     *
     * @param string $modelName
     * @param string $attribute
     * @return array
     */
    public static function getConfig($modelName, $attribute)
    {
        $configs = static::getConfigs();
        $key = "{$modelName}@{$attribute}";

        return isset($configs[$key]) ? $configs[$key] : static::defaultConfig();
    }

    /**
     * 获取有效模型名称列表
     *
     * @return array
     */
    public static function validModelNames()
    {
        $names = [];
        $contentModels = ArrayHelper::getValue(Yii::$app->params, 'contentModules', []);
        $rawData = Yii::$app->getDb()->createCommand('SELECT DISTINCT([[model_name]]) FROM ' . static::tableName() . ' WHERE [[tenant_id]] = :tenantId', [':tenantId' => Yad::getTenantId()])->queryColumn();
        foreach ($rawData as $name) {
            if (isset($contentModels[$name]['label'])) {
                $text = Yii::t("app", $contentModels[$name]['label']);
            } else {
                $text = $name;
            }

            $names[$name] = $text;
        }

        return $names;
    }

    /**
     * 获取可设置上传设定的模型和字段属性名称列表
     *
     * @return array
     */
    public static function modelAttributeOptions()
    {
        $options = [];
        $db = Yii::$app->getDb();
        $tablePrefix = $db->tablePrefix;
        $tableSchemas = $db->schema->tableSchemas;
        $modules = [];
        $modulesRawData = isset(Yii::$app->params['modules']) ? Yii::$app->params['modules'] : [];
        foreach ($modulesRawData as $ms) {
            $modules = array_merge($modules, $ms);
        }
        foreach ($tableSchemas as $tableSchema) {
            $rawColumns = $tableSchema->columns;
            $modelName = Inflector::id2camel(str_replace($tablePrefix, '', $tableSchema->name), '_');
            $modelName = 'app-models-' . $modelName;
            if (isset($modules[$modelName])) {
                $attributeLabels = Yii::createObject(BaseActiveRecord::id2ClassName($modelName))->attributeLabels();
                foreach ($rawColumns as $name => $column) {
                    if ($column->type === 'string' && strpos($name, '_path') !== false) {
                        $options[$modelName . ':' . $name] = '「' . Yii::t('app', $modules[$modelName]['label']) . '」' . (isset($attributeLabels[$name]) ? $attributeLabels[$name] : $name);
                    }
                }
            }
        }

        return $options;
    }

    // Events
    public function afterFind()
    {
        parent::afterFind();
        // Bit to KB
        $this->min_size /= 1024;
        $this->max_size /= 1024;

        $this->model_attribute = $this->model_name . ':' . $this->attribute;
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // KB To Bit
            $this->min_size *= 1024;
            $this->max_size *= 1024;

            $modelAttribute = explode(':', $this->model_attribute);
            $this->model_name = $modelAttribute[0];
            $this->attribute = $modelAttribute[1];

            return true;
        } else {
            return false;
        }
    }

}
