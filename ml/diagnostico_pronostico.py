"""Diagnóstico retrospectivo sin modificar observaciones ni predicciones."""
import math


def diagnosticar_pronostico(reales, estimados, referencia):
    reales, estimados, referencia = [list(map(float, serie)) for serie in (reales, estimados, referencia)]
    if not reales or len(reales) != len(estimados) or len(reales) != len(referencia):
        raise ValueError('Las tres series deben compartir fechas y longitud no vacía.')
    if not all(math.isfinite(float(v)) for serie in (reales, estimados, referencia) for v in serie):
        raise ValueError('La evaluación requiere valores finitos.')
    error = sum(abs(p-r) for r, p in zip(reales, estimados))
    error_ref = sum(abs(p-r) for r, p in zip(reales, referencia))
    volumen = sum(abs(r) for r in reales)
    sesgo = sum(p-r for r, p in zip(reales, estimados))
    return {
        'sesgo_porcentaje': round(100*sesgo/volumen, 2) if volumen else None,
        'mejora_referencia_porcentaje': round(100*(error_ref-error)/error_ref, 2) if error_ref else None,
        'supera_referencia': error < error_ref,
        'direccion': 'Sobreestima' if sesgo > 0 else 'Subestima' if sesgo < 0 else 'Sin sesgo agregado',
    }
