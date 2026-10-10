const QR_PATTERN = [
  1, 1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1,
  0, 1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1, 1,
];

export default function LoadingState({ label = "Chargement en cours" }) {
  return (
    <div className="qr-loader" role="status" aria-live="polite">
      <div className="qr-loader__frame" aria-hidden="true">
        <div className="qr-loader__pattern">
          {QR_PATTERN.map((filled, index) => (
            <span
              key={index}
              className={
                filled
                  ? "qr-loader__cell qr-loader__cell--filled"
                  : "qr-loader__cell"
              }
            />
          ))}
        </div>
        <span className="qr-loader__scan-line" />
      </div>
      <strong>{label}</strong>
      <span className="qr-loader__hint">Préparation des données…</span>
    </div>
  );
}
