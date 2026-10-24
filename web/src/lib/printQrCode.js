import QRCode from 'qrcode'
import api from './api'
import { notify } from './swal'

/** Échappe le texte injecté dans le document d'impression construit en chaîne. */
const escapeHtml = (valeur) =>
  String(valeur ?? '').replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
  )

/**
 * Entête officiel bilingue, identique à celui des PDF générés par l'API
 * (`resources/views/pdf/partials/entete.blade.php`). L'identité vient de
 * `GET /parametres/etablissement` : la planche imprimée ne doit pas figer un
 * nom d'établissement côté front.
 */
async function enteteEtablissement() {
  try {
    const { data } = await api.get('/parametres/etablissement')
    const logo = data.logo_url
      ? `<img class="logo" src="${escapeHtml(data.logo_url)}" alt="Logo" />`
      : ''

    return `
      <table class="entete">
        <tr>
          <td>${escapeHtml(data.pays)}<br />${escapeHtml(data.devise)}<br />${escapeHtml(data.nom)}</td>
          <td>${logo}</td>
          <td>${escapeHtml(data.pays_en)}<br />${escapeHtml(data.devise_en)}<br />${escapeHtml(data.nom_en)}</td>
        </tr>
      </table>
    `
  } catch {
    // Mieux vaut imprimer le QR sans entête que bloquer la pose du point de
    // contrôle parce que l'API est momentanément injoignable.
    return ''
  }
}

/**
 * Génère un QR code pour `value` et ouvre l'aperçu d'impression du navigateur
 * dans un nouvel onglet — utilisé pour imprimer les points QR fixes à coller
 * au point de contrôle (§4.1/§4.3).
 */
export async function printQrCode({ value, label }) {
  const [dataUrl, entete] = await Promise.all([
    QRCode.toDataURL(value, { width: 480, margin: 2 }),
    enteteEtablissement(),
  ])

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
        <title>QR — ${escapeHtml(label ?? value)}</title>
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
          .entete {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 9px;
          }
          .entete td {
            border: none;
            text-align: center;
            vertical-align: middle;
            width: 40%;
          }
          .entete td:nth-child(2) {
            width: 20%;
          }
          .entete .logo {
            width: auto;
            height: 48px;
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
          ${entete}
          <img src="${dataUrl}" alt="QR code" />
          ${label ? `<h1>${escapeHtml(label)}</h1>` : ''}
          <p>${escapeHtml(value)}</p>
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
