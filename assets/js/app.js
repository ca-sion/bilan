/**
 * Bilan et Débriefing CA Sion - Fonctions interactives, Autosave et Validation
 */

// Système universel de notifications toast
function showToast(message, type = 'success', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto flex items-center p-3.5 rounded-xl shadow-xl text-white text-xs font-medium transition-all duration-300 transform translate-y-4 opacity-0 ${
        type === 'success' ? 'bg-emerald-600' :
        type === 'error' ? 'bg-rose-600' :
        type === 'warning' ? 'bg-amber-600' : 'bg-zinc-900'
    }`;

    const icon = type === 'success' ? '✓' : (type === 'error' ? '✕' : 'ℹ');
    toast.innerHTML = `
        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 font-bold mr-2.5 text-xs flex-shrink-0">${icon}</span>
        <div class="flex-1 leading-snug">${message}</div>
    `;

    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Copie dans le presse-papier sécurisée avec fallback robuste
async function copyToClipboard(text, successMessage = 'Copié dans le presse-papier !') {
    let copied = false;
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            copied = true;
        } catch (err) {
            console.warn('navigator.clipboard.writeText a échoué, tentative fallback textarea :', err);
        }
    }

    if (!copied) {
        try {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            textArea.setAttribute('readonly', '');
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            copied = document.execCommand('copy');
            textArea.remove();
        } catch (err) {
            console.error('Erreur de copie execCommand :', err);
        }
    }

    if (copied) {
        showToast(successMessage, 'success');
        return true;
    } else {
        showToast('Impossible de copier automatiquement.', 'error');
        return false;
    }
}

// Copie de la synthèse WhatsApp par ID d'entretien
async function copyWhatsAppSynthesis(interviewId, btnElement = null) {
    if (!interviewId) return;

    if (btnElement) {
        btnElement.disabled = true;
        btnElement.classList.add('opacity-75');
    }

    try {
        const response = await fetch(`${window.APP_BASE_URL || ''}/api/export-whatsapp?interview_id=${interviewId}`);
        const data = await response.json();

        if (data.success && data.text) {
            await copyToClipboard(data.text, 'Synthèse WhatsApp copiée avec succès !');
        } else {
            showToast(data.error || 'Erreur lors de la génération de la synthèse.', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Erreur de connexion au serveur.', 'error');
    } finally {
        if (btnElement) {
            btnElement.disabled = false;
            btnElement.classList.remove('opacity-75');
        }
    }
}

// Classification d'une épreuve dans sa filière physiologique
function getDisciplineFamily(val) {
    if (!val) return 'none';
    const s = String(val).toLowerCase().trim();
    if (!s) return 'none';

    if (/(combin[ée]|d[ée]cath|heptath|pentath|hexath|polyval|multiple)/i.test(s)) {
        return 'combined';
    }
    if (/(demi-fond|demi\s*fond|fond|600\s*m|800\s*m|1000\s*m|1500\s*m|2000\s*m|3000\s*m|5000\s*m|10000\s*m|cross|steeple|route|trail|marche|endurance|middle_distance)/i.test(s)) {
        return 'endurance';
    }
    if (/(longueur|triple|hauteur|perche|saut|high_jump|long_jump|triple_jump|pole_vault|100\s*m|200\s*m|400\s*m|sprint|haie|hurdles|60\s*m|110\s*m|relais)/i.test(s)) {
        return 'explosive_sprint_jump';
    }
    if (/(poids|disque|javelot|marteau|lancer|shot_put|discus|javelin|hammer)/i.test(s)) {
        return 'throws';
    }
    return 'other';
}

// Vérification de compatibilité de 2 disciplines et mise à jour de l'alerte
function evaluateDisciplinePair(input1, input2, alertContainer, textContainer) {
    if (!input1 || !input2 || !alertContainer) return;

    const d1 = input1.value.trim();
    const d2 = input2.value.trim();

    if (!d1 || !d2) {
        alertContainer.classList.add('hidden');
        input1.classList.remove('border-amber-400', 'bg-amber-50/30');
        input2.classList.remove('border-amber-400', 'bg-amber-50/30');
        return;
    }

    const fam1 = getDisciplineFamily(d1);
    const fam2 = getDisciplineFamily(d2);

    let isConflict = false;
    let message = '';

    if (fam1 === 'combined' || fam2 === 'combined') {
        isConflict = true;
        message = "Les épreuves combinées constituent déjà un programme complet polyvalent. Elles ne se cumulent pas avec une seconde discipline isolée sans concertation étroite.";
    } else {
        const explosiveFamilies = ['explosive_sprint_jump', 'throws'];
        if ((fam1 === 'endurance' && explosiveFamilies.includes(fam2)) || (fam2 === 'endurance' && explosiveFamilies.includes(fam1))) {
            isConflict = true;
            message = `Attention : combinaison associant des filières physiologiques opposées (demi-fond / endurance vs sprint / sauts / lancers : "${d1}" et "${d2}"). Cela nécessite un arbitrage et une planification spécifique de l'entraîneur.`;
        }
    }

    if (isConflict) {
        if (textContainer) textContainer.textContent = message;
        alertContainer.classList.remove('hidden');
        input1.classList.add('border-amber-400', 'bg-amber-50/30');
        input2.classList.add('border-amber-400', 'bg-amber-50/30');
    } else {
        alertContainer.classList.add('hidden');
        input1.classList.remove('border-amber-400', 'bg-amber-50/30');
        input2.classList.remove('border-amber-400', 'bg-amber-50/30');
    }
}

