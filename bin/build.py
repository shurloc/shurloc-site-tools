#!/usr/bin/env python3

import shutil
import zipfile
from pathlib import Path

project_root = Path(__file__).resolve().parent.parent
plugin_name = project_root.name

build_root = project_root / 'build/dist'
plugin_root = build_root / plugin_name
zip_file = build_root / f"{plugin_name}.zip"

print('')
print(f'Building {plugin_name}...')
print('')

#
# Clean previous build.
#
if plugin_root.exists():
    shutil.rmtree(plugin_root)

plugin_root.mkdir(parents=True, exist_ok=True)

#
# Copy the project, excluding development files.
#
excluded_directories = [
    ".git",
    ".github",
    ".vscode",
    ".phpunit.cache",
    "build",
    "tests",
    "vendor",
    "bin",
]

excluded_files = [
    ".editorconfig",
    ".gitattributes",
    ".gitignore",
    ".phpunit.result.cache",
    "composer.json",
    "composer.lock",
    "phpcs.xml",
    "phpcs.xml.dist",
    "phpunit.xml",
    "phpunit.xml.dist",
    "phpstan.neon",
    "phpstan.neon.dist",
    "README-development.md",
    "TODO.md",
    ".gitkeep",
    "CHANGELOG.md",
]

print('Copying files into place...')
print('')

file_stack = list(project_root.glob('*'))

while len(file_stack) > 0:
    file_path = file_stack.pop()

    if (file_path.is_file() and file_path.name in excluded_files) or \
       (file_path.is_dir() and file_path.name in excluded_directories):
        continue
    new_path = plugin_root / file_path.relative_to(project_root)
    if file_path.is_dir():
        new_path.mkdir(parents=True, exist_ok=True)
        file_stack.extend(list(file_path.glob('*')))
    elif file_path.is_file():
        shutil.copy(file_path, new_path)

#
# Verify required plugin files.
#
plugin_bootstrap_file = f'{plugin_name}.php'
plugin_bootstrap_path = plugin_root / plugin_bootstrap_file

if not plugin_bootstrap_path.exists():
    raise RuntimeError(f"Plugin bootstrap file '{plugin_bootstrap_file}' was not copied.")

if not (plugin_root / 'includes').exists():
    raise RuntimeError("The 'includes' directory was not copied.")

#
# Remove any previous ZIP.
#
if zip_file.exists():
    zip_file.unlink()

#
# Create ZIP archive.
#
shutil.make_archive(
    base_name=plugin_root,
    format='zip',
    root_dir=plugin_root.parent,
    base_dir=plugin_name,
)

print('Build package contents:')
print('')

with zipfile.ZipFile(zip_file, "r") as archive:
    for file in archive.namelist():
        print(file)

print('')
print('Build complete.')
print('')
print('Folder:')
print(f"  {plugin_root}")
print('')
print('ZIP:')
print(f"  {zip_file}")
print('')
