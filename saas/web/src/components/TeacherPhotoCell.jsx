import { useRef, useState } from "react";
import api from "../lib/api";

/**
 * Aperçu + upload de la photo de référence d'un enseignant (§5 — enrôlement
 * facial embarqué ESP-WHO, voir hardware/README.md). Upload séparé du
 * formulaire CRUD générique (ResourceTable) : multipart, endpoint dédié
 * `POST /api/personnel/{id}/photo`.
 */
export default function TeacherPhotoCell({ enseignant, onUploaded }) {
  const inputRef = useRef(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  async function handleChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    setBusy(true);
    setError(null);
    try {
      const formData = new FormData();
      formData.append("photo", file);
      const { data } = await api.post(
        `/personnel/${enseignant.id}/photo`,
        formData,
        {
          headers: { "Content-Type": "multipart/form-data" },
        },
      );
      onUploaded?.(data);
    } catch {
      setError("Échec du téléversement.");
    } finally {
      setBusy(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  }

  async function handleDelete() {
    if (!window.confirm("Supprimer cette photo ?")) return;

    setBusy(true);
    setError(null);
    try {
      const { data } = await api.delete(`/personnel/${enseignant.id}/photo`);
      onUploaded?.(data);
    } catch {
      setError("Échec de la suppression.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex items-center gap-2">
      {enseignant.photo_url ? (
        <img
          src={enseignant.photo_url}
          alt={enseignant.nom}
          className="h-8 w-8 rounded-full object-cover"
        />
      ) : (
        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-ink-50 text-xs text-ink-300">
          —
        </span>
      )}
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        disabled={busy}
        className="text-xs text-brand-700 hover:text-brand-800 disabled:opacity-40"
      >
        {busy ? "Envoi…" : enseignant.photo_url ? "Changer" : "Ajouter"}
      </button>
      {enseignant.photo_url && (
        <button
          type="button"
          onClick={handleDelete}
          disabled={busy}
          className="text-xs text-red-600 hover:text-red-700 disabled:opacity-40"
        >
          Supprimer
        </button>
      )}
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        onChange={handleChange}
        className="hidden"
      />
      {error && <span className="text-xs text-red-600">{error}</span>}
    </div>
  );
}
