document.addEventListener("DOMContentLoaded", () => {
    const mode = document.getElementById("mode");
    const modeNote = document.getElementById("mode-note");
    const updateMode = () => {
        const live = mode.value === "live";
        modeNote.textContent = live
            ? "API real: se enviará esta URL al proveedor. Puede consumir créditos de tu cuenta."
            : "Demo: datos simulados. No se contacta a la API ni se visita la URL.";
        modeNote.classList.toggle("live", live);
    };
    if (mode) {
        mode.addEventListener("change", updateMode);
        updateMode();
    }
    const format = document.getElementById("format");
    const options = document.getElementById("json-options");
    if (format && options) {
        const updateFormat = () => {
            options.hidden = format.value !== "json";
        };
        format.addEventListener("change", updateFormat);
        updateFormat();
    }
    document
        .getElementById("extraction-form")
        ?.addEventListener("submit", () => {
            const button = document.getElementById("submit-button");
            button.disabled = true;
            button.textContent = "Enviando a la cola…";
        });
    const pending = document.querySelector("[data-poll-url]");
    if (!pending) return;
    let failures = 0;
    const poll = async () => {
        try {
            const response = await fetch(pending.dataset.pollUrl, {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Estado no disponible");
            const data = await response.json();
            if (!data.active) {
                window.location.reload();
                return;
            }
            const badge = document.getElementById("run-status");
            badge.textContent = data.label;
            badge.className = `badge ${data.status}`;
            failures = 0;
            document.getElementById("poll-feedback").textContent = "";
        } catch {
            failures++;
            document.getElementById("poll-feedback").textContent =
                "No se pudo actualizar el estado. Reintentando…";
        }
        window.setTimeout(poll, Math.min(1500 * (failures + 1), 15000));
    };
    window.setTimeout(poll, 1500);
});
