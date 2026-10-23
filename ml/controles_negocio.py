"""Cinco controles de negocio. Solo lectura de MySQL; no ejecuta decisiones."""
import hashlib
import json
import re
from collections import Counter
from itertools import combinations
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.ensemble import IsolationForest, RandomForestRegressor
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (accuracy_score, precision_recall_fscore_support,
                             mean_absolute_error, mean_squared_error)
from sklearn.model_selection import GroupShuffleSplit
from sklearn.pipeline import make_pipeline
from sklearn.metrics.pairwise import cosine_similarity
from predicciones_individuales import pedidos_validos, modelo_demanda_productos

RAIZ = Path(__file__).resolve().parent


def base(nombre, algoritmo, problema, datos, limite):
    return dict(nombre=nombre, algoritmo=algoritmo, problema=problema, datos=datos,
                limitacion=limite, estado='No ejecutado', metricas={}, filas=[], graficos=[],
                evidencia={}, motivo='Datos insuficientes para una evaluación separada.')


def lineas(tablas, corte):
    p = pedidos_validos(tablas, corte)
    d = tablas['detalle_pedidos'].merge(p[['id_pedido', 'fecha_pedido']], on='id_pedido')
    for c in ['cantidad', 'precio_unitario']:
        d[c] = pd.to_numeric(d[c], errors='coerce')
    d = d[(d.cantidad > 0) & (d.precio_unitario >= 0)].copy()
    d['dia'] = d.fecha_pedido.dt.normalize()
    d['importe'] = d.cantidad * d.precio_unitario
    return d


def variables(s):
    f = pd.DataFrame({'real': s})
    f['ayer'] = s.shift(1)
    f['semana'] = s.shift(7)
    f['media'] = s.shift(1).rolling(7).mean()
    f['dia'] = f.index.dayofweek
    return f.dropna()


def forecast(s, fechas, model):
    trabajo = s.copy(); pred = []
    for fecha in fechas:
        trabajo.loc[fecha] = 0.
        valor = max(0., float(model.predict(variables(trabajo).loc[[fecha]].drop(columns='real'))[0]))
        trabajo.loc[fecha] = valor; pred.append(valor)
    return np.array(pred)


def forecast_panel(panel, fechas, model):
    trabajo = panel.copy(); resultado = []
    for fecha in fechas:
        entradas = pd.DataFrame({'ayer':trabajo.iloc[-1], 'semana':trabajo.iloc[-7],
                                'media':trabajo.tail(7).mean(), 'dia':fecha.dayofweek})
        valores = np.maximum(0., model.predict(entradas))
        trabajo.loc[fecha] = valores; resultado.append(valores)
    return pd.DataFrame(resultado, index=fechas, columns=panel.columns)


