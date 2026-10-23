"""Modelos reproducibles sobre una instantánea de MySQL/MariaDB."""
import hashlib
import json
import os
from pathlib import Path

os.environ.setdefault('OMP_NUM_THREADS', '1')

import joblib
import mysql.connector
import numpy as np
import pandas as pd
import sklearn
from sklearn.cluster import KMeans
from sklearn.compose import ColumnTransformer, TransformedTargetRegressor
from sklearn.dummy import DummyClassifier
from sklearn.ensemble import RandomForestClassifier, RandomForestRegressor, ExtraTreesRegressor, GradientBoostingRegressor
from sklearn.impute import SimpleImputer
from sklearn.linear_model import Ridge, HuberRegressor
from sklearn.metrics import (balanced_accuracy_score, brier_score_loss, f1_score, precision_score, recall_score,
                             mean_absolute_error, mean_squared_error, r2_score,
                             roc_auc_score, silhouette_score)
from sklearn.model_selection import StratifiedGroupKFold
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from predicciones_individuales import modelo_recompra, modelo_demanda_productos
from diagnostico_pronostico import diagnosticar_pronostico

RAIZ = Path(__file__).resolve().parent
SEMILLA = 42


def leer_datos():
    db = mysql.connector.connect(host=os.getenv('DB_HOST', 'localhost'),
        port=int(os.getenv('DB_PORT', '3306')), user=os.getenv('DB_USER', 'root'),
        password=os.getenv('DB_PASSWORD', ''), database=os.getenv('DB_NAME', 'tienda_tecnologica'))
    cursor = db.cursor(dictionary=True)
    try:
        cursor.execute("SET time_zone = '-05:00'")
        cursor.execute('START TRANSACTION WITH CONSISTENT SNAPSHOT')
        cursor.execute('SELECT CURRENT_DATE hoy, NOW() generado')
        reloj = cursor.fetchone()
        tablas = {}
        for nombre in ('productos', 'clientes', 'pedidos', 'detalle_pedidos'):
            cursor.execute(f'SELECT * FROM {nombre}')
            tablas[nombre] = pd.DataFrame(cursor.fetchall(), columns=cursor.column_names)
        firma = hashlib.sha256()
        for nombre, df in tablas.items():
            firma.update(df.sort_values(df.columns[0]).to_json(date_format='iso').encode('utf-8'))
        return tablas, pd.Timestamp(reloj['hoy']) - pd.Timedelta(days=1), str(reloj['generado']), firma.hexdigest()[:16]
    finally:
        cursor.close()
        db.close()


def grafico(titulo, tipo, labels, valores, series=None):
    g = {'titulo': titulo, 'tipo': tipo, 'datos': {'labels': list(labels), 'valores': list(valores)}}
    if series:
        g['series'] = series
    return g


def clustering(datos, columnas, etiqueta, archivo):
    if len(datos) < 3:
        return None
    x = np.log1p(datos[columnas].astype(float).clip(lower=0))
    scaler = StandardScaler()
    x = scaler.fit_transform(x)
    unicos = len(np.unique(x, axis=0))
    candidatos = []
    for k in range(2, min(5, unicos, len(datos) - 1) + 1):
        modelo = KMeans(n_clusters=k, random_state=SEMILLA, n_init=20)
        labels = modelo.fit_predict(x)
        if len(np.unique(labels)) < 2:
            continue
        score = float(silhouette_score(x, labels, sample_size=min(1000, len(x)), random_state=SEMILLA))
        candidatos.append((score, k, modelo))
    if not candidatos:
        return None
    score, k, modelo = max(candidatos, key=lambda c: (c[0], -c[1]))
    resultado = datos.copy()
    resultado['grupo'] = modelo.labels_
    perfiles = resultado.groupby('grupo')[columnas].mean().sort_values(columnas[-1])
    orden = list(perfiles.index)
    nombres = {grupo: f'Grupo {i + 1}' for i, grupo in enumerate(orden)}
    joblib.dump({'scaler': scaler, 'modelo': modelo, 'columnas': columnas, 'nombres': nombres}, RAIZ/'artefactos'/archivo)
    return {'score': score, 'k': k, 'filas': resultado, 'perfiles': perfiles,
            'orden': orden, 'nombres': nombres,
            'comparacion': [[kk, round(ss, 4)] for ss, kk, _ in candidatos]}


