"""Importa un lote histórico académico reproducible sin reemplazar registros existentes."""
import json
import os
from datetime import datetime, timedelta
from decimal import Decimal

import mysql.connector
import numpy as np

LOTE = 'academico_v1'
SEMILLA = 20261012


def generar():
    rng = np.random.default_rng(SEMILLA)
    db = mysql.connector.connect(host=os.getenv('DB_HOST','localhost'),port=int(os.getenv('DB_PORT','3306')),
        user=os.getenv('DB_USER','root'),password=os.getenv('DB_PASSWORD',''),database=os.getenv('DB_NAME','tienda_tecnologica'))
    cur = db.cursor(dictionary=True)
    try:
        cur.execute("SET time_zone='-05:00'")
        cur.execute('''CREATE TABLE IF NOT EXISTS historial_lotes (
            nombre VARCHAR(50) PRIMARY KEY, fecha_inicio DATE NOT NULL, fecha_fin DATE NOT NULL,
            clientes INT NOT NULL DEFAULT 0, pedidos INT NOT NULL DEFAULT 0,
            configuracion LONGTEXT NOT NULL, creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB''')
        cur.execute('SELECT CURRENT_DATE hoy')
        fin = cur.fetchone()['hoy'] - timedelta(days=1)
        inicio = fin - timedelta(days=269)
        db.commit()
        db.start_transaction()
        cur.execute('SELECT * FROM historial_lotes WHERE nombre=%s FOR UPDATE',(LOTE,))
        existente = cur.fetchone()
        if existente:
            db.rollback()
            print(f"El lote ya existe: {existente['pedidos']} pedidos; no se duplicaron registros.")
            return
        cur.execute('SELECT p.*,c.nombre categoria FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca WHERE p.estado=1 AND c.estado=1 AND m.estado=1')
        productos = cur.fetchall()
        rangos = {'Laptops':(1700,7000),'Computadoras':(1800,9000),'Monitores':(400,2500),
            'Componentes':(120,1600),'Almacenamiento':(160,900),'Periféricos':(35,650),'Celulares':(500,4000)}
        pools = {cat:[p for p in productos if p['categoria']==cat and minimo<=float(p['precio'])<=maximo] for cat,(minimo,maximo) in rangos.items()}
        if any(not pool for pool in pools.values()):
            raise RuntimeError('Se requiere al menos un producto con precio adecuado en cada categoría.')
        configuracion = {'semilla':SEMILLA,'dias':270,'stock':'Importación histórica: conserva las existencias actuales.',
            'patrones':['crecimiento gradual','fin de semana','fin de mes','preferencias por cliente','cancelación ligada a características del pedido']}
        cur.execute('INSERT INTO historial_lotes(nombre,fecha_inicio,fecha_fin,configuracion) VALUES(%s,%s,%s,%s)',
                    (LOTE,inicio,fin,json.dumps(configuracion,ensure_ascii=False)))
        nombres = ['Ana','Carlos','Lucía','Diego','Valeria','José','Camila','Luis','María','Andrés','Daniela','Miguel']
        apellidos = ['Quispe','Rojas','Flores','Mendoza','Torres','Vargas','Ramírez','Castro','Sánchez','Huamán']
        clientes = []
        for i in range(180):
            grupo = 'frecuente' if i<35 else ('alto_valor' if i<65 else 'ocasional')
            nombre = nombres[i%len(nombres)]
            apellido = apellidos[(i//len(nombres))%len(apellidos)]+' '+apellidos[(i+3)%len(apellidos)]
            correo = f'cliente.ml.{i+1:04d}@example.test'
            telefono = str(980100000+i)
            direccion = f'Av. Universitaria {100+i}, Lima'
            registro = datetime.combine(inicio-timedelta(days=int(rng.integers(1,31))),datetime.min.time())
            cur.execute('INSERT INTO clientes(nombre,apellido,correo,telefono,direccion,fecha_registro) VALUES(%s,%s,%s,%s,%s,%s)',
                        (nombre,apellido,correo,telefono,direccion,registro))
            clientes.append({'id':cur.lastrowid,'nombre':nombre,'apellido':apellido,'correo':correo,'telefono':telefono,
                'direccion':direccion,'grupo':grupo,'peso':4.5 if grupo=='frecuente' else (2.2 if grupo=='alto_valor' else .8)})
        pesos = np.array([c['peso'] for c in clientes]); pesos /= pesos.sum()
        n_pedidos = 0
        for t in range(270):
            dia = inicio+timedelta(days=t)
            media = (12+.045*t)*(1.3 if dia.weekday()>=5 else 1)*(1.35 if dia.day>=25 else 1)
            for _ in range(int(rng.poisson(media))):
                cliente = clientes[int(rng.choice(len(clientes),p=pesos))]
                if cliente['grupo']=='alto_valor':
                    categorias = ['Laptops','Computadoras','Monitores']; probs = [.45,.35,.20]
                elif cliente['grupo']=='frecuente':
                    categorias = ['Componentes','Periféricos','Almacenamiento']; probs = [.40,.35,.25]
                else:
                    categorias = ['Celulares','Periféricos','Monitores','Almacenamiento']; probs = [.35,.35,.15,.15]
                categoria = str(rng.choice(categorias,p=probs))
                principal = pools[categoria][int(rng.integers(len(pools[categoria])))]
                cantidad = int(rng.choice([1,2,3],p=[.73,.20,.07]))
                detalle = [(principal,cantidad)]
                if rng.random()<.24:
                    accesorio = pools['Periféricos'][int(rng.integers(len(pools['Periféricos'])))]
                    if accesorio['id_producto']!=principal['id_producto']:
                        detalle.append((accesorio,1))
                unidades = sum(c for _,c in detalle)
                metodo = str(rng.choice(['Yape/Plin','Transferencia BCP','Contraentrega'],p=[.48,.30,.22]))
                hora = int(rng.choice([10,12,15,18,20],p=[.15,.20,.25,.25,.15]))
                envio = Decimal('15.00') if rng.random()<.25 else Decimal('0.00')
                total = sum((p['precio']*c for p,c in detalle),Decimal('0.00'))+envio
                # Etiquetas generadas con una regla probabilística observable, nunca copiadas a las variables del ML.
                logit = -3.5 + 1.6*(metodo=='Contraentrega') + 1.2*(unidades>=3) + .75*(float(total)>4000) + .65*(hora>=18)
                cancelar = rng.random() < 1/(1+np.exp(-logit))
                estado = 'Cancelado' if cancelar else 'Entregado'
                fecha = datetime.combine(dia,datetime.min.time())+timedelta(hours=hora,minutes=int(rng.integers(60)),seconds=int(rng.integers(60)))
                dir_pedido = f"DNI: {88000000+clientes.index(cliente)} | Envío: {'Fuera de la ciudad (+S/ 15)' if envio else 'Dentro de la ciudad (Gratis)'} | Dir: {cliente['direccion']}"
                cur.execute('''INSERT INTO pedidos(id_cliente,fecha_pedido,total,estado,metodo_pago,cliente_nombre,cliente_apellido,cliente_correo,cliente_telefono,cliente_direccion)
                    VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)''',
                    (cliente['id'],fecha,total,estado,metodo,cliente['nombre'],cliente['apellido'],cliente['correo'],cliente['telefono'],dir_pedido))
                id_pedido = cur.lastrowid
                for producto,cant in detalle:
                    cur.execute('INSERT INTO detalle_pedidos(id_pedido,id_producto,cantidad,precio_unitario,subtotal) VALUES(%s,%s,%s,%s,%s)',
                        (id_pedido,producto['id_producto'],cant,producto['precio'],producto['precio']*cant))
                n_pedidos += 1
        cur.execute('UPDATE historial_lotes SET clientes=%s,pedidos=%s WHERE nombre=%s',(len(clientes),n_pedidos,LOTE))
        db.commit()
        print(f'Histórico añadido: {len(clientes)} clientes, {n_pedidos} pedidos, {inicio} a {fin}. Registros anteriores y stock conservados.')
    except Exception:
        db.rollback()
        raise
    finally:
        cur.close(); db.close()


if __name__=='__main__':
    generar()
