"""Predicciones individuales con separación temporal y referencias explícitas."""
import numpy as np
import pandas as pd
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import brier_score_loss, roc_auc_score, mean_absolute_error

ESTADOS = ['Confirmado', 'En preparación', 'Enviado', 'Entregado']


def pedidos_validos(tablas, corte):
    p = tablas['pedidos'].copy()
    p['fecha_pedido'] = pd.to_datetime(p['fecha_pedido'])
    p['total'] = pd.to_numeric(p['total'], errors='coerce').fillna(0)
    return p[p['estado'].isin(ESTADOS) & (p['fecha_pedido'] < corte + pd.Timedelta(days=1))]


def ficha_recompra(pedidos, corte):
    """Las variables nunca utilizan compras posteriores al punto de observación."""
    limite = pd.Timestamp(corte) + pd.Timedelta(days=1)
    pasado = pedidos[pedidos['fecha_pedido'] < limite].copy()
    filas = []
    for cliente, compras in pasado.groupby('id_cliente'):
        fechas = compras['fecha_pedido'].sort_values()
        if len(fechas) < 2:
            continue
        reciente = compras[compras['fecha_pedido'] >= limite - pd.Timedelta(days=90)]
        filas.append({'id_cliente': int(cliente), 'recencia': max(0, (limite - fechas.iloc[-1]).days),
                      'frecuencia_90': len(reciente), 'monto_90': float(reciente['total'].sum()),
                      'intervalo_medio': float(fechas.diff().dropna().dt.total_seconds().mean() / 86400),
                      'antiguedad': max(1, (limite - fechas.iloc[0]).days), 'compras_historial': len(compras)})
    return pd.DataFrame(filas, columns=['id_cliente','recencia','frecuencia_90','monto_90','intervalo_medio','antiguedad','compras_historial'])


