#!/usr/bin/env python3
import os
import platform
import shutil
import subprocess
import json

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
os.chdir(ROOT)

def run_in_new_terminal(cmd, title=None):
    system = platform.system()
    if system == 'Windows':
        if title:
            # start "title" cmd /k "cd /d ROOT && cmd"
            # also set the console title inside the cmd session to ensure exact match
            safe_cmd = f'title {title} && cd /d "{ROOT}" && {cmd}'
            subprocess.Popen(f'start "{title}" cmd /k "{safe_cmd}"', shell=True)
        else:
            subprocess.Popen(f'start cmd /k "cd /d "{ROOT}" && {cmd}"', shell=True)
    elif system == 'Darwin':
        subprocess.Popen(['osascript', '-e', f'tell app "Terminal" to do script "{cmd}"'])
    else:
        terminals = [
            ['gnome-terminal', '--', 'bash', '-lc', f'{cmd}; exec bash'],
            ['konsole', '-e', 'bash', '-lc', f'{cmd}; exec bash'],
            ['xterm', '-e', f'{cmd}; bash']
        ]
        for t in terminals:
            try:
                subprocess.Popen(t)
                return
            except FileNotFoundError:
                continue
        subprocess.Popen(cmd, shell=True)

def check_available(cmd):
    exe = cmd.split()[0]
    if os.path.isfile(exe):
        return True
    return shutil.which(exe) is not None

REDIS_PATH = r'C:\Redis\redis-server.exe'
REDIS_DATA_DIR = r'C:\Redis\data'
MAILPIT_PATH = r'C:\Users\Mario\scoop\apps\mailpit\1.30.5\mailpit.exe'

cmds = [
    f'{REDIS_PATH} --dir {REDIS_DATA_DIR}',
    MAILPIT_PATH,
    'npm run dev',
    'php artisan serve --host=127.0.0.1 --port=8000 --no-reload',
    'php artisan queue:work --verbose --sleep=3 --tries=3',
    'php artisan schedule:work',
]

titles = {}
for i, cmd in enumerate(cmds):
    name = cmd.split()[0]
    title = f'irison-{i}-{name}'
    titles[cmd] = title
    if not check_available(cmd):
        print(f'Advertencia: no se encontró "{cmd.split()[0]}". Puede que falte instalarlo o no esté en PATH.')
    run_in_new_terminal(cmd, title=title)

# Guardar títulos para que el script de stop pueda cerrar las ventanas
try:
    with open(os.path.join(os.path.dirname(__file__), '.dev_titles.json'), 'w', encoding='utf-8') as f:
        json.dump(titles, f)
except Exception:
    pass

print('Comandos lanzados.')
