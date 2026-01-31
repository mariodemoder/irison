#!/usr/bin/env python3
import os
import platform
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
os.chdir(ROOT)

def kill_by_port_windows(port):
    try:
        out = subprocess.check_output(f'netstat -aon | findstr :{port}', shell=True, stderr=subprocess.DEVNULL, text=True)
    except subprocess.CalledProcessError:
        return []
    pids = set()
    for line in out.splitlines():
        parts = line.split()
        if parts:
            pid = parts[-1]
            if pid.isdigit():
                pids.add(pid)
    for pid in pids:
        subprocess.run(['taskkill', '/PID', pid, '/F'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    return list(pids)

def kill_by_port_unix(port):
    try:
        out = subprocess.check_output(['lsof', '-ti', f':{port}'], text=True, stderr=subprocess.DEVNULL)
    except Exception:
        return []
    pids = set(out.split())
    for pid in pids:
        subprocess.run(['kill', '-9', pid], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    return list(pids)

def main():
    ports = [8000, 5173]
    system = platform.system()
    killed = {}

    # Primero intentar cerrar ventanas por título (Windows)
    titles_file = os.path.join(os.path.dirname(__file__), '.dev_titles.json')
    if system == 'Windows' and os.path.exists(titles_file):
        try:
            import json
            with open(titles_file, 'r', encoding='utf-8') as f:
                titles = json.load(f)
            for cmd, title in titles.items():
                # Intentar cerrar ventana por título exacta
                subprocess.run(f'taskkill /FI "WINDOWTITLE eq {title}" /T /F', shell=True)
        except Exception:
            pass
    for p in ports:
        if system == 'Windows':
            pids = kill_by_port_windows(p)
        else:
            pids = kill_by_port_unix(p)
        killed[p] = pids

    any_killed = any(killed[p] for p in killed)
    if any_killed:
        for p, pids in killed.items():
            if pids:
                print(f'Puerto {p}: terminados PIDs {", ".join(pids)}')
    else:
        print('No se encontraron procesos en los puertos 8000/5173.')
        if '--force' in sys.argv:
            print('Forzando cierre de procesos node/php (esto puede afectar otros proyectos)...')
            if system == 'Windows':
                subprocess.run(['taskkill', '/IM', 'node.exe', '/F'])
                subprocess.run(['taskkill', '/IM', 'php.exe', '/F'])
            else:
                subprocess.run(['pkill', '-f', 'node'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                subprocess.run(['pkill', '-f', 'php'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            print('Operación forzada completada.')
        else:
            print('Ejecuta con --force para terminar procesos `node` y `php` globalmente si lo deseas.')

if __name__ == '__main__':
    main()
