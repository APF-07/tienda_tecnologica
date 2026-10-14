"""Compatibilidad con los puntos de entrada anteriores."""
from modelos import entrenar

def exportar(tipo):
    return entrenar([tipo])
