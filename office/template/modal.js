// template/modal.js
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = "flex";
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = "none";
}

// Sluiten met Escape toets
window.addEventListener("keydown", e => {
    if (e.key === "Escape") {
        document.querySelectorAll(".modal-overlay").forEach(m => m.style.display = "none");
    }
});
