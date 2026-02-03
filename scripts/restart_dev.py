#!/usr/bin/env python3
import os
import sys
import subprocess

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
os.chdir(ROOT)

def run_script(name, args=None):
    path = os.path.join(os.path.dirname(__file__), name)
    cmd = [sys.executable, path]
    if args:
        cmd += args
    return subprocess.run(cmd)


def main():
    print('Ejecutando stop_dev.py...')
    try:
        res = run_script('stop_dev.py')
        if res.returncode != 0:
            print(f'stop_dev.py terminó con código {res.returncode}')
    except Exception as e:
        print(f'Error al ejecutar stop_dev.py: {e}')

    print('Ejecutando start_dev.py...')
    try:
        res2 = run_script('start_dev.py')
        if res2.returncode != 0:
            print(f'start_dev.py terminó con código {res2.returncode}')
            sys.exit(res2.returncode)
    except Exception as e:
        print(f'Error al ejecutar start_dev.py: {e}')
        sys.exit(1)

    print('Reinicio completado.')


if __name__ == '__main__':
    main()