def modelo_recompra(tablas, corte):
    corte = pd.Timestamp(corte).normalize()
    p = pedidos_validos(tablas, corte)
    resultado = {'tipo_modelo': 'recompra', 'nombre': 'Probabilidad de recompra en 30 días',
        'algoritmo': 'Regresión logística con calibración sigmoide temporal', 'estado': 'insuficiente',
        'descripcion': 'Estima si un cliente con al menos dos compras confirmadas volverá a comprar durante los 30 días posteriores al corte.',
        'metricas': {}, 'graficos': [], 'tablas': [], 'predicciones': {},
        'metodologia': ['Variables: recencia, frecuencia e importe de 90 días, intervalo y antigüedad.',
            'Las etiquetas observan 30 días completos posteriores a cada fecha de observación.',
            'Entrenamiento, calibración y prueba en periodos separados; cada etiqueta termina antes del siguiente periodo.',
            'La calibración ajusta puntuaciones a frecuencias observadas. Se limita la extrapolación al rango de puntuaciones calibrado, sin imponer un tope porcentual arbitrario.',
            'No se reajusta con la prueba final: se conserva el modelo evaluado y su calibrador.',
            'Regla de publicación conservadora: al menos cinco compras, 30 días de antigüedad y 30 casos en el rango de calibración. No equivale a significación estadística individual.',
            'El mismo cliente puede aparecer en fechas anteriores: evalúa clientes conocidos en el futuro, no clientes nuevos.',
            'Brier mide error probabilístico (menor es mejor). AUC no es porcentaje de acierto.',
            'Probabilidades estimadas sin garantía individual; no se afirma calibración externa. Datos académicos.']}
    if p.empty:
        return resultado
    primera = p['fecha_pedido'].min().normalize()
    anclas = list(pd.date_range(primera + pd.Timedelta(days=60), corte - pd.Timedelta(days=30), freq='30D'))
    if len(anclas) < 4:
        resultado['descripcion'] += ' Se necesitan aproximadamente 180 días y ambas clases en periodos separados.'
        return resultado
    muestras = []
    for ancla in anclas:
        x = ficha_recompra(p, ancla)
        futuro = p[(p['fecha_pedido'] >= ancla + pd.Timedelta(days=1)) & (p['fecha_pedido'] < ancla + pd.Timedelta(days=31))]
        x['objetivo'] = x['id_cliente'].isin(futuro['id_cliente']).astype(int)
        x['ancla'] = ancla
        muestras.append(x)
    datos = pd.concat(muestras, ignore_index=True)
    prueba = datos[datos['ancla'] == anclas[-1]]
    calibracion = datos[datos['ancla'] == anclas[-2]]
    train = datos[datos['ancla'] + pd.Timedelta(days=30) <= anclas[-2]]
    columnas = ['recencia','frecuencia_90','monto_90','intervalo_medio','antiguedad']
    if any(len(frame) < minimo or frame['objetivo'].nunique() < 2 or frame['objetivo'].value_counts().min() < 5
           for frame, minimo in [(train,50),(calibracion,50),(prueba,20)]):
        return resultado
    modelo = make_pipeline(StandardScaler(), LogisticRegression(C=0.3, max_iter=1000, random_state=42))
    modelo.fit(train[columnas], train['objetivo'])
    score_cal = modelo.decision_function(calibracion[columnas])
    calibrador = LogisticRegression(C=1.0, max_iter=1000, random_state=42)
    calibrador.fit(score_cal.reshape(-1,1), calibracion['objetivo'])
    minimo, maximo = float(score_cal.min()), float(score_cal.max())
    def probabilidad(frame):
        scores = modelo.decision_function(frame[columnas])
        return calibrador.predict_proba(np.clip(scores,minimo,maximo).reshape(-1,1))[:,1]
    probabilidades = probabilidad(prueba)
    raw = modelo.predict_proba(prueba[columnas])[:,1]
    prob_cal = probabilidad(calibracion)
    base = float(train['objetivo'].mean())
    resultado['metricas'] = {'Observaciones de entrenamiento': len(train), 'Clientes de calibración':len(calibracion), 'Clientes de prueba': len(prueba),
        'Brier de prueba': round(float(brier_score_loss(prueba['objetivo'], probabilidades)),4),
        'Brier sin calibrar': round(float(brier_score_loss(prueba['objetivo'],raw)),4),
        'Brier referencia constante': round(float(brier_score_loss(prueba['objetivo'], np.repeat(base,len(prueba)))),4),
        'Recompra observada en calibración (%)':round(float(calibracion['objetivo'].mean()*100),2),
        'ROC AUC de prueba': round(float(roc_auc_score(prueba['objetivo'], probabilidades)),4),
        'Inicio de calibración':str(anclas[-2].date()),
        'Inicio de prueba': str(anclas[-1].date()), 'Final de etiquetas de prueba': str((anclas[-1]+pd.Timedelta(days=30)).date())}
    resultado['estado'] = 'listo'
    # Se conserva exactamente el modelo calibrado que se evaluó fuera de muestra.
    actual = ficha_recompra(p, corte)
    prob = probabilidad(actual) if len(actual) else []
    for (_, fila), valor in zip(actual.iterrows(), prob):
        grupo = min(4,int(valor*5))
        similares = np.minimum(4,(prob_cal*5).astype(int)) == grupo
        casos = int(similares.sum())
        positivos = int(calibracion.loc[similares,'objetivo'].sum())
        resultado['predicciones'][str(int(fila['id_cliente']))] = {'id_cliente': int(fila['id_cliente']),
            'probabilidad_30d': round(float(valor)*100,4), 'compras_historial': int(fila['compras_historial']),
            'recencia_dias': int(fila['recencia']), 'desde': str((corte+pd.Timedelta(days=1)).date()),
            'hasta': str((corte+pd.Timedelta(days=30)).date()),
            'evidencia_limitada': bool(fila['compras_historial']<5 or fila['antiguedad']<30 or casos<30),
            'casos_calibracion_rango':casos,'recompras_calibracion_rango':positivos,
            'rango_calibracion': [grupo*20,(grupo+1)*20]}
    mejores = sorted([r for r in resultado['predicciones'].values() if not r['evidencia_limitada']],key=lambda r:-r['probabilidad_30d'])[:10]
    resultado['tablas'] = [{'titulo':'Clientes con mayor probabilidad estimada · consultar cualquier ID debajo',
        'columnas':['Cliente','Recompra en 30 días (%)','Compras históricas','Recencia (días)'],
        'filas':[[r['id_cliente'],('>99.9' if r['probabilidad_30d']>=99.95 else '<0.1' if r['probabilidad_30d']<0.05 else round(r['probabilidad_30d'],1)),r['compras_historial'],r['recencia_dias']] for r in mejores]}]
    return resultado


