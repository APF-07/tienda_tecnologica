import sys
import unittest
from pathlib import Path
import numpy as np
import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'ml'))
from predicciones_individuales import (ficha_recompra, modelo_recompra, variables_demanda,
                                      modelo_demanda_productos, fecha_cruce, pronosticar_panel)


class PrediccionesIndividualesTest(unittest.TestCase):
    def test_variables_recompra_no_usan_compras_futuras(self):
        p = pd.DataFrame({'id_cliente':[1,1,1], 'fecha_pedido':pd.to_datetime(['2026-01-01','2026-01-10','2026-02-01']), 'total':[10,20,999999]})
        x = ficha_recompra(p,pd.Timestamp('2026-01-15'))
        self.assertEqual(x.iloc[0]['compras_historial'],2)
        self.assertEqual(x.iloc[0]['monto_90'],30)

    def test_cliente_sin_recurrencia_no_recibe_porcentaje(self):
        p = pd.DataFrame({'id_cliente':[1], 'fecha_pedido':pd.to_datetime(['2026-01-01']), 'total':[10]})
        self.assertTrue(ficha_recompra(p,pd.Timestamp('2026-01-15')).empty)

    def test_recompra_historial_corto_es_insuficiente(self):
        p = pd.DataFrame({'id_cliente':[1,1], 'fecha_pedido':pd.to_datetime(['2026-01-01','2026-01-10']), 'total':[10,20], 'estado':['Entregado','Entregado']})
        r = modelo_recompra({'pedidos':p},pd.Timestamp('2026-01-15'))
        self.assertEqual(r['estado'],'insuficiente')
        self.assertEqual(r['predicciones'],{})

    def test_recompra_calibracion_temporal_y_poca_evidencia(self):
        rng = np.random.default_rng(42)
        fechas = pd.date_range('2026-01-01',periods=260)
        filas = [(cliente,fecha,100,'Entregado') for cliente in range(1,101)
                 for fecha in fechas if rng.random() < (.003 + cliente*.0008)]
        filas += [(999,fechas[-3],100,'Entregado'),(999,fechas[-1],100,'Entregado')]
        p = pd.DataFrame(filas,columns=['id_cliente','fecha_pedido','total','estado'])
        r = modelo_recompra({'pedidos':p},fechas[-1])
        self.assertEqual(r['estado'],'listo')
        self.assertLess(pd.Timestamp(r['metricas']['Inicio de calibración']) + pd.Timedelta(days=29),
                        pd.Timestamp(r['metricas']['Inicio de prueba']))
        self.assertTrue(r['predicciones']['999']['evidencia_limitada'])
        self.assertNotIn(999,[fila[0] for fila in r['tablas'][0]['filas']])
        for v in r['predicciones'].values():
            self.assertTrue(0 <= v['probabilidad_30d'] <= 100)
            self.assertLessEqual(v['recompras_calibracion_rango'],v['casos_calibracion_rango'])


    def test_variables_demanda_no_usan_el_objetivo_actual(self):
        serie = pd.Series(np.arange(40),index=pd.date_range('2026-01-01',periods=40))
        a = variables_demanda(serie)
        serie.iloc[-1] = 99999
        b = variables_demanda(serie)
        pd.testing.assert_series_equal(a.iloc[-1].drop('objetivo'),b.iloc[-1].drop('objetivo'))

    def test_fecha_sin_demanda_no_se_inventa(self):
        fechas = pd.date_range('2026-01-01',periods=30)
        self.assertIsNone(fecha_cruce(np.zeros(30),5,fechas))
        self.assertEqual(fecha_cruce(np.ones(30),5,fechas),'2026-01-05')

    def test_pronostico_recursivo_referencia_no_ve_futuro(self):
        fechas = pd.date_range('2026-01-01',periods=28)
        panel = pd.DataFrame({1:np.arange(28)},index=fechas)
        futuro = pd.date_range('2026-01-29',periods=10)
        p = pronosticar_panel(panel,futuro,'Referencia semanal')
        self.assertEqual(p.iloc[0,0],21)
        self.assertEqual(p.iloc[7,0],21)

    def tablas(self):
        fechas = pd.date_range('2026-01-01',periods=100)
        return {'pedidos':pd.DataFrame({'id_pedido':range(100),'fecha_pedido':fechas,'id_cliente':1,'estado':'Entregado','total':100}),
                'detalle_pedidos':pd.DataFrame({'id_pedido':range(100),'id_producto':1,'cantidad':[2+i%3 for i in range(100)]}),
                'productos':pd.DataFrame({'id_producto':[1,2],'stock':[20,10],'estado':[1,1]})}

    def test_seleccion_demanda_no_usa_prueba_final(self):
        tablas = self.tablas();corte=pd.Timestamp('2026-04-10')
        original = modelo_demanda_productos(tablas,corte)
        tablas['detalle_pedidos'].loc[93:,'cantidad'] *= 100
        cambiado = modelo_demanda_productos(tablas,corte)
        self.assertEqual(original['estado'],'listo')
        self.assertEqual(original['metricas']['Modelo recomendado'],cambiado['metricas']['Modelo recomendado'])
        self.assertEqual([r[:2] for r in original['tablas'][0]['filas']], [r[:2] for r in cambiado['tablas'][0]['filas']])
        self.assertNotIn('2',original['predicciones'])
        self.assertGreaterEqual(original['predicciones']['1']['unidades_7d'],0)
        serie = original['predicciones']['1']['serie']
        self.assertEqual(len(serie['fechas']),30)
        self.assertEqual(len(serie['stock_central']),30)
        self.assertTrue(np.all(np.diff(serie['stock_central']) <= 0))
        self.assertTrue(np.all(np.asarray(serie['stock_rapido']) <= np.asarray(serie['stock_central'])))
        self.assertTrue(np.all(np.asarray(serie['stock_lento']) >= np.asarray(serie['stock_central'])))


if __name__ == '__main__':
    unittest.main()
