"""Casos sintéticos identificados: prueban código, no precisión comercial."""
import sys
import json
import unittest
from pathlib import Path
import pandas as pd
import numpy as np
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'ml'))
import controles_negocio as cn


class ControlesNegocioTest(unittest.TestCase):
    def setUp(self):
        self.corte=pd.Timestamp('2026-10-01')
        self.productos=pd.DataFrame([dict(id_producto=i,nombre=('Laptop portátil' if i<5 else 'Mouse ratón')+str(i),descripcion='Equipo informático' if i<5 else 'Accesorio USB',id_categoria=1 if i<5 else 2,estado=1,stock=20) for i in range(1,9)])
        p=[];d=[]
        for i,fecha in enumerate(pd.date_range(end=self.corte,periods=65)):
            p.append(dict(id_pedido=i+1,id_cliente=i+1,fecha_pedido=fecha,total=100,estado='Confirmado'))
            for pid in [1,5]:d.append(dict(id_pedido=i+1,id_producto=pid,cantidad=1,precio_unitario=50))
        self.tablas={'productos':self.productos,'pedidos':pd.DataFrame(p),'detalle_pedidos':pd.DataFrame(d)}

    def test_ventas_constantes_separacion_y_no_negativos(self):
        r=cn.ventas_producto(self.tablas,self.corte)
        self.assertLess(r['evidencia']['entrenamiento_hasta'],r['evidencia']['validacion_desde'])
        self.assertLess(r['evidencia']['validacion_desde'],r['evidencia']['prueba_desde'])
        self.assertEqual(r['metricas']['MAE prueba (S/)'],0)
        self.assertTrue(all(v>=0 for f in r['filas'] for v in f['valores']))
        json.dumps(r,allow_nan=False)

    def test_recomendaciones_no_recomiendan_origen_y_tienen_holdout(self):
        r=cn.recomendador(self.tablas,self.corte)
        self.assertGreater(r['evidencia']['cestas_prueba'],0)
        self.assertTrue(all(f['id'] not in f['recomendaciones'] for f in r['filas']))
        self.assertTrue(0<=r['metricas']['Recall@5']<=1)
        json.dumps(r,allow_nan=False)

    def test_detector_fixture_etiquetado(self):
        r=cn.prueba_anomalias()
        self.assertGreaterEqual(r['Recall'],.8)
        self.assertTrue(0<=r['F1']<=1)

    def test_anomalias_sin_etiquetas_no_se_aprueban(self):
        r=cn.anomalias(self.tablas,self.corte)
        self.assertEqual(r['estado'],'No ejecutado')
        self.assertIsNone(r['evidencia']['F1_real'])

    def test_clasificacion_pocos_datos_no_inventa_metricas(self):
        r=cn.clasificador(self.tablas)
        self.assertEqual(r['estado'],'No ejecutado')
        self.assertFalse(r['metricas'])

    def test_clasificacion_grupos_separados_y_metricas_finitas(self):
        filas=[]
        for i in range(60):
            categoria=1 if i<30 else 2
            filas.append(dict(id_producto=i+1,nombre=('Portátil pantalla' if categoria==1 else 'Ratón USB')+' '+chr(65+i//3)+chr(65+i%3),descripcion='Producto '+chr(65+i%26),id_categoria=categoria,estado=1,stock=20))
        t=dict(self.tablas);t['productos']=pd.DataFrame(filas)
        r=cn.clasificador(t)
        self.assertEqual(r['evidencia']['grupos_compartidos'],0)
        for valor in r['metricas'].values():self.assertTrue(0<=valor<=1)
        json.dumps(r,allow_nan=False)

    def test_ventas_historial_corto_no_predice(self):
        t=dict(self.tablas);t['pedidos']=t['pedidos'].tail(10)
        self.assertEqual(cn.ventas_producto(t,self.corte)['estado'],'No ejecutado')

    def test_error_columna_faltante_no_publica_resultado_falso(self):
        t=dict(self.tablas);t['detalle_pedidos']=t['detalle_pedidos'].drop(columns='precio_unitario')
        with self.assertRaises(KeyError):cn.lineas(t,self.corte)

    def test_demanda_insuficiente(self):
        t=dict(self.tablas);t['pedidos']=t['pedidos'].tail(10)
        self.assertEqual(cn.demanda(t,self.corte)['estado'],'No ejecutado')


if __name__=='__main__':unittest.main()
