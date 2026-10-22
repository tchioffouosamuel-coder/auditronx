import QRCode from 'qrcode'
import { notify } from './swal'

/**
 * Génère un QR code pour `value` et ouvre l'aperçu d'impression du navigateur
 * dans un nouvel onglet — utilisé pour imprimer les points QR fixes à coller
 * au point de contrôle (§4.1/§4.3).
 */
export async function printQrCode({ value, label }) {
  // 1024 px de source pour que l'agrandissement à 120 mm reste net : à cette
  // taille d'impression, les 480 px d'origine donnaient des modules flous que
  // les scanners accrochaient mal de loin.
  const dataUrl = await QRCode.toDataURL(value, { width: 1024, margin: 2 })

  const win = window.open('', '_blank', 'width=600,height=700')
  if (!win) {
    notify("Le navigateur a bloqué l'ouverture de la fenêtre d'impression (popup).", { icon: 'error' })
    return
  }

  win.document.write(`
    <!doctype html>
    <html lang="fr">
      <head>
        <meta charset="utf-8" />
        <title>QR — ${label ?? value}</title>
        <style>
          @page {
            size: A4 portrait;
            margin: 10mm;
          }
          body {
            margin: 0;
            font-family: system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
          }
          .card {
            text-align: center;
            padding: 32px;
          }
          img {
            /* Côté exprimé en mm : le PDF doit sortir au même format quel que
               soit le DPI de l'aperçu. 120 mm tient dans la largeur utile d'un
               A4 (190 mm) et reste scannable à plusieurs mètres du mur. */
            width: 120mm;
            height: 120mm;
            max-width: 100%;
            image-rendering: pixelated;
          }
          h1 {
            font-size: 22px;
            margin: 16px 0 4px;
          }
          p {
            font-size: 12px;
            color: #555;
            font-family: monospace;
            word-break: break-all;
          }
        </style>
      </head>
      <body>
        <div class="card">
          <img src="${dataUrl}" alt="QR code" />
          ${label ? `<h1>${label}</h1>` : ''}
          <p>${value}</p>
        </div>
      </body>
    </html>
  `)
  win.document.close()

  // Certains navigateurs ne redéclenchent pas onload après un document.write
  // direct — un seul déclenchement garanti par ce garde-fou.
  let printed = false
  const triggerPrint = () => {
    if (printed) return
    printed = true
    win.focus()
    win.print()
  }
  win.onload = triggerPrint
  setTimeout(triggerPrint, 300)
}