// Initialise la détection de conflit de disciplines sur tous les formulaires
function initDisciplineConflictCheckers() {
    // 1. Formulaires athlètes (U18, U16 2e année)
    const athleteD1 = document.getElementById('chosen_discipline_1') || document.querySelector('[name="chosen_discipline_1"]');
    const athleteD2 = document.getElementById('chosen_discipline_2') || document.querySelector('[name="chosen_discipline_2"]');
    const athleteAlert = document.getElementById('discipline-compatibility-warning');
    const athleteAlertText = document.getElementById('discipline-warning-text');

    if (athleteD1 && athleteD2 && athleteAlert) {
        const checkAthlete = () => evaluateDisciplinePair(athleteD1, athleteD2, athleteAlert, athleteAlertText);
        ['input', 'change', 'keyup', 'blur'].forEach(evt => {
            athleteD1.addEventListener(evt, checkAthlete);
            athleteD2.addEventListener(evt, checkAthlete);
        });
        checkAthlete();
    }

    // 2. Écran split coach - Souhaits athlète
    const splitAthD1 = document.querySelector('[name="athlete_answers[chosen_discipline_1]"]');
    const splitAthD2 = document.querySelector('[name="athlete_answers[chosen_discipline_2]"]');
    const splitAthAlert = document.getElementById('ath-discipline-conflict-alert');
    const splitAthText = document.getElementById('ath-discipline-conflict-text');

    if (splitAthD1 && splitAthD2 && splitAthAlert) {
        const checkSplitAth = () => evaluateDisciplinePair(splitAthD1, splitAthD2, splitAthAlert, splitAthText);
        ['input', 'change', 'keyup', 'blur'].forEach(evt => {
            splitAthD1.addEventListener(evt, checkSplitAth);
            splitAthD2.addEventListener(evt, checkSplitAth);
        });
        checkSplitAth();
    }

    // 3. Écran split coach - Décisions coach
    const splitCoachD1 = document.querySelector('[name="decisions[primary_discipline]"]');
    const splitCoachD2 = document.querySelector('[name="decisions[secondary_discipline]"]');
    const splitCoachAlert = document.getElementById('coach-discipline-conflict-alert');
    const splitCoachText = document.getElementById('coach-discipline-conflict-text');

    if (splitCoachD1 && splitCoachD2 && splitCoachAlert) {
        const checkSplitCoach = () => evaluateDisciplinePair(splitCoachD1, splitCoachD2, splitCoachAlert, splitCoachText);
        ['input', 'change', 'keyup', 'blur'].forEach(evt => {
            splitCoachD1.addEventListener(evt, checkSplitCoach);
            splitCoachD2.addEventListener(evt, checkSplitCoach);
        });
        checkSplitCoach();
    }
}

// Gestionnaire d'enregistrement automatique (Autosave)
class FormAutosave {
    constructor(formId, interviewId, athleteId) {
        this.form = document.getElementById(formId);
        this.interviewId = interviewId;
        this.athleteId = athleteId;
        this.indicator = document.getElementById('autosave-indicator');
        this.isDirty = false;
        this.isSaving = false;
        this.debounceTimeout = null;

        if (!this.form) return;
        this.init();
    }

