"""Extrait les fichiers d'une image LittleFS lue sur une borne (voir recuperer_file.ps1).

mklittlefs 0.2.3 (fourni par PlatformIO) embarque une version de littlefs trop
ancienne pour relire le format écrit par arduino-esp32 2.0.17 (disk version
2.1) : il échoue avec "Corrupted dir pair at 1 0". littlefs-python, plus
récent, le relit correctement.

Usage : python extraire_littlefs.py <image.bin> <dossier_sortie>
"""
import os
import sys

from littlefs import LittleFS

BLOCK_SIZE = 4096  # taille de bloc LittleFS d'arduino-esp32


def main(image_path: str, out_dir: str) -> None:
    data = open(image_path, "rb").read()
    fs = LittleFS(block_size=BLOCK_SIZE, block_count=len(data) // BLOCK_SIZE, mount=False)
    fs.context.buffer[:] = data
    fs.mount()

    for root, _dirs, files in fs.walk("/"):
        for name in files:
            src = root.rstrip("/") + "/" + name
            dest = os.path.join(out_dir, src.lstrip("/"))
            os.makedirs(os.path.dirname(dest), exist_ok=True)
            with fs.open(src, "rb") as f:
                content = f.read()
            with open(dest, "wb") as f:
                f.write(content)
            print(f"{src} ({len(content)} octets)")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
