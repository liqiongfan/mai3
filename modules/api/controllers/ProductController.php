<?php

namespace app\modules\api\controllers;

use app\modules\api\helpers\Util;
use app\modules\api\models\Product;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\helpers\Inflector;
use yii\web\NotFoundHttpException;

/**
 * product 接口
 *
 * @author hiscaler <hiscaler@gmail.com>
 */
class ProductController extends Controller
{

    /**
     * 解析查询条件
     *
     * @param string $fields
     * @param string $orderBy
     * @param integer $offset
     * @param integer $limit
     * @return Query
     */
    private function parseQuery($fields, $orderBy = null, $offset = null, $limit = null)
    {
        $where = [];
        $selectColumns = Util::filterQuerySelectColumns(['t.id', 't.category_id', 't.type_id', 't.brand_id', 't.sn', 't.name', 't.market_price', 't.shop_price', 't.member_price', 't.keywords', 't.description', 't.online', 't.on_off_datetime', 't.view_require_credits', 't.ordering', 't.clicks_count', 't.sales_count', 't.status', 't.created_at', 't.created_by', 't.updated_at', 't.updated_by'], $fields);
        // Order By
        $orderByColumns = [];
        if (!empty($orderBy)) {
            $orderByColumnLimit = ['id', 'ordering', 'createdAt', 'updatedAt']; // Supported order by column names
            foreach (explode(',', trim($orderBy)) as $string) {
                if (!empty($string)) {
                    $string = explode('.', $string);
                    if (in_array($string[0], $orderByColumnLimit)) {
                        $orderByColumns['t.' . Inflector::camel2id($string[0], '_')] = isset($string[1]) && $string[1] == 'asc' ? SORT_ASC : SORT_DESC;
                    }
                }
            }
        }

        $query = (new ActiveQuery(Product::className()))
            ->select($selectColumns)
            ->from('{{%product}} t')
            ->where($where)
            ->offset($offset)
            ->limit($limit)
            ->orderBy($orderByColumns ?: ['t.id' => SORT_DESC]);

        return $query;
    }

    /**
     * 用户列表（带翻页数据）
     *
     * @param string $orderBy
     * @param integer $page
     * @param integer $pageSize
     * @return ActiveDataProvider
     */
    public function actionIndex($orderBy = null, $page = 1, $pageSize = 10)
    {
        return new ActiveDataProvider([
            'query' => $this->parseQuery(Yii::$app->getRequest()->get('fields'), $orderBy, null, null),
            'pagination' => [
                'page' => (int) $page - 1,
                'pageSize' => (int) $pageSize ?: 20
            ]
        ]);
    }

    /**
     * 列表（不带翻页数据）
     *
     * @param string $orderBy
     * @param integer $offset
     * @param integer $limit
     * @return ActiveDataProvider
     */
    public function actionList($orderBy = null, $offset = 0, $limit = 10)
    {
        return new ActiveDataProvider([
            'query' => $this->parseQuery(Yii::$app->getRequest()->get('fields'), $orderBy, $offset, $limit),
            'pagination' => false
        ]);
    }

    /**
     * 详情
     *
     * @param $id
     * @return array|null|\yii\db\ActiveRecord
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {
        $model = (new ActiveQuery(Product::className()))
            ->from('{{%product}}')
            ->where(['id' => (int) $id])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        return $model;
    }

}