def modelo_productos(tablas, hoy):
    p = tablas['productos'].copy()
    p = p[p['estado'].astype(int) == 1]
    for campo in ('precio', 'stock'):
        p[campo] = pd.to_numeric(p[campo], errors='coerce').fillna(0).clip(lower=0)
    grupos = clustering(p, ['precio', 'stock'], 'Inventario', 'inventario.joblib')
    modelo = {'nombre': 'Segmentación del inventario', 'algoritmo': 'K-Means + StandardScaler',
        'estado': 'listo' if grupos else 'insuficiente',
        'descripcion': 'Agrupa productos con precio y stock similares. El número de grupos se elige por su separación medida con Silhouette.',
        'metricas': {'Productos analizados': len(p)}, 'graficos': [], 'tablas': [],
        'metodologia': ['Transformación log1p para reducir el efecto de precios extremos.',
            'Estandarización de precio y stock antes de calcular distancias.',
            'Comparación de 2 a 5 grupos; selección por mayor Silhouette.',
            'Silhouette mide separación de grupos; no es un porcentaje de precisión.']}
    if grupos:
        modelo['metricas'].update({'Grupos seleccionados': grupos['k'], 'Silhouette': round(grupos['score'], 4)})
        conteos = grupos['filas']['grupo'].value_counts()
        modelo['graficos'] = [grafico('Productos por grupo', 'doughnut',
            [grupos['nombres'][i] for i in grupos['orden']], [int(conteos[i]) for i in grupos['orden']])]
        modelo['tablas'] = [{'titulo': 'Perfil de los grupos', 'columnas': ['Grupo', 'Productos', 'Precio medio (S/)', 'Stock medio'],
            'filas': [[grupos['nombres'][i], int(conteos[i]), round(float(grupos['perfiles'].loc[i, 'precio']), 2),
                        round(float(grupos['perfiles'].loc[i, 'stock']), 1)] for i in grupos['orden']]},
            {'titulo': 'Selección del número de grupos', 'columnas': ['Grupos', 'Silhouette'], 'filas': grupos['comparacion']}]
    pedidos = tablas['pedidos'].copy()
    pedidos['fecha_pedido'] = pd.to_datetime(pedidos['fecha_pedido'])
    confirmados = pedidos[pedidos['estado'].isin(['Confirmado','En preparación','Enviado','Entregado']) &
                         (pedidos['fecha_pedido'] >= hoy - pd.Timedelta(days=29)) & (pedidos['fecha_pedido'] < hoy + pd.Timedelta(days=1))]
    detalles = tablas['detalle_pedidos']
    ventas = detalles[detalles['id_pedido'].isin(confirmados['id_pedido'])].groupby('id_producto')['cantidad'].sum()
    reposicion = []
    for _, producto in p.iterrows():
        unidades = int(ventas.get(producto['id_producto'], 0))
        sugerida = max(0, int(np.ceil(unidades / 30 * 14)) - int(producto['stock']))
        if sugerida:
            reposicion.append([str(producto['nombre']), int(producto['stock']), unidades, sugerida])
    modelo['tablas'].append({'titulo': 'Reposición orientativa para 14 días (promedio de ventas de 30 días)',
        'columnas': ['Producto', 'Stock', 'Vendidas en 30 días', 'Unidades sugeridas'],
        'filas': sorted(reposicion, key=lambda r: -r[3])[:10]})
    return [modelo]