def ventas_producto(tablas, corte):
    r = base('Ventas futuras por producto', 'Random Forest regularizado frente a referencia semanal',
             'Estimar ingresos por producto para planificar presupuesto.', 'Líneas confirmadas: fecha, producto, cantidad y precio histórico.',
             'Importes de productos sin envío; estados actuales del pedido. No mide pagos recibidos ni demanda no atendida.')
    d = lineas(tablas, corte)
    if d.empty: return r
    calendario = pd.date_range(d.dia.min(), corte)
    if len(calendario) < 56: return r
    panel = d.pivot_table(index='dia', columns='id_producto', values='importe', aggfunc='sum').reindex(calendario).fillna(0)
    activos = set(tablas['productos'].loc[tablas['productos'].estado == 1, 'id_producto'])
    ids = [i for i in panel if i in activos and (panel[i].iloc[:-14] > 0).sum() >= 8]
    if not ids: return r
    # Entrenamiento compartido entre productos; el ID no se usa como objetivo o etiqueta.
    def ajustar(fin):
        f = pd.concat([variables(panel[i].loc[:fin]) for i in ids], ignore_index=True)
        model = RandomForestRegressor(n_estimators=60, max_depth=6, min_samples_leaf=8, random_state=42, n_jobs=1)
        model.fit(f.drop(columns='real'), f.real)
        return model
    valid, test = calendario[-14:-7], calendario[-7:]
    mv, mt, mf = ajustar(calendario[-15]), ajustar(calendario[-8]), ajustar(corte)
    pv_panel = forecast_panel(panel[ids].iloc[:-14], valid, mv)
    pt_panel = forecast_panel(panel[ids].iloc[:-7], test, mt)
    futuro = pd.date_range(corte+pd.Timedelta(days=1), periods=7)
    pf_panel = forecast_panel(panel[ids], futuro, mf)
    reales, estimados, referencias = [], [], []
    for pid in ids:
        s = panel[pid]
        pv = pv_panel[pid].to_numpy()
        bv = s.reindex(valid-pd.Timedelta(days=7)).to_numpy()
        elegido = 'Random Forest' if mean_absolute_error(s.loc[valid], pv) < mean_absolute_error(s.loc[valid], bv) else 'Referencia semanal'
        real = s.loc[test].to_numpy(); referencia = s.reindex(test-pd.Timedelta(days=7)).to_numpy()
        pred = pt_panel[pid].to_numpy() if elegido == 'Random Forest' else referencia
        futuro_pred = pf_panel[pid].to_numpy() if elegido == 'Random Forest' else s.iloc[-7:].to_numpy()
        reales.extend(real); estimados.extend(pred); referencias.extend(referencia)
        r['filas'].append(dict(id=int(pid), modelo=elegido, ventas_7d=round(float(futuro_pred.sum()), 2),
            mae=round(float(mean_absolute_error(real, pred)), 2), fechas=futuro.strftime('%Y-%m-%d').tolist(),
            valores=futuro_pred.round(2).tolist(), fechas_prueba=test.strftime('%Y-%m-%d').tolist(),
            observados=real.round(2).tolist(), estimados=pred.round(2).tolist()))
    mae = float(mean_absolute_error(reales, estimados)); ref = float(mean_absolute_error(reales, referencias))
    r.update(estado='Aprobado' if mae < ref else 'Fallido', motivo='Aprobación: menor MAE de prueba que repetir la semana anterior. No garantiza resultados futuros.')
    r['metricas'] = {'MAE prueba (S/)': round(mae, 3), 'RMSE prueba (S/)': round(float(np.sqrt(mean_squared_error(reales, estimados))), 3),
                     'MAE referencia (S/)': round(ref, 3), 'Productos evaluados': len(ids)}
    r['evidencia'] = {'entrenamiento_hasta':str(calendario[-15].date()), 'validacion_desde':str(valid[0].date()),
                     'prueba_desde':str(test[0].date()), 'prueba_hasta':str(test[-1].date()), 'casos':len(reales)}
    return r


def demanda(tablas, corte):
    m = modelo_demanda_productos(tablas, corte)
    r = base('Demanda y reposición', m['algoritmo'], 'Detectar riesgo de stock insuficiente para siete días.',
             'Unidades confirmadas por producto y stock actual.', 'Sin stock histórico diario no se puede validar la exactitud de alertas reales de quiebre. Las ventas pueden estar censuradas por agotamientos.')
    if m['estado'] != 'listo': return r
    r['metricas'] = m['metricas']; r['evidencia'] = {'separacion':'7 días de validación y 7 posteriores de prueba; entrenamiento anterior.',
        'alertas_reales':'No ejecutado: falta reconstrucción fiable del stock disponible en cada fecha.'}
    for v in m['predicciones'].values():
        r['filas'].append(dict(id=v['id_producto'], stock=v['stock'], demanda_7d=v['unidades_7d'],
            reposicion=max(0, int(np.ceil(v['unidades_7d']-v['stock']))), alerta=v['unidades_7d'] > v['stock'],
            mae=v['mae_diario_prueba'], fecha=v['fecha_agotamiento']))
    mae=m['metricas']['MAE diario de prueba (unidades)']
    referencia=min(m['metricas']['MAE referencia semanal'],m['metricas']['MAE media de 7 días'])
    r['estado'] = 'Fallido' if mae >= referencia else 'No ejecutado'
    r['motivo'] = ('La demanda no mejora la mejor referencia en prueba. ' if mae >= referencia else 'Demanda evaluada. ')+'Alertas proyectadas operativas; validación de alertas reales no ejecutada por falta de stock histórico.'
    return r


