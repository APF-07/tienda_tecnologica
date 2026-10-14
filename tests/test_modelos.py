import sys
import tempfile
import unittest
from pathlib import Path

import numpy as np
import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'ml'))
import modelos


class ModelosTest(unittest.TestCase):
    def setUp(self):
        self.temporal = tempfile.TemporaryDirectory()
        self.raiz_original = modelos.RAIZ
        modelos.RAIZ = Path(self.temporal.name)
        (modelos.RAIZ / 'artefactos').mkdir()
        self.hoy = pd.Timestamp('2026-10-12')

    def tearDown(self):
        modelos.RAIZ = self.raiz_original
        self.temporal.cleanup()

    def tablas(self, n=40, estado='Confirmado'):
        fechas = pd.date_range(end=self.hoy, periods=n)
        pedidos = pd.DataFrame([{'id_pedido':i+1, 'id_cliente':i+1, 'fecha_pedido':f,
            'total':float(100+i%7), 'estado':estado, 'metodo_pago':'Contraentrega'} for i,f in enumerate(fechas)])
        return {'pedidos':pedidos,
            'clientes':pd.DataFrame({'id_cliente':range(1,n+1)}),
            'productos':pd.DataFrame({'id_producto':[1], 'nombre':['Equipo'], 'precio':[100.0], 'stock':[10], 'estado':[1]}),
            'detalle_pedidos':pd.DataFrame({'id_pedido':range(1,n+1), 'id_producto':[1]*n,'cantidad':[1]*n})}

    def test_variables_solo_usan_el_pasado(self):
        serie = pd.Series(np.arange(12,dtype=float),index=pd.date_range('2026-10-01',periods=12))
        original = modelos.caracteristicas_serie(serie).iloc[-1].drop('objetivo')
        serie.iloc[-1] = 100000
        modificado = modelos.caracteristicas_serie(serie).iloc[-1].drop('objetivo')
        pd.testing.assert_series_equal(original,modificado)

    def test_historial_corto_no_inventa_predicciones(self):
        resultado = modelos.modelo_pronostico(self.tablas(10),self.hoy)
        self.assertEqual(resultado['estado'],'insuficiente')
        self.assertEqual(resultado['graficos'],[])

    def test_pronostico_evalua_fechas_y_no_da_negativos(self):
        resultado = modelos.modelo_pronostico(self.tablas(),self.hoy,True)
        self.assertEqual(resultado['estado'],'listo')
        grafico = resultado['graficos'][0]
        self.assertEqual(len(grafico['datos']['valores']),7)
        self.assertTrue(all(v>=0 for v in grafico['datos']['valores']))
        self.assertEqual(grafico['datos']['labels'][0],'2026-10-13')
        evaluacion = resultado['graficos'][1]
        self.assertEqual(evaluacion['datos']['labels'][-1],'2026-10-12')
        self.assertEqual(len(evaluacion['datos']['labels']),resultado['metricas']['Días de prueba'])

    def test_clustering_identico_no_fabrica_grupos(self):
        df = pd.DataFrame({'precio':[100]*10,'stock':[5]*10})
        self.assertIsNone(modelos.clustering(df,['precio','stock'],'Prueba','test.joblib'))

    def test_clientes_sin_compras_confirmadas(self):
        resultado = modelos.modelo_clientes(self.tablas(12,'Pendiente'),self.hoy)[0]
        self.assertEqual(resultado['estado'],'insuficiente')
        self.assertEqual(resultado['metricas']['Clientes con compras'],0)
        self.assertEqual(resultado['metricas']['Sin compras confirmadas'],12)

    def test_cancelacion_no_entrena_con_estados_pendientes(self):
        resultado = modelos.modelo_cancelacion(self.tablas(40,'Pendiente'))
        self.assertEqual(resultado['estado'],'insuficiente')
        self.assertEqual(resultado['metricas']['Pedidos con resultado conocido'],0)

    def test_validacion_cancelacion_separa_clientes(self):
        tablas = self.tablas(60)
        tablas['pedidos']['estado']=['Entregado' if i%2 else 'Cancelado' for i in range(60)]
        resultado = modelos.modelo_cancelacion(tablas)
        self.assertEqual(resultado['estado'],'listo')
        self.assertIn('Clientes compartidos entre entrenamiento y prueba: 0.',resultado['metodologia'])
        self.assertGreaterEqual(resultado['metricas']['ROC AUC'],0)
        self.assertLessEqual(resultado['metricas']['ROC AUC'],1)

    def test_referencia_mediana_resiste_un_pico(self):
        tablas = self.tablas(40)
        tablas['pedidos']['total']=0.0
        tablas['pedidos'].loc[30,'total']=100000.0
        resultado = modelos.modelo_pronostico(tablas,self.hoy,True)
        comparacion = next(t['filas'] for t in resultado['tablas'] if t['titulo']=='Evaluación final por ventanas de hasta 7 días')
        mediana = next(r for r in comparacion if r[0]=='Mediana histórica')
        self.assertEqual(mediana[1],0.0)

    def test_porcentaje_tolerancia_y_ceros(self):
        metricas = modelos.evaluar_regresion([100,100,0,0], [120,121,0,1])
        self.assertEqual(metricas['dentro_20'],50.0)
        self.assertEqual(metricas['wape'],21.0)
        self.assertIsNone(modelos.evaluar_regresion([0,0],[1,2])['wape'])

    def test_recursion_no_usa_observaciones_futuras(self):
        fechas = pd.date_range('2026-01-01', periods=30)
        serie = pd.Series(np.arange(30,dtype=float)+10,index=fechas)
        futuro = fechas[-7:]
        columnas = list(modelos.caracteristicas_serie(serie).drop(columns='objetivo').columns)
        class Ayer:
            def predict(self, entrada):
                return entrada['lag_1'].to_numpy()
        pred = modelos.predecir_bloque(serie.iloc[:-7],futuro,'Prueba',Ayer(),columnas)
        self.assertTrue(np.all(pred == serie.iloc[-8]))

    def test_prueba_no_se_utiliza_para_elegir_modelo(self):
        tablas = self.tablas(70)
        original = modelos.modelo_pronostico(tablas,self.hoy,True)
        n_test = original['metricas']['Días de prueba']
        tablas['pedidos'].loc[tablas['pedidos'].index[-n_test:], 'total'] = 1000000.0
        cambiado = modelos.modelo_pronostico(tablas,self.hoy,True)
        self.assertEqual(original['metricas']['Modelo recomendado'],cambiado['metricas']['Modelo recomendado'])
        self.assertEqual(original['tablas'][0],cambiado['tablas'][0])
        calidad = cambiado['calidad']
        self.assertGreaterEqual(calidad['porcentaje'],0)
        self.assertLessEqual(calidad['porcentaje'],100)
        self.assertEqual(calidad['dias'],n_test)


if __name__ == '__main__':
    unittest.main()
