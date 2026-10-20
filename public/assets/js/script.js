
/*
 * FinTrack - JavaScript
 * English, Dari, and Pashto support
 */

const ftTranslations = window.fintrackTranslations || {};

function ftT(key) {
    return ftTranslations[key] || key;
}

document.addEventListener("DOMContentLoaded", () => {
    setupFormValidation();
    setupPasswordToggle();
    setupPasswordChecks();
    setupTransactionForm();
    setupDateRange();
    setupDeleteModal();
    setupLiveSearch();
    setupChat();
    autoHideAlerts();
});


/* 1. Form validation */
function setupFormValidation() {
    document.querySelectorAll(".needs-validation").forEach(form => {
        form.addEventListener("submit", event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add("was-validated");
        });
    });
}


/* 2. Show / hide password */
function setupPasswordToggle() {
    document.querySelectorAll(".toggle-password").forEach(button => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.dataset.target);
            if (!input) return;

            const hidden = input.type === "password";
            input.type = hidden ? "text" : "password";

            button.innerHTML = hidden
                ? '<i class="bi bi-eye-slash"></i>'
                : '<i class="bi bi-eye"></i>';
        });
    });
}


/* 3. Password strength and matching */
function setupPasswordChecks() {
    const password = document.getElementById("password");
    const confirm = document.getElementById("confirm_password");
    const bar = document.getElementById("strengthBar");
    const strengthText = document.getElementById("strengthText");

    if (!password) return;

    if (bar && strengthText) {
        password.addEventListener("input", () => {
            const value = password.value;
            let score = 0;

            if (value.length >= 6) score++;
            if (value.length >= 10) score++;
            if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
            if (/\d/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;

            const levels = [
                ["too_short", "bg-danger", 10],
                ["weak", "bg-danger", 25],
                ["fair", "bg-warning", 50],
                ["good", "bg-info", 75],
                ["strong", "bg-success", 90],
                ["very_strong", "bg-success", 100],
            ];

            const [label, color, width] = levels[score];

            bar.className = "progress-bar " + color;
            bar.style.width = width + "%";

            strengthText.textContent =
                ftT("password_strength") + ": " + ftT(label);
        });
    }

    if (confirm) {
        const checkMatch = () => {
            confirm.setCustomValidity(
                confirm.value === password.value
                    ? ""
                    : ftT("passwords_do_not_match")
            );
        };

        password.addEventListener("input", checkMatch);
        confirm.addEventListener("input", checkMatch);
    }
}


/* 4. Transaction form */
function setupTransactionForm() {
    const form = document.getElementById("transactionForm");
    if (!form) return;

    const categorySelect = document.getElementById("category_id");
    const typeRadios = form.querySelectorAll('input[name="type"]');

    if (!categorySelect) return;

    const filterCategories = () => {
        const type = form.querySelector(
            'input[name="type"]:checked'
        )?.value;

        categorySelect
            .querySelectorAll("option[data-type]")
            .forEach(option => {
                option.hidden = option.dataset.type !== type;
                option.disabled = option.dataset.type !== type;
            });

        const selected = categorySelect.selectedOptions[0];

        if (selected && selected.disabled) {
            categorySelect.value = "";
        }
    };

    typeRadios.forEach(radio => {
        radio.addEventListener("change", filterCategories);
    });

    filterCategories();

    const description = document.getElementById("description");
    const counter = document.getElementById("charCount");

    if (description && counter) {
        const updateCounter = () => {
            counter.textContent = description.value.length;
        };

        description.addEventListener("input", updateCounter);
        updateCounter();
    }
}


/* 5. Date range validation */
function setupDateRange() {
    const from = document.querySelector(".date-from");
    const to = document.querySelector(".date-to");

    if (!from || !to) return;

    const check = () => {
        to.min = from.value;

        const wrong =
            from.value &&
            to.value &&
            to.value < from.value;

        to.setCustomValidity(
            wrong ? ftT("date_range_invalid") : ""
        );
    };

    from.addEventListener("change", check);
    to.addEventListener("change", check);

    check();
}


/* 6. Delete confirmation modal */
function setupDeleteModal() {
    const modal = document.getElementById("deleteModal");
    if (!modal) return;

    let formToSubmit = null;

    document.addEventListener("click", event => {
        const button = event.target.closest(".delete-btn");
        if (!button) return;

        formToSubmit = button.closest(".delete-form");

        const title = document.getElementById("deleteModalTitle");

        if (title) {
            title.textContent =
                button.dataset.title ||
                ftT("delete_item_confirm");
        }

        modal.classList.add("show");
    });

    const close = () => {
        modal.classList.remove("show");
        formToSubmit = null;
    };

    document.getElementById("cancelDelete")
        ?.addEventListener("click", close);

    modal.addEventListener("click", event => {
        if (event.target === modal) close();
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") close();
    });

    document.getElementById("confirmDelete")
        ?.addEventListener("click", () => {
            if (formToSubmit) {
                formToSubmit.submit();
            }
        });
}


/* 7. AJAX live transaction search */
function setupLiveSearch() {
    const form = document.getElementById("filterForm");
    const search = document.getElementById("liveSearch");

    if (!form || !search) return;

    let timer = null;

    const runSearch = async () => {
        const params = new URLSearchParams(
            new FormData(form)
        ).toString();

        try {
            const response = await fetch(
                "ajax/search_transactions.php?" + params
            );

            if (!response.ok) {
                throw new Error("Search failed");
            }

            const result = await response.json();

            document.getElementById("transactionRows").innerHTML =
                result.html;

            document.getElementById("resultCount").textContent =
                result.count;

            document.getElementById("resultIncome").textContent =
                result.income;

            document.getElementById("resultExpense").textContent =
                result.expense;

            document.getElementById("exportLink").href =
                "export_csv.php?" + params;

            history.replaceState(null, "", "?" + params);
        } catch (error) {
            console.error("Transaction search error:", error);
        }
    };

    search.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 300);
    });

    form.querySelectorAll("select, input[type=date]")
        .forEach(field => {
            field.addEventListener("change", runSearch);
        });
}


