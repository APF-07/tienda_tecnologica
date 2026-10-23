import sys
import unittest
import json
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'ml'))
from diagnostico_pronostico import diagnosticar_pronostico


class DiagnosticoTest(unittest.TestCase):
    def test_mejora_y_sobreestimacion(self):
        d = diagnosticar_pronostico([10, 10], [12, 12], [14, 14])
        self.assertEqual(d['sesgo_porcentaje'], 20)
        self.assertEqual(d['mejora_referencia_porcentaje'], 50)
        self.assertTrue(d['supera_referencia'])
        self.assertIn('true', json.dumps(d))

    def test_no_oculta_referencia_perfecta(self):
        d = diagnosticar_pronostico([10], [8], [10])
        self.assertIsNone(d['mejora_referencia_porcentaje'])
        self.assertFalse(d['supera_referencia'])
        self.assertEqual(d['direccion'], 'Subestima')

    def test_ceros_sin_porcentaje_falso(self):
        d = diagnosticar_pronostico([0], [0], [0])
        self.assertIsNone(d['sesgo_porcentaje'])
        self.assertFalse(d['supera_referencia'])

    def test_error_compensado_no_es_exactitud(self):
        d = diagnosticar_pronostico([10, 10], [5, 15], [10, 10])
        self.assertEqual(d['sesgo_porcentaje'], 0)
        self.assertFalse(d['supera_referencia'])

    def test_rechaza_series_invalidas(self):
        for series in [([], [], []), ([1], [1, 2], [1]), ([1], [float('nan')], [1])]:
            with self.assertRaises(ValueError):
                diagnosticar_pronostico(*series)


if __name__ == '__main__':
    unittest.main()