def metricas_clase(real, pred):
    p, rec, f, _ = precision_recall_fscore_support(real, pred, average='binary', zero_division=0)
    return {'Precision':round(float(p), 4), 'Recall':round(float(rec), 4), 'F1':round(float(f), 4)}


def prueba_anomalias():
    # Etiquetas explícitas solo para demostrar mecanismo; nunca se mezclan con métricas del negocio.
    rng = np.random.default_rng(42)
    train = np.column_stack([rng.normal(100, 5, 150), rng.integers(1, 4, 150)])
    normales = np.column_stack([rng.normal(100, 4, 30), rng.integers(1, 4, 30)])
    anomalos = np.array([[5000+i*500, 100+i*10] for i in range(10)])
    model = IsolationForest(n_estimators=100, contamination=.05, random_state=42).fit(np.log1p(train))
    pred = model.predict(np.log1p(np.vstack([normales, anomalos]))) == -1
    return metricas_clase(np.array([0]*30+[1]*10), pred)


def anomalias(tablas, corte):
    r = base('Detección de anomalías', 'Isolation Forest', 'Priorizar operaciones inusuales para revisión; no acusa fraude.',
             'Importe, unidades y número de líneas por pedido confirmado.', 'Sin etiquetas verificadas del negocio no se puede medir precisión, recall ni F1 reales. No cubre movimientos manuales de stock sin trazabilidad uniforme.')
    d = lineas(tablas, corte)
    if d.empty: return r
    a = d.groupby('id_pedido').agg(importe=('importe','sum'), unidades=('cantidad','sum'), lineas=('id_producto','count'), fecha=('fecha_pedido','min')).sort_values(['fecha'])
    fechas = sorted(a.fecha.dt.normalize().unique())
    if len(fechas) < 10 or len(a) < 60: return r
    limite = pd.Timestamp(fechas[int(len(fechas)*.8)])
    train, test = a[a.fecha < limite], a[a.fecha >= limite]
    if len(train) < 40 or test.empty: return r
    cols = ['importe','unidades','lineas']
    model = IsolationForest(n_estimators=120, contamination=.03, random_state=42).fit(np.log1p(train[cols]))
    scores = -model.decision_function(np.log1p(test[cols])); flags = model.predict(np.log1p(test[cols])) == -1
    for (pid, row), score, flag in zip(test.iterrows(), scores, flags):
        if flag: r['filas'].append(dict(id=int(pid), fecha=str(row.fecha), importe=round(float(row.importe),2), unidades=int(row.unidades), puntuacion=round(float(score),4)))
    r['filas'].sort(key=lambda x:-x['puntuacion'])
    r['metricas'] = {'Pedidos entrenamiento':len(train), 'Pedidos de evaluación sin etiquetas':len(test), 'Operaciones inusuales':int(flags.sum())}
    demo = prueba_anomalias()
    r['evidencia'] = {'prueba_desde':str(limite.date()), 'prueba_sintetica':demo, 'casos_sinteticos':'150 normales de entrenamiento; 30 normales y 10 anómalos etiquetados de prueba.', 'F1_real':None}
    r.update(estado='No ejecutado', motivo='Detector operativo. Validación sintética '+('aprobada' if demo['F1'] >= .8 else 'fallida')+'; validación del negocio no ejecutada por falta de etiquetas confiables.')
    return r