    init() {
        // Déclenche l'autosave avec debounce rapide sur la frappe (600ms)
        this.form.addEventListener('input', () => this.triggerDirty(600));
        
        // Sauvegarde immédiate lors du changement de sélection (radio, checkbox, select) ou perte de focus
        this.form.addEventListener('change', () => this.triggerImmediateSave());
        this.form.addEventListener('focusout', (e) => {
            if (this.isDirty && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                this.triggerImmediateSave();
            }
        });

        // Filet de sécurité toutes les 15 secondes si modifications en attente
        setInterval(() => {
            if (this.isDirty && !this.isSaving) {
                this.save();
            }
        }, 15000);

        // Sauvegarde synchrone à la fermeture / changement de page
        window.addEventListener('beforeunload', () => {
            if (this.isDirty) {
                this.save(true);
            }
        });
    }

    triggerDirty(delay = 600) {
        this.isDirty = true;
        this.setIndicator('dirty', 'Modifications non enregistrées');

        clearTimeout(this.debounceTimeout);
        this.debounceTimeout = setTimeout(() => {
            if (this.isDirty && !this.isSaving) {
                this.save();
            }
        }, delay);
    }

    triggerImmediateSave() {
        clearTimeout(this.debounceTimeout);
        if (this.isDirty && !this.isSaving) {
            this.save();
        } else {
            this.triggerDirty(100);
        }
    }

    getFormData() {
        const formData = new FormData(this.form);
        const data = {};

        // Parseur universel de clés HTML imbriquées (ex: "goals_results[goal_1_perf]", "days[]", "name")
        const setNestedValue = (targetObj, path, val) => {
            if (!path.includes('[')) {
                targetObj[path] = val;
                return;
            }

            const keys = path.replace(/\]/g, '').split('[');
            let current = targetObj;

            for (let i = 0; i < keys.length; i++) {
                const k = keys[i];
                const isLast = (i === keys.length - 1);
                const nextK = keys[i + 1];

                if (k === '') {
                    if (Array.isArray(current)) {
                        current.push(val);
                    }
                    return;
                }

                if (isLast) {
                    current[k] = val;
                } else {
                    if (nextK === '') {
                        if (!Array.isArray(current[k])) {
                            current[k] = [];
                        }
                    } else if (current[k] === undefined || typeof current[k] !== 'object' || current[k] === null) {
                        current[k] = {};
                    }
                    current = current[k];
                }
            }
        };

        for (const [key, value] of formData.entries()) {
            setNestedValue(data, key, value);
        }

        // Prise en compte des cases à cocher non cochées (0)
        const checkboxes = this.form.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            const name = cb.name;
            if (name && !name.endsWith('[]') && !formData.has(name)) {
                setNestedValue(data, name, 0);
            }
        });

        return data;
    }

    async save(isSync = false) {
        if (this.isSaving) return;
        this.isSaving = true;
        this.setIndicator('saving', 'Enregistrement en cours...');

        const payload = {
            interview_id: this.interviewId,
            athlete_id: this.athleteId,
            answers: this.getFormData()
        };

        try {
            const response = await fetch(`${window.APP_BASE_URL || ''}/api/save`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload),
                keepalive: isSync
            });

            const result = await response.json();
            if (result && result.success) {
                this.isDirty = false;
                const timeStr = result.saved_at || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                this.setIndicator('saved', `Modifications enregistrées à ${timeStr}`);
            } else {
                this.setIndicator('error', (result && result.error) ? result.error : 'Erreur d\'enregistrement');
            }
        } catch (err) {
            console.error('Erreur autosave :', err);
            this.setIndicator('error', 'Erreur de connexion');
        } finally {
            this.isSaving = false;
        }
    }

    setIndicator(state, text) {
        if (!this.indicator) return;

        let icon = '';
        let badgeClass = '';

        if (state === 'saving') {
            icon = '<span class="inline-block w-2 h-2 rounded-full bg-amber-400 animate-ping mr-2"></span>';
            badgeClass = 'text-amber-700 bg-amber-50 border-amber-200';
        } else if (state === 'saved') {
            icon = '<span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-2"></span>';
            badgeClass = 'text-emerald-700 bg-emerald-50 border-emerald-200';
        } else if (state === 'dirty') {
            icon = '<span class="inline-block w-2 h-2 rounded-full bg-zinc-400 mr-2"></span>';
            badgeClass = 'text-zinc-600 bg-zinc-50 border-zinc-200';
        } else if (state === 'error') {
            icon = '<span class="inline-block w-2 h-2 rounded-full bg-rose-500 mr-2"></span>';
            badgeClass = 'text-rose-700 bg-rose-50 border-rose-200';
        }

        this.indicator.className = `inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border transition-all duration-200 ${badgeClass}`;
        this.indicator.innerHTML = `${icon}<span>${text}</span>`;
    }
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    initDisciplineConflictCheckers();
});
