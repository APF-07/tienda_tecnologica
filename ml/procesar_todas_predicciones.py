import sys
from modelos import entrenar

if __name__ == '__main__':
    try:
        entrenar()
        from controles_negocio import entrenar_controles
        entrenar_controles()
    except Exception as error:
        print('No se pudo completar el entrenamiento: ' + str(error), file=sys.stderr)
        sys.exit(1)