def variables_demanda(serie):
    frame = pd.DataFrame(index=serie.index)
    frame['lag_1'] = serie.shift(1)
    frame['lag_7'] = serie.shift(7)
    frame['media_7'] = serie.shift(1).rolling(7).mean()
    frame['media_28'] = serie.shift(1).rolling(28).mean()
    frame['dia_semana'] = frame.index.dayofweek
    frame['objetivo'] = serie
    return frame


def pronosticar_panel(panel, fechas, nombre, modelo=None):
    historia = panel.copy().astype(float)
    valores = []
    for fecha in fechas:
        entradas = pd.DataFrame({'lag_1': historia.iloc[-1], 'lag_7': historia.iloc[-7],
            'media_7': historia.tail(7).mean(), 'media_28': historia.tail(28).mean(), 'dia_semana': fecha.dayofweek})
        if nombre == 'Random Forest':
            pred = modelo.predict(entradas[['lag_1','lag_7','media_7','media_28','dia_semana']])
        elif nombre == 'Referencia semanal':
            pred = historia.iloc[-7].to_numpy()
        else:
            pred = historia.tail(7).mean().to_numpy()
        pred = np.maximum(0, pred)
        historia.loc[fecha] = pred
        valores.append(pred)
    return pd.DataFrame(valores,index=fechas,columns=panel.columns)


def fecha_cruce(valores, stock, fechas):
    if stock <= 0:
        return str(fechas[0].date())
    indices = np.flatnonzero(np.cumsum(valores) >= stock)
    return str(fechas[int(indices[0])].date()) if len(indices) else None