def modelo_clientes(tablas, hoy):
    pedidos = tablas['pedidos'].copy()
    pedidos['fecha_pedido'] = pd.to_datetime(pedidos['fecha_pedido'])
    pedidos['total'] = pd.to_numeric(pedidos['total'], errors='coerce').fillna(0)
    pedidos = pedidos[pedidos['estado'].isin(['Confirmado','En preparación','Enviado','Entregado']) &
                      (pedidos['fecha_pedido'] < hoy + pd.Timedelta(days=1))]
    rfm = pedidos.groupby('id_cliente').agg(frecuencia=('id_pedido','count'), monto=('total','sum'), ultima=('fecha_pedido','max')).reset_index()
    rfm['recencia'] = (hoy - rfm['ultima'].dt.normalize()).dt.days.clip(lower=0)
    grupos = clustering(rfm, ['recencia','frecuencia','monto'], 'Clientes', 'clientes_rfm.joblib')
    modelo = {'nombre': 'Segmentación de clientes RFM', 'algoritmo': 'K-Means sobre recencia, frecuencia y monto',
        'estado': 'listo' if grupos else 'insuficiente',
        'descripcion': 'Detecta patrones de compra a partir del historial confirmado. Los grupos describen comportamiento observado; no etiquetan abandono sin evidencia.',
        'metricas': {'Clientes con compras': len(rfm), 'Sin compras confirmadas': len(tablas['clientes']) - len(rfm)},
        'graficos': [], 'tablas': [], 'metodologia': ['Recencia: días desde la última compra confirmada.',
            'Frecuencia: número de pedidos confirmados por cliente.', 'Monto: suma de pedidos confirmados, incluyendo envío.',
            'log1p + StandardScaler; selección de 2 a 5 grupos por Silhouette.']}
    if grupos:
        modelo['metricas'].update({'Segmentos': grupos['k'], 'Silhouette': round(grupos['score'], 4)})
        conteos = grupos['filas']['grupo'].value_counts()
        modelo['graficos'] = [grafico('Clientes por segmento', 'doughnut',
            [grupos['nombres'][i] for i in grupos['orden']], [int(conteos[i]) for i in grupos['orden']])]
        modelo['tablas'] = [{'titulo': 'Perfil RFM de cada segmento',
            'columnas': ['Grupo','Clientes','Recencia (días)','Compras medias','Monto medio (S/)'],
            'filas': [[grupos['nombres'][i], int(conteos[i]), round(float(grupos['perfiles'].loc[i,'recencia']),1),
                round(float(grupos['perfiles'].loc[i,'frecuencia']),2), round(float(grupos['perfiles'].loc[i,'monto']),2)] for i in grupos['orden']]},
            {'titulo': 'Evaluación de segmentos', 'columnas': ['Grupos','Silhouette'], 'filas': grupos['comparacion']}]
    return [modelo]


def caracteristicas_serie(serie):
    df = pd.DataFrame({'objetivo': serie})
    df['lag_1'] = serie.shift(1)
    df['lag_7'] = serie.shift(7)
    df['media_7'] = serie.shift(1).rolling(7).mean()
    df['dia_semana'] = df.index.dayofweek
    df['tendencia'] = np.arange(len(df))
    return df.dropna()


def evaluar_regresion(real, prediccion):
    real = np.asarray(real, dtype=float)
    prediccion = np.asarray(prediccion, dtype=float)
    error = np.abs(real - prediccion)
    return {'mae': round(float(mean_absolute_error(real,prediccion)),3),
        'rmse': round(float(np.sqrt(mean_squared_error(real,prediccion))),3),
        'r2': round(float(r2_score(real,prediccion)),3) if len(real)>1 and np.var(real)>0 else None,
        'dentro_20': round(float(np.mean(error <= np.abs(real)*.20)*100),1),
        'wape': round(float(error.sum()/np.abs(real).sum()*100),1) if np.abs(real).sum()>0 else None}


def predecir_bloque(historial, fechas, nombre, estimador, columnas):
    """Predicción recursiva: no incorpora valores observados del bloque futuro."""
    trabajo = historial.copy()
    mediana = float(historial.median())
    por_dia = historial.groupby(historial.index.dayofweek).median().to_dict()
    valores = []
    for fecha in fechas:
        trabajo.loc[fecha] = 0.0
        if nombre == 'Referencia semanal':
            valor = float(trabajo.loc[fecha-pd.Timedelta(days=7)])
        elif nombre == 'Mediana histórica':
            valor = mediana
        elif nombre == 'Mediana por día de semana':
            valor = float(por_dia.get(fecha.dayofweek, mediana))
        else:
            entrada = caracteristicas_serie(trabajo).loc[[fecha], columnas]
            valor = float(estimador.predict(entrada)[0])
        valor = max(0, valor)
        trabajo.loc[fecha] = valor
        valores.append(valor)
    return np.asarray(valores)


def evaluar_bloques(serie, fechas, nombre, estimador, columnas):
    """Reajusta con el pasado al inicio de cada bloque de hasta siete días."""
    predicciones, ventanas = [], []
    for inicio in range(0, len(fechas), 7):
        bloque = fechas[inicio:inicio+7]
        pasado = serie.loc[serie.index < bloque[0]]
        frame = caracteristicas_serie(pasado)
        if estimador is not None:
            estimador.fit(frame[columnas], frame['objetivo'])
        pred = predecir_bloque(pasado, bloque, nombre, estimador, columnas)
        predicciones.extend(pred.tolist())
        ventanas.append({'inicio':str(bloque[0].date()),'fin':str(bloque[-1].date()),
                         **evaluar_regresion(serie.loc[bloque],pred)})
    return np.asarray(predicciones), ventanas