def texto_productos(p):
    return (p.nombre.fillna('')+' '+p.descripcion.fillna('')).str.lower().str.replace(r'\s+', ' ', regex=True).str.strip()


def recomendador(tablas, corte):
    r = base('Recomendación de productos', 'Híbrido TF-IDF y coocurrencia de compras', 'Sugerir productos relacionados o complementarios.',
             'Nombres, descripciones y cestas confirmadas anteriores al corte.', 'Evalúa completar cestas futuras, no intención del usuario ni conversión. Metadatos actuales; no existe catálogo histórico versionado.')
    p = tablas['productos'][tablas['productos'].estado == 1].sort_values('id_producto').copy()
    if len(p) < 6: return r
    ids = p.id_producto.astype(int).tolist(); pos = {pid:i for i,pid in enumerate(ids)}
    try: x = TfidfVectorizer(ngram_range=(1,2), max_features=6000).fit_transform(texto_productos(p))
    except ValueError: return r
    similitud = cosine_similarity(x)
    d = lineas(tablas, corte)
    fechas = sorted(d.dia.unique())
    if len(fechas) < 10: return r
    limite = pd.Timestamp(fechas[int(len(fechas)*.8)])
    co = np.zeros((len(ids), len(ids)))
    for _, cesta in d[d.dia < limite].groupby('id_pedido'):
        for a,b in combinations(sorted(set(cesta.id_producto)&set(ids)),2):
            co[pos[a],pos[b]] += 1; co[pos[b],pos[a]] += 1
    co = np.divide(co, np.maximum(1, co.max(axis=1, keepdims=True)))
    afinidad = .4*similitud + .6*co
    def recomendar(contexto):
        indices = [pos[i] for i in contexto if i in pos]
        if not indices: return []
        scores = afinidad[indices].mean(axis=0); scores[indices] = -1
        return [ids[j] for j in np.argsort(-scores, kind='stable')[:5] if scores[j] >= 0]
    popularidad = d[d.dia < limite].groupby('id_producto').cantidad.sum().sort_values(ascending=False).index.tolist()
    hits = 0; casos = 0; denominador = 0; hits_base = 0
    for pedido, cesta in d[d.dia >= limite].groupby('id_pedido'):
        articulos = sorted(set(cesta.id_producto)&set(ids))
        if len(articulos) < 2: continue
        # Enmascaramiento reproducible; no optimizar con el producto oculto.
        oculto = articulos[int(hashlib.sha256(str(pedido).encode()).hexdigest(),16)%len(articulos)]
        rec = recomendar([i for i in articulos if i != oculto]); hits += int(oculto in rec); casos += 1; denominador += len(rec)
        base_rec = [i for i in popularidad if i in pos and i not in [a for a in articulos if a != oculto]][:5]
        hits_base += int(oculto in base_rec)
    r['filas'] = [dict(id=pid, recomendaciones=recomendar([pid])) for pid in ids]
    r['evidencia'] = {'prueba_desde':str(limite.date()), 'cestas_prueba':casos, 'aciertos':hits, 'protocolo':'Un producto oculto por cesta futura; coocurrencias aprendidas solo de fechas anteriores. K=5.'}
    if not casos: return r
    r['metricas'] = {'Precision@5':round(hits/denominador,4) if denominador else 0, 'Recall@5':round(hits/casos,4), 'Recall@5 popularidad':round(hits_base/casos,4), 'Cestas evaluadas':casos}
    r.update(estado='Aprobado' if hits > hits_base else 'Fallido', motivo='Aprobación: Recall@5 superior a popularidad en las mismas cestas reservadas. No certifica conversión ni relevancia comercial.')
    return r