def modelo_demanda_productos(tablas, corte):
    corte = pd.Timestamp(corte).normalize()
    r = {'tipo_modelo':'demanda_producto','nombre':'Demanda por producto y agotamiento estimado',
        'algoritmo':'Random Forest y referencias temporales', 'estado':'insuficiente',
        'descripcion':'Pronostica unidades por producto y estima agotamiento con stock actual, suponiendo que no habrá reposición.',
        'metricas':{},'graficos':[],'tablas':[],'predicciones':{},
        'metodologia':['Productos activos con al menos 56 días y 8 días de venta antes de validar.',
            'Modelo global de unidades diarias: rezagos de 1 y 7 días, medias previas de 7 y 28 y día de semana. No utiliza stock actual como variable de entrenamiento.',
            'Selección: siete días de validación; evaluación: siete días posteriores separados. Predicción recursiva sin observar ventas futuras.',
            'Se compara Random Forest con repetición semanal y media de siete días; puede ganar una referencia sencilla.',
            'La fecha central usa demanda acumulada a 30 días; si no alcanza el stock no se inventa una fecha.',
            'El rango usa demanda diaria más/menos el MAE de prueba por producto: escenario orientativo, no intervalo de confianza calibrado.',
            'Stock actual y ausencia de reposición. Pedidos históricos clasificados por su estado disponible, sin historial de estados ni de quiebres de stock.',
            'Ventas observadas no equivalen a toda la demanda: un producto pudo no vender por falta de existencias.']}
    p = pedidos_validos(tablas,corte)
    if p.empty or tablas['detalle_pedidos'].empty:
        return r
    d = tablas['detalle_pedidos'].merge(p[['id_pedido','fecha_pedido']],on='id_pedido')
    d['dia'] = d['fecha_pedido'].dt.normalize()
    d['cantidad'] = pd.to_numeric(d['cantidad'],errors='coerce').fillna(0).clip(lower=0)
    inicio = p['fecha_pedido'].min().normalize()
    if (corte-inicio).days < 70:
        return r
    panel = d.pivot_table(index='dia',columns='id_producto',values='cantidad',aggfunc='sum').reindex(pd.date_range(inicio,corte)).fillna(0)
    activos = set(tablas['productos'].loc[tablas['productos']['estado']==1,'id_producto'])
    base = panel.iloc[:-14]
    ids = [i for i in panel.columns if i in activos and (base[i]>0).sum()>=8 and base[i].sum()>=20
           and (base.index[-1]-base.index[base[i]>0][0]).days>=55]
    if not ids:
        return r
    panel = panel[ids]
    columnas = ['lag_1','lag_7','media_7','media_28','dia_semana']
    def ajustar(hasta):
        datos = pd.concat([variables_demanda(panel[i].loc[:hasta]).dropna().tail(120) for i in ids],ignore_index=True)
        rf = RandomForestRegressor(n_estimators=60,max_depth=10,min_samples_leaf=12,random_state=42,n_jobs=1)
        rf.fit(datos[columnas],datos['objetivo'])
        return rf
    fin_train = panel.index[-15]
    rf = ajustar(fin_train)
    validacion = panel.index[-14:-7]
    errores = {}
    for nombre in ['Random Forest','Referencia semanal','Media de 7 días']:
        pred = pronosticar_panel(panel.loc[:fin_train],validacion,nombre,rf)
        errores[nombre] = float(mean_absolute_error(panel.loc[validacion].to_numpy().ravel(),pred.to_numpy().ravel()))
    elegido = min(errores,key=lambda k:(errores[k],k=='Random Forest'))
    test = panel.index[-7:]
    rf_test = ajustar(panel.index[-8])
    comparacion = {}; pred_test = None
    for nombre in errores:
        pred = pronosticar_panel(panel.iloc[:-7],test,nombre,rf_test)
        comparacion[nombre] = float(mean_absolute_error(panel.loc[test].to_numpy().ravel(),pred.to_numpy().ravel()))
        if nombre==elegido:
            pred_test=pred
    mae_individual = (pred_test-panel.loc[test]).abs().mean()
    futuro = pd.date_range(corte+pd.Timedelta(days=1),periods=30)
    rf_final = ajustar(corte)
    pred_final = pronosticar_panel(panel,futuro,elegido,rf_final)
    for _, prod in tablas['productos'][tablas['productos']['estado']==1].iterrows():
        pid=int(prod['id_producto'])
        if pid not in ids:
            continue
        valores=pred_final[pid].to_numpy();error=float(mae_individual[pid]);stock=int(prod['stock'])
        r['predicciones'][str(pid)]={'id_producto':pid,'stock':stock,'modelo':elegido,'unidades_7d':round(float(valores[:7].sum()),2),
            'unidades_30d':round(float(valores.sum()),2),'fecha_agotamiento':fecha_cruce(valores,stock,futuro),
            'fecha_escenario_rapido':fecha_cruce(valores+error,stock,futuro),
            'fecha_escenario_lento':fecha_cruce(np.maximum(0,valores-error),stock,futuro),
            'mae_diario_prueba':round(error,3),'horizonte_hasta':str(futuro[-1].date()),'agotado':stock==0,
            'serie':{'fechas':futuro.strftime('%Y-%m-%d').tolist(),'demanda':valores.round(4).tolist(),
                     'stock_central':np.maximum(0,stock-np.cumsum(valores)).round(4).tolist(),
                     'stock_rapido':np.maximum(0,stock-np.cumsum(valores+error)).round(4).tolist(),
                     'stock_lento':np.maximum(0,stock-np.cumsum(np.maximum(0,valores-error))).round(4).tolist()}}
    r['estado']='listo';r['metricas']={'Productos evaluados':len(ids),'Días de validación':7,'Días de prueba':7,
        'Modelo recomendado':elegido,'MAE diario de prueba (unidades)':round(comparacion[elegido],3),
        'MAE referencia semanal':round(comparacion['Referencia semanal'],3),'MAE media de 7 días':round(comparacion['Media de 7 días'],3)}
    r['tablas']=[{'titulo':'Comparación por unidades diarias · menor MAE es mejor','columnas':['Método','MAE validación','MAE prueba'],
        'filas':[[nombre,round(errores[nombre],3),round(comparacion[nombre],3)] for nombre in errores]},
        {'titulo':'Productos con fecha de agotamiento estimada más próxima · consultar cualquier ID debajo',
         'columnas':['Producto','Stock','Unidades estimadas en 7 días','Fecha central'],
         'filas':[[v['id_producto'],v['stock'],v['unidades_7d'],'Ya agotado' if v['agotado'] else v['fecha_agotamiento'] or 'No estimada en 30 días'] for v in sorted(r['predicciones'].values(),key=lambda v:v['fecha_agotamiento'] or '9999')[:10]]}]
    return r
