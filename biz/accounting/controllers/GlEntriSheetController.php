<?php

namespace biz\accounting\controllers;

use Yii;
use biz\accounting\models\GlHeader;
use biz\accounting\models\searchs\GlEntriSheet;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use biz\accounting\models\EntriSheetDtl;
use biz\accounting\models\GlDetail;
use yii\base\Model;

/**
 * GlEntriSheetController implements the CRUD actions for GlHeader model.
 */
class GlEntriSheetController extends Controller
{

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all GlHeader models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new GlEntriSheet();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
                'searchModel' => $searchModel,
                'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single GlHeader model.
     * @param  integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
                'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new GlHeader model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($es = null)
    {
        $model = new GlHeader();

        if (!empty($es)) {
            $details = [];
            foreach (EntriSheetDtl::findAll(['id_esheet' => $es]) as $eDtl) {
                /* @var $eDtl EntriSheetDtl */
                $details[$eDtl->nm_esheet_dtl] = new GlDetail(['id_coa' => $eDtl->id_coa]);
            }
            $model->populateRelation('glDetails', $details);
            $post = Yii::$app->request->post();
            if ($model->load($post)) {
                try {
                    $transaction = Yii::$app->db->beginTransaction();
                    $success = $model->save();
                    $success = $model->saveRelated('glDetails', $post, $success);
                    if ($success) {
                        $error = false;
                        $balance = 0.0;
                        foreach ($model->glDetails as $detail) {
                            $balance += $detail->amount;
                        }
                        if ($balance != 0) {
                            $model->addError('', 'Details should be balance');
                            $error = true;
                        }
                        //
                        if ($error) {
                            $transaction->rollBack();
                        } else {
                            $transaction->commit();

                            return $this->redirect(['view', 'id' => $model->id_gl]);
                        }
                    } else {
                        $transaction->rollBack();
                    }
                } catch (\Exception $exc) {
                    $transaction->rollBack();
                    $model->addError('', $exc->getMessage());
                }
            }
        }

        return $this->render('create', [
                'model' => $model,
                'es' => $es,
        ]);
    }

    /**
     * Deletes an existing GlHeader model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param  integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the GlHeader model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param  integer               $id
     * @return GlHeader              the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = GlHeader::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}