def modelo_pronostico(tablas, hoy, ventas=False):
    pedidos = tablas['pedidos'].copy()
    pedidos['fecha_pedido'] = pd.to_datetime(pedidos['fecha_pedido'])
    pedidos['total'] = pd.to_numeric(pedidos['total'], errors='coerce').fillna(0)
    estados = ['Confirmado','En preparación','Enviado','Entregado'] if ventas else ['Pendiente','Confirmado','En preparación','Enviado','Entregado']
    pedidos = pedidos[pedidos['estado'].isin(estados) & (pedidos['fecha_pedido'] < hoy + pd.Timedelta(days=1))]
    nombre = 'Pronóstico de ventas' if ventas else 'Pronóstico de volumen de pedidos'
    unidad = 'S/' if ventas else 'pedidos'
    modelo = {'nombre': nombre, 'algoritmo': 'Ridge, Huber, Random Forest, Extra Trees y Gradient Boosting; referencias estadísticas',
        'estado': 'insuficiente', 'descripcion': 'Selecciona el modelo en una validación temporal y lo evalúa después en fechas reservadas. La prueba final no se usa para elegir el modelo.',
        'metricas': {}, 'graficos': [], 'tablas': [], 'metodologia': ['Serie diaria completa, incluidos días sin pedidos.',
            'Variables: valores de ayer y hace 7 días, media previa de 7 días, día de semana y tendencia.',
            'Prueba final: últimos 7 días (o 20% si es mayor). Validación previa de 7 a 21 días; selección sin utilizar la prueba final.',
            'MAE y RMSE se expresan en '+unidad+'. R² puede ser negativo cuando el modelo no generaliza bien.',
            'La referencia semanal repite el valor observado 7 días antes.']}
    if pedidos.empty:
        modelo['descripcion'] = 'No hay pedidos suficientes para construir una serie temporal.'
        return modelo
    agregada = pedidos.groupby(pedidos['fecha_pedido'].dt.normalize())['total'].sum() if ventas else pedidos.groupby(pedidos['fecha_pedido'].dt.normalize()).size()
    indice = pd.date_range(agregada.index.min(), hoy, freq='D')
    serie = agregada.reindex(indice, fill_value=0).astype(float)
    frame = caracteristicas_serie(serie)
    modelo['metricas'] = {'Días de historial': len(serie), 'Días con actividad': int((serie>0).sum())}
    if len(frame)<20:
        modelo['descripcion'] = 'Se necesitan al menos 27 días de historial para comparar modelos temporales con este conjunto de variables.'
        return modelo
    n_test = max(7,int(np.ceil(len(frame)*.2)))
    train, test = frame.iloc[:-n_test], frame.iloc[-n_test:]
    n_validacion = min(21, max(7, len(train)//4))
    ajuste, validacion = train.iloc[:-n_validacion], train.iloc[-n_validacion:]
    columnas = [c for c in frame.columns if c!='objetivo']
    candidatos = {'Ridge': make_pipeline(StandardScaler(),Ridge(alpha=10)),
                  'Random Forest': RandomForestRegressor(n_estimators=200,max_depth=5,min_samples_leaf=2,random_state=SEMILLA,n_jobs=1),
                  'Huber robusto':TransformedTargetRegressor(regressor=make_pipeline(StandardScaler(),HuberRegressor(max_iter=1000)),transformer=StandardScaler()),
                  'Extra Trees':ExtraTreesRegressor(n_estimators=100,max_depth=5,min_samples_leaf=2,random_state=SEMILLA,n_jobs=1),
                  'Gradient Boosting':GradientBoostingRegressor(n_estimators=80,max_depth=2,learning_rate=.05,loss='huber',random_state=SEMILLA)}
    nombres = list(candidatos) + ['Referencia semanal','Mediana histórica','Mediana por día de semana']
    seleccion, comparacion, predicciones, ventanas_test = {}, {}, {}, {}
    for nombre_modelo in nombres:
        estimador = candidatos.get(nombre_modelo)
        pred_val, _ = evaluar_bloques(serie, validacion.index, nombre_modelo, estimador, columnas)
        seleccion[nombre_modelo] = evaluar_regresion(validacion['objetivo'], pred_val)
    simplicidad = {'Mediana histórica':0,'Referencia semanal':1,'Mediana por día de semana':2,'Ridge':3,'Huber robusto':4,'Random Forest':5,'Extra Trees':6,'Gradient Boosting':7}
    elegido = min(seleccion,key=lambda clave: (seleccion[clave]['mae'],simplicidad[clave]))
    recomendado = elegido
    solicitado = os.getenv('ML_MODELO_VENTAS' if ventas else 'ML_MODELO_PEDIDOS', 'automatico')
    if solicitado != 'automatico':
        if solicitado not in candidatos:
            raise ValueError('Modelo manual no permitido: '+solicitado)
        elegido = solicitado
    modelo['control'] = {'modo':'Automático' if solicitado=='automatico' else 'Manual',
        'recomendado':recomendado,'utilizado':elegido,'familias_ml':5,'candidatos':len(nombres),
        'motivo':('Menor MAE en validación temporal; en empate se prefiere el método más simple.' if solicitado=='automatico'
            else 'Selección explícita del administrador. La recomendación automática se conserva para comparar.'),
        'mae_validacion_elegido':seleccion[elegido]['mae'],'mae_validacion_recomendado':seleccion[recomendado]['mae']}
    mejor_ml = min(candidatos,key=lambda clave: seleccion[clave]['mae'])
    # El modelo se elige antes de consultar los resultados del bloque de prueba.
    for nombre_modelo in nombres:
        pred, ventanas = evaluar_bloques(serie, test.index, nombre_modelo, candidatos.get(nombre_modelo), columnas)
        comparacion[nombre_modelo] = evaluar_regresion(test['objetivo'], pred)
        predicciones[nombre_modelo] = pred
        ventanas_test[nombre_modelo] = ventanas
    evaluacion = comparacion[elegido]
    referencia = comparacion['Referencia semanal']['mae']
    modelo['decision'] = {'supera_referencia_prueba':evaluacion['mae']<referencia,
        'mejora_mae_porcentaje':round((referencia-evaluacion['mae'])/referencia*100,1) if referencia>0 else None,
        'uso':'Orientar turnos, capacidad de despacho y presupuesto; confirmar con resultados reales antes de comprometer recursos.',
        'advertencia':('No mejora la referencia semanal en la prueba reservada. Evita usar este pronóstico como única base de una decisión.' if evaluacion['mae']>=referencia else
            'Superó la referencia semanal en la prueba histórica; esto no garantiza el resultado futuro.')}
    modelo['estado']='listo'
    modelo['calidad'] = {
        'porcentaje': evaluacion['dentro_20'], 'titulo':'Días dentro del margen de ±20%',
        'dias': len(test), 'ventanas':len(ventanas_test[elegido]),
        'aciertos':int(np.sum(np.abs(test['objetivo'].to_numpy()-predicciones[elegido]) <= np.abs(test['objetivo'].to_numpy())*.20)),
        'explicacion':'Porcentaje de días de prueba cuya estimación quedó a ±20% del valor observado. Cuando el valor real es cero, solo cuenta una predicción de cero. No es la probabilidad de acertar una predicción futura.'}
    modelo['metricas'].update({'Modelo recomendado': elegido, 'Recomendación automática':recomendado, 'Mejor modelo ML': mejor_ml,
        'Días de entrenamiento inicial': len(ajuste), 'Días de validación':len(validacion), 'Días de prueba': len(test),
        'Ventanas de prueba':len(ventanas_test[elegido]),
        'MAE de prueba': evaluacion['mae'], 'RMSE de prueba': evaluacion['rmse'],
        'Error ponderado WAPE (%)':evaluacion['wape']})
    columnas_tabla = ['Modelo','MAE ('+unidad+')','RMSE ('+unidad+')','R²','Días dentro de ±20% (%)','WAPE (%)']
    modelo['tablas']=[{'titulo':'Selección con validación temporal por ventanas', 'columnas':columnas_tabla,
        'filas':[[clave,valor['mae'],valor['rmse'],valor['r2'],valor['dentro_20'],valor['wape']] for clave,valor in seleccion.items()]},
        {'titulo':'Evaluación final por ventanas de hasta 7 días', 'columnas':columnas_tabla,
        'filas':[[clave,valor['mae'],valor['rmse'],valor['r2'],valor['dentro_20'],valor['wape']] for clave,valor in comparacion.items()]},
        {'titulo':'Estabilidad del modelo seleccionado', 'columnas':['Desde','Hasta','MAE ('+unidad+')','Días dentro de ±20% (%)'],
         'filas':[[v['inicio'],v['fin'],v['mae'],v['dentro_20']] for v in ventanas_test[elegido]]}]
    modelo['tablas'].append({'titulo':'Días con mayor valor observado · historial conservado',
        'columnas':['Fecha',unidad], 'filas':[[str(fecha.date()),round(float(valor),2)] for fecha,valor in serie.nlargest(3).items()]})
    modelo['diagnosticos'] = {
        clave: diagnosticar_pronostico(test['objetivo'], predicciones[clave], predicciones['Referencia semanal'])
        for clave in nombres
    }
    modelo['tablas'].append({'titulo':'Sesgo y utilidad frente a la referencia semanal · prueba reservada',
        'columnas':['Modelo','Sesgo agregado (%)','Mejora del error frente a referencia (%)','Tendencia del error','Supera referencia'],
        'filas':[[clave, d['sesgo_porcentaje'], d['mejora_referencia_porcentaje'], d['direccion'], 'Sí' if d['supera_referencia'] else 'No']
                 for clave, d in modelo['diagnosticos'].items()]})
    modelo['metodologia'].append('Sesgo positivo: sobreestimación agregada; negativo: subestimación. Puede ocultar errores que se compensan. La mejora frente a referencia utiliza las mismas fechas de prueba y no interviene en la selección del modelo.')
    estimador = candidatos.get(elegido)
    for candidato in candidatos.values():
        candidato.fit(frame[columnas],frame['objetivo'])
    fechas_futuras = pd.date_range(hoy+pd.Timedelta(days=1),periods=7,freq='D')
    valores_futuros = predecir_bloque(serie,fechas_futuras,elegido,estimador,columnas)
    futuros = [round(float(v),2) if ventas else int(round(v)) for v in valores_futuros]
    fechas=[(hoy+pd.Timedelta(days=i)).strftime('%Y-%m-%d') for i in range(1,8)]
    modelo['graficos']=[grafico('Próximos 7 días · '+unidad,'line',fechas,futuros),
        grafico('Evaluación temporal: observado frente a estimado','line',test.index.strftime('%Y-%m-%d').tolist(),predicciones[elegido].round(2).tolist(),
            [{'nombre':'Observado','valores':test['objetivo'].round(2).tolist()},
             {'nombre':elegido,'valores':predicciones[elegido].round(2).tolist()}])]
    if elegido not in candidatos:
        modelo['metodologia'].append('La referencia estadística fue preferida en validación y se utiliza para el pronóstico; en empate se favorece el método más simple.')
    modelo['metodologia'].append('Evaluación recursiva en ventanas de hasta 7 días: al iniciar cada ventana se reajusta usando solo datos anteriores. No se introducen valores reales de esa ventana en sus rezagos.')
    modelo['metodologia'].extend(['Mediana por día de semana: referencia robusta que conserva el patrón semanal sin eliminar registros.', 'WAPE = suma de errores absolutos / suma de valores reales × 100; un valor menor es mejor y puede superar 100%.', 'El porcentaje de días dentro de ±20% es una medida histórica con tolerancia explícita, no precisión universal.'])
    modelo['metodologia'].append('Huber robusto estandariza entradas y objetivo, penalizando menos los errores extremos. No se eliminan registros por ser extremos.')
    if (serie>0).sum()<14:
        modelo['metodologia'].append('El historial tiene pocos días con actividad; conviene ampliar la serie para evaluar estabilidad.')
    modelo['graficos'].append(grafico('Comparación de modelos · MAE de validación (menor es mejor)','bar',list(seleccion),[v['mae'] for v in seleccion.values()]))
    modelo['metodologia'].append('Se comparan cinco modelos ML (Ridge, Huber, Random Forest, Extra Trees y Gradient Boosting) y tres referencias estadísticas, que no se cuentan como ML. Automático puede preferir una referencia si obtiene menor error; el modo manual permite elegir entre los cinco ML y no oculta una referencia mejor. La verificación prospectiva se consulta en Predicción frente a realidad.')
    joblib.dump({'modelo':estimador,'modelos_ml':candidatos,'nombre':elegido,'columnas':columnas,'metricas':comparacion,'seleccion':seleccion,'fecha_corte':str(hoy.date())},RAIZ/'artefactos'/('ventas.joblib' if ventas else 'pedidos.joblib'))
    return modelo


def modelo_cancelacion(tablas):
    pedidos=tablas['pedidos'].copy()
    pedidos['fecha_pedido']=pd.to_datetime(pedidos['fecha_pedido'])
    pedidos['total']=pd.to_numeric(pedidos['total'],errors='coerce').fillna(0)
    unidades=tablas['detalle_pedidos'].groupby('id_pedido')['cantidad'].sum()
    pedidos['unidades']=pedidos['id_pedido'].map(unidades).fillna(0).astype(float)
    pedidos['dia_semana']=pedidos['fecha_pedido'].dt.dayofweek
    pedidos['hora']=pedidos['fecha_pedido'].dt.hour
    numeric=['total','unidades','dia_semana','hora']; categoricas=['metodo_pago']; columnas=numeric+categoricas
    cerrados=pedidos[pedidos['estado'].isin(['Entregado','Cancelado'])].copy()
    y=(cerrados['estado']=='Cancelado').astype(int)
    modelo={'tipo_modelo':'cancelacion','predicciones':{},'nombre':'Riesgo de cancelación de pedidos','algoritmo':'Random Forest Classifier', 'estado':'insuficiente',
        'descripcion':'Aprende de pedidos entregados y cancelados. Puntúa los pendientes usando importe, unidades, método de pago y fecha de creación.',
        'metricas':{'Pedidos con resultado conocido':len(cerrados)},'graficos':[],'tablas':[],
        'metodologia':['Los pedidos pendientes o en proceso no se usan como etiquetas de entrenamiento.',
            'Validación por grupos de cliente: un cliente no aparece a la vez en entrenamiento y prueba.',
            'El estado final no se incluye entre las variables de entrada.',
            'F1 y exactitud balanceada evalúan ambas clases; AUC mide discriminación y Brier el error de las probabilidades.',
            'La puntuación es una estimación, no modifica estados ni cancela pedidos automáticamente.']}
    if len(cerrados)<30 or y.nunique()<2 or y.value_counts().min()<5 or cerrados['id_cliente'].nunique()<5:
        return modelo
    n_splits=min(5,int(y.value_counts().min()))
    splitter=StratifiedGroupKFold(n_splits=n_splits,shuffle=True,random_state=SEMILLA)
    train_idx,test_idx=next(splitter.split(cerrados[columnas],y,groups=cerrados['id_cliente']))
    if y.iloc[train_idx].nunique()<2 or y.iloc[test_idx].nunique()<2:
        return modelo
    pre=ColumnTransformer([('num',SimpleImputer(strategy='median'),numeric),
                           ('cat',OneHotEncoder(handle_unknown='ignore'),categoricas)])
    rf=RandomForestClassifier(n_estimators=200,max_depth=6,min_samples_leaf=3,class_weight='balanced',random_state=SEMILLA,n_jobs=1)
    pipeline=make_pipeline(pre,rf)
    xtrain,xtest=cerrados.iloc[train_idx][columnas],cerrados.iloc[test_idx][columnas]
    ytrain,ytest=y.iloc[train_idx],y.iloc[test_idx]
    pipeline.fit(xtrain,ytrain)
    prob=pipeline.predict_proba(xtest)[:,1]; pred=(prob>=.5).astype(int)
    referencia=DummyClassifier(strategy='prior').fit(xtrain,ytrain)
    base=referencia.predict(xtest)
    modelo['estado']='listo'
    modelo['metricas'].update({'Entrenamiento':len(train_idx),'Prueba':len(test_idx),
        'Alertas de cancelación acertadas (%)':round(float(precision_score(ytest,pred,zero_division=0))*100,1),
        'Cancelaciones detectadas (%)':round(float(recall_score(ytest,pred,zero_division=0))*100,1),
        'Exactitud balanceada (%)':round(float(balanced_accuracy_score(ytest,pred))*100,1),
        'F1 cancelación':round(float(f1_score(ytest,pred,zero_division=0)),4),
        'Exactitud balanceada':round(float(balanced_accuracy_score(ytest,pred)),4),
        'ROC AUC':round(float(roc_auc_score(ytest,prob)),4),'Brier':round(float(brier_score_loss(ytest,prob)),4)})
    modelo['metodologia'].append('Clientes compartidos entre entrenamiento y prueba: ' + str(len(set(cerrados.iloc[train_idx]['id_cliente']) & set(cerrados.iloc[test_idx]['id_cliente']))) + '.')
    modelo['metodologia'].append('Alertas acertadas = cancelaciones reales entre las alertas emitidas; cancelaciones detectadas = alertas correctas entre todas las cancelaciones reales de prueba. Umbral de alerta: 50%.')
    modelo['tablas']=[{'titulo':'Comparación con una referencia que usa la clase mayoritaria','columnas':['Modelo','F1 cancelación','Exactitud balanceada'],
        'filas':[['Random Forest',modelo['metricas']['F1 cancelación'],modelo['metricas']['Exactitud balanceada']],
                 ['Referencia mayoritaria',round(float(f1_score(ytest,base,zero_division=0)),4),round(float(balanced_accuracy_score(ytest,base)),4)]]}]
    nombres=pre.get_feature_names_out()
    importancia=sorted(zip(nombres,rf.feature_importances_),key=lambda t:-t[1])[:8]
    modelo['graficos']=[grafico('Importancia de las variables','bar',[n.replace('num__','').replace('cat__','') for n,_ in importancia],[round(float(v),4) for _,v in importancia])]
    pipeline.fit(cerrados[columnas],y)
    pendientes=pedidos[pedidos['estado']=='Pendiente'].copy()
    if not pendientes.empty:
        pendientes['riesgo']=pipeline.predict_proba(pendientes[columnas])[:,1]
        modelo['predicciones']={str(int(r['id_pedido'])):{'id_pedido':int(r['id_pedido']),'probabilidad_cancelacion':round(float(r['riesgo'])*100,1)} for _,r in pendientes.iterrows()}
        top=pendientes.sort_values('riesgo',ascending=False).head(10)
        modelo['tablas'].append({'titulo':'Pedidos pendientes con mayor puntuación estimada','columnas':['Pedido','Importe (S/)','Unidades','Puntuación (%)'],
            'filas':[[int(r['id_pedido']),round(float(r['total']),2),int(r['unidades']),round(float(r['riesgo'])*100,1)] for _,r in top.iterrows()]})
    joblib.dump({'modelo':pipeline,'columnas':columnas,'metricas':modelo['metricas']},RAIZ/'artefactos'/'cancelacion.joblib')
    return modelo


def entrenar(tipos=None):
    os.environ.setdefault('OMP_NUM_THREADS','1')
    tipos=tipos or ['productos','clientes','ventas','pedidos']
    (RAIZ/'artefactos').mkdir(exist_ok=True)
    print('Leyendo instantánea de MySQL...',flush=True)
    tablas,hoy,generado,firma=leer_datos()
    constructores={'productos':lambda:modelo_productos(tablas,hoy)+[modelo_demanda_productos(tablas,hoy)],
        'clientes':lambda:modelo_clientes(tablas,hoy)+[modelo_recompra(tablas,hoy)],
        'ventas':lambda:[modelo_pronostico(tablas,hoy,True)],
        'pedidos':lambda:[modelo_pronostico(tablas,hoy),modelo_cancelacion(tablas)]}
    informes=[]
    for tipo in tipos:
        print(tipo+': calculando modelos y evaluación...',flush=True)
        resultado={'schema_version':2,'tipo':tipo,'generado':generado,'fecha_corte':str(hoy.date()),'datos_id':firma,'moneda':'PEN',
            'versiones':{'scikit_learn':sklearn.__version__,'pandas':pd.__version__,'numpy':np.__version__},
            'conteos':{nombre:len(df) for nombre,df in tablas.items()},'modelos':constructores[tipo]()}
        informes.append((tipo,resultado))
    # Publicar cada JSON mediante reemplazo atómico: PHP nunca lee un archivo parcial.
    for tipo,resultado in informes:
        destino=RAIZ/f'cache_todos_{tipo}.json';temporal=destino.with_suffix('.json.tmp')
        temporal.write_text(json.dumps(resultado,ensure_ascii=False,indent=2,allow_nan=False),encoding='utf-8')
        temporal.replace(destino)
        print(f'{tipo}: '+', '.join(m['nombre']+' ['+m['estado']+']' for m in resultado['modelos']),flush=True)
    return [resultado for _,resultado in informes]
