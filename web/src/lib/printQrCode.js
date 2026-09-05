import QRCode from 'qrcode'

/**
 * Génère un QR code pour `value` et ouvre l'aperçu d'impression du navigateur
 * dans un nouvel onglet — utilisé pour imprimer les points QR fixes à coller
 * au point de contrôle (§4.1/§4.3).
 */
export async function printQrCode({ value, label }) {
  const dataUrl = await QRCode.toDataURL(value, { width: 480, margin: 2 })

  const win = window.open('', '_blank', 'width=600,height=700')
  if (!win) {
    window.alert("Le navigateur a bloqué l'ouverture de la fenêtre d'impression (popup).")
    return
  }

  win.document.write(`
    <!doctype html>
    <html lang="fr">
      <head>
        <meta charset="utf-8" />
        <title>QR — ${label ?? value}</title>
        <style>
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
            width: 320px;
            height: 320px;
          }
          h1 {
            font-size: 18px;
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
