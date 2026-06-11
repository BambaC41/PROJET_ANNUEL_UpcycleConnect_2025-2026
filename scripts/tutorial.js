// scripts/tutorial.js
// Tutoriel guidé pour le dashboard particulier

const tourSteps = [
    {
        target: '.pro-kpis',
        title: '📊 Vos indicateurs clés',
        content: 'Ici vous trouvez vos statistiques : annonces, inscriptions, dépôts, upcycling score et notifications.',
        position: 'top'
    },
    {
        target: 'a[href="particulier_annonces.php"]',
        title: '📦 Gérer mes annonces',
        content: 'Déposez des annonces de don ou vente. Elles seront validées par notre équipe.',
        position: 'right'
    },
    {
        target: 'a[href="particulier_conteneurs.php"]',
        title: '🗳️ Dépôts conteneur',
        content: 'Demandez un dépôt dans un conteneur. Vous recevrez un code d\'accès et un code-barres.',
        position: 'right'
    },
    {
        target: 'a[href="particulier_catalogue.php"]',
        title: '🛍️ Catalogue',
        content: 'Découvrez et inscrivez-vous aux formations, ateliers et événements.',
        position: 'right'
    },
    {
        target: 'a[href="particulier_planning.php"]',
        title: '🗓️ Planning',
        content: 'Consultez votre emploi du temps hebdomadaire.',
        position: 'right'
    },
    {
        target: 'a[href="particulier_profile.php"]',
        title: '👤 Mon profil',
        content: 'Gérez vos informations personnelles et votre compte.',
        position: 'left'
    }
];

let currentStep = 0;

function startTour() {
    if (currentStep >= tourSteps.length) {
        endTour();
        return;
    }
    
    const step = tourSteps[currentStep];
    const element = document.querySelector(step.target);
    
    if (!element) {
        // Passer l'étape si l'élément n'existe pas
        currentStep++;
        startTour();
        return;
    }
    
    // Supprimer les overlays précédents
    removeHighlight();
    
    // Créer l'overlay de fond
    const overlay = document.createElement('div');
    overlay.id = 'tour-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 9998;
    `;
    
    // Surligner l'élément
    const rect = element.getBoundingClientRect();
    const highlight = document.createElement('div');
    highlight.id = 'tour-highlight';
    highlight.style.cssText = `
        position: fixed;
        top: ${Math.max(0, rect.top - 8)}px;
        left: ${Math.max(0, rect.left - 8)}px;
        width: ${rect.width + 16}px;
        height: ${rect.height + 16}px;
        border-radius: 12px;
        box-shadow: 0 0 0 4px #4CAF50, 0 0 0 8px rgba(76,175,80,0.3);
        z-index: 9999;
        transition: all 0.3s ease;
        pointer-events: none;
    `;
    
    // Créer la bulle d'information
    const bubble = document.createElement('div');
    bubble.id = 'tour-bubble';
    
    // Calculer la position de la bulle
    let bubbleTop, bubbleLeft;
    switch(step.position) {
        case 'top':
            bubbleTop = rect.top - 130;
            bubbleLeft = rect.left + rect.width/2 - 160;
            break;
        case 'bottom':
            bubbleTop = rect.bottom + 20;
            bubbleLeft = rect.left + rect.width/2 - 160;
            break;
        case 'left':
            bubbleTop = rect.top + rect.height/2 - 70;
            bubbleLeft = rect.left - 340;
            break;
        case 'right':
        default:
            bubbleTop = rect.top + rect.height/2 - 70;
            bubbleLeft = rect.right + 20;
    }
    
    // Ajuster pour ne pas sortir de l'écran
    if (bubbleLeft < 20) bubbleLeft = 20;
    if (bubbleLeft + 320 > window.innerWidth) bubbleLeft = window.innerWidth - 340;
    if (bubbleTop < 20) bubbleTop = 20;
    if (bubbleTop + 200 > window.innerHeight) bubbleTop = window.innerHeight - 220;
    
    bubble.style.cssText = `
        position: fixed;
        top: ${bubbleTop}px;
        left: ${bubbleLeft}px;
        width: 300px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        z-index: 10000;
        padding: 16px;
        font-family: sans-serif;
    `;
    
    // Flèche de la bulle
    const arrow = document.createElement('div');
    let arrowStyle = '';
    switch(step.position) {
        case 'top':
            arrowStyle = `bottom: 100%; left: 50%; margin-left: -10px; border-width: 0 10px 10px 10px; border-color: transparent transparent white transparent;`;
            break;
        case 'bottom':
            arrowStyle = `top: -10px; left: 50%; margin-left: -10px; border-width: 0 10px 10px 10px; border-color: transparent transparent white transparent; transform: rotate(180deg);`;
            break;
        case 'left':
            arrowStyle = `right: -10px; top: 50%; margin-top: -10px; border-width: 10px 0 10px 10px; border-color: transparent transparent transparent white;`;
            break;
        case 'right':
        default:
            arrowStyle = `left: -10px; top: 50%; margin-top: -10px; border-width: 10px 10px 10px 0; border-color: transparent white transparent transparent;`;
    }
    arrow.style.cssText = `
        position: absolute;
        width: 0;
        height: 0;
        ${arrowStyle}
        border-style: solid;
    `;
    
    bubble.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="margin: 0; color: #4CAF50;">${step.title}</h3>
            <button id="tour-close" style="background: none; border: none; font-size: 20px; cursor: pointer; padding: 4px 8px;">&times;</button>
        </div>
        <p style="margin: 0 0 16px 0; color: #333;">${step.content}</p>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <button id="tour-prev" ${currentStep === 0 ? 'disabled' : ''} style="padding: 8px 16px; background: #f0f0f0; border: none; border-radius: 6px; cursor: pointer; ${currentStep === 0 ? 'opacity: 0.5; cursor: not-allowed;' : ''}">◀ Précédent</button>
            <button id="tour-next" style="padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer;">${currentStep === tourSteps.length - 1 ? 'Terminer' : 'Suivant ▶'}</button>
        </div>
        <div style="text-align: center; margin-top: 12px; font-size: 12px; color: #888;">
            Étape ${currentStep + 1} / ${tourSteps.length}
        </div>
    `;
    
    bubble.insertBefore(arrow, bubble.firstChild);
    
    document.body.appendChild(overlay);
    document.body.appendChild(highlight);
    document.body.appendChild(bubble);
    
    // Événements
    document.getElementById('tour-next').addEventListener('click', () => {
        currentStep++;
        removeHighlight();
        startTour();
    });
    
    const prevBtn = document.getElementById('tour-prev');
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentStep > 0) {
                currentStep--;
                removeHighlight();
                startTour();
            }
        });
    }
    
    document.getElementById('tour-close').addEventListener('click', () => {
        endTour();
    });
}

function removeHighlight() {
    const overlay = document.getElementById('tour-overlay');
    const highlight = document.getElementById('tour-highlight');
    const bubble = document.getElementById('tour-bubble');
    if (overlay) overlay.remove();
    if (highlight) highlight.remove();
    if (bubble) bubble.remove();
}

async function endTour() {
    removeHighlight();
    // Marquer le tutoriel comme terminé
    try {
        const res = await fetch('tutorial_complete.php', { 
            method: 'POST', 
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            console.error('Erreur lors de la finalisation:', data);
        }
    } catch (e) {
        console.error('Erreur réseau:', e);
    }
}