/* 8. FinBot chat */
function setupChat() {
    const form = document.getElementById("chatForm");
    const input = document.getElementById("chatInput");
    const messages = document.getElementById("chatMessages");

    if (!form || !input || !messages) return;

    const toggle = document.getElementById("chatToggle");
    const panel = document.getElementById("chatPanel");

    if (toggle && panel) {
        toggle.addEventListener("click", () => {
            panel.classList.toggle("open");

            if (panel.classList.contains("open")) {
                input.focus();
            }
        });

        document.getElementById("chatClose")
            ?.addEventListener("click", () => {
                panel.classList.remove("open");
            });
    }

    const addMessage = (text, who) => {
        const bubble = document.createElement("div");

        bubble.className = "chat-msg " + who;

        // Safe text rendering (prevents HTML injection)
        bubble.textContent = text;

        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;

        return bubble;
    };

    const ask = async question => {
        if (!question.trim()) return;

        addMessage(question, "user");

        const typing = addMessage(
            ftT("finbot_thinking"),
            "bot typing"
        );

        const data = new FormData();
        data.append("question", question);

        const csrf = document.querySelector(
            'meta[name="csrf-token"]'
        );

        if (csrf) {
            data.append("csrf_token", csrf.content);
        }

        try {
            const response = await fetch(
                "ajax/ai_chat.php",
                {
                    method: "POST",
                    body: data
                }
            );

            if (!response.ok) {
                throw new Error("FinBot request failed");
            }

            const result = await response.json();

            typing.remove();
            addMessage(result.answer, "bot");

        } catch (error) {
            typing.remove();

            addMessage(
                ftT("finbot_error"),
                "bot"
            );
        }
    };

    form.addEventListener("submit", event => {
        event.preventDefault();

        const question = input.value.trim();
        if (!question) return;

        input.value = "";
        ask(question);
    });

    document.querySelectorAll(".chip[data-question]")
        .forEach(chip => {
            chip.addEventListener("click", () => {
                ask(chip.dataset.question);
            });
        });

    messages.scrollTop = messages.scrollHeight;
}


/* 9. Auto-hide success alerts */
function autoHideAlerts() {
    document.querySelectorAll(".auto-hide.alert-success")
        .forEach(alert => {
            setTimeout(() => {
                bootstrap.Alert
                    .getOrCreateInstance(alert)
                    .close();
            }, 4000);
        });
}