def clasificador(tablas):
    r = base('Clasificación de productos', 'TF-IDF y regresión logística balanceada', 'Proponer categorías para revisión humana.',
             'Nombre y descripción como entrada; categoría actual como etiqueta.', 'Las categorías actuales pueden contener errores. La puntuación no está calibrada como confianza. Sin etiquetas externas revisadas no hay certificación de calidad.')
    p = tablas['productos'].copy(); texto = texto_productos(p)
    grupos = texto.str.replace(r'\d+', '#', regex=True)
    if len(p) < 30 or p.id_categoria.nunique() < 2 or grupos.nunique() < 6: return r
    split = GroupShuffleSplit(n_splits=1, test_size=.25, random_state=42)
    train, test = next(split.split(texto, p.id_categoria, grupos))
    if p.iloc[train].id_categoria.nunique() < 2: return r
    model = make_pipeline(TfidfVectorizer(ngram_range=(1,2), sublinear_tf=True, max_features=10000),
                          LogisticRegression(max_iter=1000, class_weight='balanced', random_state=42))
    try: model.fit(texto.iloc[train], p.iloc[train].id_categoria)
    except ValueError: return r
    pred = model.predict(texto.iloc[test]); y = p.iloc[test].id_categoria
    precision, recall, f1, _ = precision_recall_fscore_support(y, pred, average='macro', zero_division=0)
    acc = float(accuracy_score(y,pred)); mayoria = p.iloc[train].id_categoria.mode().iloc[0]
    baseline = float((y==mayoria).mean())
    r['metricas'] = {'Accuracy':round(acc,4), 'Precision macro':round(float(precision),4), 'Recall macro':round(float(recall),4), 'F1 macro':round(float(f1),4), 'Accuracy mayoría':round(baseline,4)}
    r['evidencia'] = {'productos_train':len(train), 'productos_test':len(test), 'grupos_compartidos':len(set(grupos.iloc[train])&set(grupos.iloc[test])),
                     'protocolo':'Nombres y descripciones normalizados por números se mantienen juntos; etiquetas de evaluación son categorías del catálogo, no auditoría independiente.'}
    # Mantener el modelo evaluado; no reajustar sobre test para generar propuestas.
    todos = model.predict(texto); probabilidades = model.predict_proba(texto).max(axis=1)
    test_ids = set(p.iloc[test].id_producto)
    for (_, prod), propuesta, score in zip(p.iterrows(), todos, probabilidades):
        r['filas'].append(dict(id=int(prod.id_producto), actual=int(prod.id_categoria), propuesta=int(propuesta), puntuacion=round(float(score),4),
            evaluacion=int(prod.id_producto) in test_ids,
            huella=hashlib.sha256((str(prod.nombre or '')+'\n'+str(prod.descripcion or '')).encode()).hexdigest()))
    r.update(estado='Aprobado' if acc > baseline and f1 >= .5 else 'Fallido', motivo='Criterio previo: accuracy superior a mayoría y F1 macro ≥ 0.50. Aprobación solo frente a etiquetas actuales; requiere revisión humana.')
    return r


def entrenar_controles():
    from modelos import leer_datos
    tablas, corte, generado, firma = leer_datos()
    informe = dict(schema_version=1, generado=generado, fecha_corte=str(corte.date()), datos_id=firma,
        origen='MySQL del proyecto académico; no representa una validación con datos reales de producción.', controles={})
    funciones = {'ventas':lambda:ventas_producto(tablas,corte), 'inventario':lambda:demanda(tablas,corte),
                 'anomalias':lambda:anomalias(tablas,corte), 'recomendaciones':lambda:recomendador(tablas,corte), 'clasificacion':lambda:clasificador(tablas)}
    for clave, funcion in funciones.items():
        print('Control de negocio '+clave+': evaluando...', flush=True)
        informe['controles'][clave] = funcion()
    destino = RAIZ/'cache_controles_negocio.json'; temporal = destino.with_suffix('.json.tmp')
    temporal.write_text(json.dumps(informe, ensure_ascii=False, indent=2, allow_nan=False), encoding='utf-8')
    temporal.replace(destino)
    return informe


if __name__ == '__main__':
    entrenar_controles()
