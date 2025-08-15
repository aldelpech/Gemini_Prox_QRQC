// Version améliorée du fichier qrqc-app.js avec gestion d'erreurs robuste v1.2.1

let chatHistory = [];
let currentProblemDescription = "";
let awaitingReportGeneration = false;
let fullChatTranscript = [];
let promptsConfig;
let reportTemplate;
let questionCounter = 0;
let totalEstimatedQuestions = 8;

// Éléments DOM
const problemInputSection = document.getElementById('problem-input-section');
const analysisSection = document.getElementById('analysis-section');
const reportSection = document.getElementById('report-section');
const problemDescriptionInput = document.getElementById('problem-description');
const startAnalysisBtn = document.getElementById('start-analysis-btn');
const consentContainer = document.getElementById('consent-container');
const consentStoreReport = document.getElementById('consent-store-report');
const chatLog = document.getElementById('chat-log');
const userResponseInput = document.getElementById('user-response-input');
const sendResponseBtn = document.getElementById('send-response-btn');
const responseArea = document.getElementById('response-area');
const loadingIndicator = document.getElementById('loading-indicator');
const loadingMessageSpan = document.getElementById('loading-message');
const generateReportBtn = document.getElementById('generate-report-btn');
const saveDiscussionBtn = document.getElementById('save-discussion-btn');
const downloadReportLink = document.getElementById('download-report-link');
const pdfProgressBarContainer = document.getElementById('pdf-progress-bar-container');
const pdfProgressBar = document.getElementById('pdf-progress-bar');
const progressIndicator = document.getElementById('progress-indicator');
const appIntroText = document.getElementById('app-intro-text');
const appTipText = document.getElementById('app-tip-text');

// Fonctions utilitaires
async function fetchConfig(url) {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error(`Could not fetch config from ${url}:`, error);
        showAlert("Erreur de chargement de l'application. Cette application est encore en développement. Veuillez réessayer dans quelques secondes ou recharger la page.", 'error');
        return null;
    }
}

// Fonction showAlert modifiée pour supporter le type 'maintenance'
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    
    // Styling spécial pour les messages de maintenance
    if (type === 'maintenance') {
        alertDiv.className = 'maintenance-alert fade-in';
        alertDiv.style.cssText = `
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 3px solid #ffc107;
            color: #856404;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            font-weight: 600;
            box-shadow: 0 4px 20px rgba(255, 193, 7, 0.3);
            position: relative;
        `;
        
        // Ajouter une icône
        const icon = document.createElement('div');
        icon.textContent = '🔧';
        icon.style.cssText = `
            font-size: 2em;
            position: absolute;
            top: 15px;
            right: 20px;
        `;
        alertDiv.appendChild(icon);
        
    } else {
        alertDiv.className = `alert-warning alert-${type} fade-in`;
    }
    
    const textDiv = document.createElement('div');
    textDiv.textContent = message;
    alertDiv.appendChild(textDiv);
    
    const container = document.querySelector('.gemini-qrqc-app-container');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Scroll vers le message pour s'assurer qu'il est visible
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    // Durée d'affichage plus longue pour les messages de maintenance
    const displayDuration = type === 'maintenance' ? 15000 : (type === 'error' ? 10000 : 6000);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, displayDuration);
}

function showSuccessMessage(title, content) {
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message fade-in';
    successDiv.innerHTML = `
        <h4>${title}</h4>
        ${content}
    `;
    
    const container = document.querySelector('.gemini-qrqc-app-container');
    container.insertBefore(successDiv, container.firstChild);
    
    setTimeout(() => {
        if (successDiv.parentNode) {
            successDiv.remove();
        }
    }, 12000);
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollToBottom() {
    if (chatLog) {
        chatLog.scrollTop = chatLog.scrollHeight;
    }
}

function updateProgressIndicator() {
    if (progressIndicator) {
        const current = Math.min(questionCounter, totalEstimatedQuestions);
        progressIndicator.textContent = `Question ${current}/${totalEstimatedQuestions}`;
        
        if (awaitingReportGeneration) {
            progressIndicator.textContent = "Analyse terminée ✓";
            progressIndicator.style.background = "rgba(35, 158, 154, 0.3)";
        }
    }
}

function createMessageElement(sender, text, isInitialProblem = false) {
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('chat-message', sender === 'user' ? 'user-message' : 'ai-message');
    
    const avatar = document.createElement('div');
    avatar.classList.add('message-avatar', sender === 'user' ? 'user-avatar' : 'ai-avatar');
    avatar.textContent = sender === 'user' ? '👤' : '🤖';
    
    const content = document.createElement('div');
    content.classList.add('message-content');
    content.innerHTML = text.replace(/\n/g, '<br>');
    
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(content);
    
    return messageDiv;
}

function addMessage(sender, text, isInitialProblem = false) {
    const messageElement = createMessageElement(sender, text, isInitialProblem);
    chatLog.appendChild(messageElement);
    scrollToBottom();
    
    const chatPart = { sender: sender, text: text, isInitialProblem: isInitialProblem };
    fullChatTranscript.push(chatPart);

    if (sender === 'user' && !isInitialProblem) {
        questionCounter++;
        updateProgressIndicator();
    }
    
    if (chatHistory.length === 0 && sender === 'ai') {
        chatHistory.push({ role: "user", parts: [{ text: currentProblemDescription }] });
    }
    chatHistory.push({ role: sender === 'user' ? 'user' : 'model', parts: [{ text: text }] });
}

function setLoadingState(isLoading, message = "L'IA réfléchit...") {
    if (isLoading) {
        loadingIndicator.classList.remove('hidden');
        loadingMessageSpan.textContent = message;
        sendResponseBtn.disabled = true;
        userResponseInput.disabled = true;
        
        const loadingMessage = createMessageElement('ai', `<span class="loading-dots"><span></span><span></span><span></span></span> ${message}`);
        loadingMessage.id = 'temp-loading-message';
        chatLog.appendChild(loadingMessage);
        scrollToBottom();
    } else {
        loadingIndicator.classList.add('hidden');
        sendResponseBtn.disabled = false;
        userResponseInput.disabled = false;
        
        const tempMessage = document.getElementById('temp-loading-message');
        if (tempMessage) {
            tempMessage.remove();
        }
    }
}

function showReportGenerationControls() {
    responseArea.classList.add('hidden');
    consentContainer.classList.remove('hidden');
    generateReportBtn.classList.remove('hidden');
    generateReportBtn.disabled = false;
    generateReportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    
    setTimeout(() => {
        generateReportBtn.focus();
    }, 100);
}

function validateInput(input) {
    if (!input || !input.value) {
        showAlert('Impossible de valider le champ de saisie. Veuillez recharger la page.', 'error');
        return false;
    }
    
    const value = input.value.trim();
    if (!value) {
        input.style.borderColor = '#ef665c';
        input.style.boxShadow = '0 0 0 3px rgba(239, 102, 92, 0.3)';
        showAlert('Veuillez saisir votre réponse avant de continuer.', 'warning');
        input.focus();
        return false;
    }
    
    input.style.borderColor = '';
    input.style.boxShadow = '';
    return true;
}

// Fonction modifiée pour gérer les erreurs 429
function handleApiError(error, context = '', userFriendlyAction = '') {
    console.error('API Error:', error, 'Context:', context);
    
    let errorMessage = "Désolé, une erreur technique s'est produite. ";
    let shouldShowMaintenanceInfo = false;
    
    // Gestion spéciale de l'erreur 429 (quota dépassé)
    if (error.message?.includes('429') || error.message?.includes('quota')) {
        errorMessage = "Le quota quotidien d'utilisation de l'IA a été atteint. ";
        errorMessage += "L'application sera automatiquement disponible demain à 1h du matin. ";
        errorMessage += "Une page de maintenance va s'afficher pour informer les futurs visiteurs.";
        shouldShowMaintenanceInfo = true;
    } else if (error.message?.includes('network') || error.message?.includes('fetch') || error.message?.includes('Failed to fetch')) {
        errorMessage += "Problème de connexion réseau. ";
    } else if (error.message?.includes('timeout')) {
        errorMessage += "Le service met trop de temps à répondre. ";
    } else if (error.message?.includes('API') || error.message?.includes('500')) {
        errorMessage += "Le service IA est temporairement indisponible. ";
    } else if (error.message?.includes('403') || error.message?.includes('401')) {
        errorMessage += "Problème d'authentification avec le service. ";
    }
    
    if (!shouldShowMaintenanceInfo) {
        errorMessage += "Veuillez réessayer dans quelques secondes. Cette application est encore en développement et l'administrateur a été automatiquement informé de ce problème.";
    }
    
    if (userFriendlyAction && !shouldShowMaintenanceInfo) {
        errorMessage += ` ${userFriendlyAction}`;
    }
    
    showAlert(errorMessage, shouldShowMaintenanceInfo ? 'maintenance' : 'error');
    
    // Si c'est une erreur de quota, proposer d'actualiser dans quelques secondes pour voir la page de maintenance
    if (shouldShowMaintenanceInfo) {
        setTimeout(() => {
            if (confirm('Voulez-vous actualiser la page pour voir la page de maintenance ?')) {
                location.reload();
            }
        }, 3000);
    }
}

// Fonction modifiée pour détecter les erreurs 429 dans la réponse
async function sendMessageToGemini(prompt, isReportGeneration = false) {
    const initialLoadingMessage = isReportGeneration ? 
        "Un instant, l'IA génère votre rapport..." : 
        "L'IA analyse votre demande...";
    setLoadingState(true, initialLoadingMessage);

    try {
        let payload = {
            contents: [...chatHistory]
        };
        
        payload.contents.push({ 
            role: "user", 
            parts: [{ text: prompt }] 
        });

        if (isReportGeneration) {
            payload.generationConfig = {
                responseMimeType: "application/json",
                responseSchema: promptsConfig.schema
            };
        }
        
        const formData = new URLSearchParams();
        formData.append('action', 'gemini_proxy_request');
        formData.append('nonce', geminiProxConfig.nonce);
        formData.append('payload_json', JSON.stringify(payload));

        const response = await fetch(geminiProxConfig.proxy_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (!result.success) {
            // Vérifier si le message d'erreur contient une indication de quota dépassé
            const errorMessage = result.data || 'Erreur API inconnue';
            
            if (errorMessage.includes('quota') || errorMessage.includes('429')) {
                throw new Error(`429: ${errorMessage}`);
            }
            
            throw new Error(errorMessage);
        }

        if (result.data && result.data.candidates && result.data.candidates.length > 0 &&
            result.data.candidates[0].content && result.data.candidates[0].content.parts &&
            result.data.candidates[0].content.parts.length > 0) {
            const text = result.data.candidates[0].content.parts[0].text;
            return text;
        } else {
            throw new Error("Réponse API inattendue ou vide");
        }
    } catch (error) {
        const context = isReportGeneration ? 'génération_rapport' : 'conversation';
        const action = isReportGeneration ? 
            'Vous pouvez réessayer de générer le rapport.' : 
            'Vous pouvez réessayer de poser votre question.';
        
        // Si l'erreur contient déjà un message utilisateur (du serveur), l'utiliser directement
        if (error.message && error.message.includes('Cette application est encore en développement')) {
            showAlert(error.message, 'error');
        } else {
            handleApiError(error, context, action);
        }
        throw error;
    } finally {
        setLoadingState(false);
    }
}

async function startAnalysis() {
    if (!validateInput(problemDescriptionInput)) {
        return;
    }

    // Réinitialiser l'état
    chatLog.innerHTML = '';
    chatHistory = [];
    fullChatTranscript = [];
    awaitingReportGeneration = false;
    questionCounter = 0;
    
    consentContainer.classList.add('hidden');
    generateReportBtn.classList.add('hidden');
    generateReportBtn.disabled = true;
    
    if (appIntroText) appIntroText.classList.add('hidden');
    if (appTipText) appTipText.classList.add('hidden');
    
    problemInputSection.classList.add('hidden');
    analysisSection.classList.remove('hidden');
    analysisSection.classList.add('fade-in');
    
    currentProblemDescription = problemDescriptionInput.value.trim();
    
    progressIndicator.classList.remove('hidden');
    updateProgressIndicator();
    
    const isResume = currentProblemDescription.startsWith("interaction précédente, du ");
    
    if (isResume) {
        try {
            const lines = currentProblemDescription.split('\n\n');
            lines.shift();
            
            lines.forEach(line => {
                const parts = line.split(':');
                const senderRaw = parts.shift()?.trim();
                const text = parts.join(':').trim();
                const sender = senderRaw === 'Moi' ? 'user' : 'ai';
                
                if (sender === 'user') {
                    fullChatTranscript.push({ sender: 'user', text: text, isInitialProblem: false });
                    chatHistory.push({ role: 'user', parts: [{ text: text }] });
                } else {
                    fullChatTranscript.push({ sender: 'ai', text: text, isInitialProblem: false });
                    chatHistory.push({ role: 'model', parts: [{ text: text }] });
                }
            });
            
            fullChatTranscript.forEach(m => {
                addMessage(m.sender, m.text, m.isInitialProblem);
            });

            const lastUserMessage = fullChatTranscript.filter(m => m.sender === 'user').pop()?.text;
            
            if (!lastUserMessage) {
                throw new Error('Impossible de retrouver le dernier message utilisateur');
            }
            
            loadingMessageSpan.textContent = "Je reprends la conversation...";
            const aiResponse = await sendMessageToGemini(promptsConfig.prompts.resume.replace('{{conversation_a_reprendre}}', lastUserMessage));
            
            addMessage('ai', aiResponse);

            if (aiResponse.includes("J'ai suffisamment d'informations pour générer le rapport. Souhaitez-vous que je le fasse ?")) {
                awaitingReportGeneration = true;
                updateProgressIndicator();
                showReportGenerationControls();
                responseArea.classList.add('hidden');
            } else {
                responseArea.classList.remove('hidden');
                userResponseInput.focus();
            }
        } catch (error) {
            console.error('Erreur lors de la reprise de conversation:', error);
            showAlert('Erreur lors de la reprise de la conversation sauvegardée. Vous pouvez recommencer l\'analyse en saisissant votre problème initial.', 'error');
            problemInputSection.classList.remove('hidden');
            analysisSection.classList.add('hidden');
        }
        
    } else {
        try {
            addMessage('user', currentProblemDescription, true);
            chatHistory.push({ role: "user", parts: [{ text: currentProblemDescription }] });
            
            const initialPrompt = promptsConfig.prompts.initial.replace('{{probleme_initial}}', currentProblemDescription);
            loadingMessageSpan.textContent = "L'IA analyse votre problème...";
            const aiResponse = await sendMessageToGemini(initialPrompt);
            
            addMessage('ai', aiResponse);
            chatHistory.push({ role: "model", parts: [{ text: aiResponse }] });
            
            if (aiResponse.includes("J'ai suffisamment d'informations pour générer le rapport. Souhaitez-vous que je le fasse ?")) {
                awaitingReportGeneration = true;
                updateProgressIndicator();
                showReportGenerationControls();
                responseArea.classList.add('hidden');
            } else {
                responseArea.classList.remove('hidden');
                userResponseInput.focus();
            }
        } catch (error) {
            console.error('Erreur lors du démarrage de l\'analyse:', error);
            showAlert('Erreur lors du démarrage de l\'analyse. Vous pouvez réessayer de démarrer l\'analyse.', 'error');
            problemInputSection.classList.remove('hidden');
            analysisSection.classList.add('hidden');
        }
    }
}

async function sendUserResponse() {
    if (!validateInput(userResponseInput)) {
        return;
    }

    const userResponse = userResponseInput.value.trim();
    
    addMessage('user', userResponse);
    userResponseInput.value = '';
    
    try {
        const prompt = promptsConfig.prompts.followUp.replace('{{reponse_utilisateur}}', userResponse);
        loadingMessageSpan.textContent = "L'IA analyse votre réponse...";
        const aiResponse = await sendMessageToGemini(prompt);
        
        addMessage('ai', aiResponse);
        
        if (aiResponse.includes("J'ai suffisamment d'informations pour générer le rapport. Souhaitez-vous que je le fasse ?")) {
            awaitingReportGeneration = true;
            updateProgressIndicator();
            showReportGenerationControls();
            responseArea.classList.add('hidden');
        } else {
            userResponseInput.focus();
        }
    } catch (error) {
        console.error('Erreur lors de l\'envoi de la réponse:', error);
        userResponseInput.focus();
        // Le message d'erreur est déjà affiché par handleApiError
    }
}

function saveDiscussion() {
    try {
        if (!fullChatTranscript || fullChatTranscript.length === 0) {
            showAlert('Aucune conversation à sauvegarder. Démarrez d\'abord une analyse.', 'warning');
            return;
        }

        const now = new Date();
        const dateString = now.toLocaleDateString('fr-FR');
        const timeString = now.toLocaleTimeString('fr-FR');
        const header = `interaction précédente, du ${dateString} à ${timeString}\n\n`;
        const discussion = fullChatTranscript.map(m => {
            const senderLabel = m.sender === 'user' ? "Moi :" : "IA :";
            return `${senderLabel} ${m.text}`;
        }).join('\n\n');
        const fileContent = header + discussion;
        
        const successTitle = "💾 Discussion sauvegardée avec succès !";
        const successContent = `
            <p><strong>Comment reprendre votre analyse plus tard :</strong></p>
            <p>1. Ouvrez le fichier téléchargé dans un éditeur de texte</p>
            <p>2. Copiez <strong>tout le contenu</strong> du fichier</p>
            <p>3. Collez-le dans la zone de description du problème de cette page</p>
            <p>4. Cliquez sur "Démarrer l'analyse QRQC"</p>
            <p>L'IA reconnaîtra automatiquement qu'il s'agit d'une sauvegarde et reprendra la conversation là où vous vous étiez arrêté.</p>
        `;
        
        showSuccessMessage(successTitle, successContent);
        
        const blob = new Blob([fileContent], { type: 'text/plain; charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `discussion_qrqc_${Date.now()}.txt`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        showAlert('Erreur lors de la sauvegarde de la discussion. Veuillez réessayer ou contacter l\'administrateur si le problème persiste.', 'error');
    }
}

async function generatePdfReport() {
    if (!awaitingReportGeneration) {
        showAlert("L'IA n'a pas encore indiqué qu'elle a suffisamment d'informations pour générer un rapport.", 'warning');
        return;
    }
    
    pdfProgressBarContainer.classList.remove('hidden');
    pdfProgressBar.style.width = '0%';
    generateReportBtn.disabled = true;
    
    loadingMessageSpan.textContent = "Génération du rapport en cours...";
    loadingIndicator.classList.remove('hidden');

    addMessage('ai', "Parfait ! Je vais maintenant générer votre rapport d'analyse QRQC. Cela peut prendre un moment...");
    
    try {
        const reportGenerationPrompt = promptsConfig.prompts.reportGeneration;
        
        pdfProgressBar.style.width = '30%';
        
        const jsonResponseText = await sendMessageToGemini(reportGenerationPrompt, true);
        
        pdfProgressBar.style.width = '60%';
        
        let reportData;
        try {
            reportData = JSON.parse(jsonResponseText);
            reportData.etape1_detection_reaction.probleme_initial = fullChatTranscript.find(m => m.isInitialProblem)?.text || currentProblemDescription;
        } catch (parseError) {
            console.error("Failed to parse JSON from AI:", jsonResponseText, parseError);
            throw new Error("L'IA a fourni des données dans un format incorrect. Veuillez réessayer.");
        }
        
        pdfProgressBar.style.width = '80%';
        
        // Vérification de la validité des données
        if (!reportData.titre_probleme || !reportData.etape1_detection_reaction) {
            throw new Error("Les données du rapport sont incomplètes. Veuillez réessayer la génération.");
        }
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        let yOffset = 20;
        const margin = 15;
        const lineHeight = 7;
        const headerColor = '#550000';
        const accentColor = '#d72c4b';
        const grayColor = '#514e57';
        
        function addText(text, x, y, options = {}) {
            doc.setFontSize(options.fontSize || 10);
            doc.setTextColor(options.textColor || grayColor);
            const lines = doc.splitTextToSize(text, doc.internal.pageSize.width - 2 * margin);
            if (y + lines.length * lineHeight > doc.internal.pageSize.height - margin) {
                doc.addPage();
                y = margin;
            }
            doc.text(lines, x, y);
            return y + lines.length * lineHeight;
        }
        
        function checkPageBreak(currentY, requiredSpaceForNextSection) {
            if (currentY + requiredSpaceForNextSection > doc.internal.pageSize.height - margin) {
                doc.addPage();
                return margin;
            }
            return currentY;
        }
        
        function getNestedValue(obj, path) {
            return path.split('.').reduce((current, key) => current ? current[key] : '', obj);
        }
        
        // Génération du PDF selon le template
        reportTemplate.forEach(element => {
            yOffset = checkPageBreak(yOffset, 30);
            let content = '';
            
            switch (element.type) {
                case 'header':
                    doc.setFontSize(20);
                    doc.setTextColor(headerColor);
                    doc.text(element.text, doc.internal.pageSize.width / 2, yOffset, { align: 'center' });
                    yOffset += 15;
                    break;
                    
                case 'date_time':
                    const now = new Date();
                    const dateString = now.toLocaleDateString('fr-FR');
                    const timeString = now.toLocaleTimeString('fr-FR');
                    doc.setFontSize(10);
                    doc.setTextColor(grayColor);
                    doc.text(`${element.text_prefix}${dateString}${element.text_suffix}${timeString}`, margin, yOffset);
                    yOffset += 10;
                    break;
                    
                case 'section_title':
                    doc.setFontSize(16);
                    doc.setTextColor(accentColor);
                    content = element.text.replace('{{titre_probleme}}', reportData.titre_probleme);
                    yOffset = addText(content, margin, yOffset, {fontSize: 16, textColor: accentColor});
                    yOffset += 10;
                    break;
                    
                case 'main_section_title':
                    doc.setFontSize(16);
                    doc.setTextColor(headerColor);
                    yOffset = addText(element.text, margin, yOffset, {fontSize: 16, textColor: headerColor});
                    yOffset += lineHeight;
                    break;
                    
                case 'main_section_title_appendix':
                    doc.addPage();
                    yOffset = margin;
                    doc.setFontSize(16);
                    doc.setTextColor(headerColor);
                    yOffset = addText(element.text, margin, yOffset, {fontSize: 16, textColor: headerColor});
                    yOffset += 10;
                    break;
                    
                case 'sub_section_title':
                    doc.setFontSize(12);
                    doc.setTextColor(accentColor);
                    yOffset = addText(element.text, margin, yOffset, {fontSize: 12, textColor: accentColor});
                    yOffset += lineHeight;
                    break;
                    
                case 'text':
                    doc.setFontSize(10);
                    doc.setTextColor(grayColor);
                    if (element.title) {
                        yOffset = addText(`${element.title} : ${getNestedValue(reportData, element.data_path)}`, margin, yOffset);
                    } else {
                        yOffset = addText(getNestedValue(reportData, element.data_path), margin, yOffset);
                    }
                    yOffset += lineHeight;
                    break;
                    
                case 'key_value_text':
                    doc.setFontSize(10);
                    doc.setTextColor(grayColor);
                    const value = getNestedValue(reportData, element.data_path);
                    if (value) {
                        yOffset = addText(`${element.key} ${value}`, margin, yOffset);
                    }
                    break;
                    
                case 'list':
                    doc.setFontSize(10);
                    doc.setTextColor(grayColor);
                    const listData = getNestedValue(reportData, element.data_path);
                    if (listData && Array.isArray(listData)) {
                        listData.forEach(item => {
                            yOffset = addText(`- ${item}`, margin, yOffset);
                        });
                    }
                    yOffset += 10;
                    break;
                    
                case 'list_5_why':
                    doc.setFontSize(10);
                    doc.setTextColor(grayColor);
                    const fiveWhyData = getNestedValue(reportData, element.data_path);
                    if (fiveWhyData && Array.isArray(fiveWhyData)) {
                        fiveWhyData.forEach(item => {
                            yOffset = addText(`${item.question} ${item.reponse}`, margin, yOffset);
                        });
                    }
                    yOffset += 10;
                    break;
                    
                case 'table':
                    const tableData = getNestedValue(reportData, element.data_path);
                    if (tableData && Array.isArray(tableData)) {
                        const tableRows = tableData.map(action => [
                            action.action || '', 
                            action.qui || '', 
                            action.quand || ''
                        ]);
                        const estimatedTableHeight = (tableRows.length + 1) * (10 + 2 * 2);
                        yOffset = checkPageBreak(yOffset, estimatedTableHeight + 20);
                        doc.autoTable({
                            startY: yOffset + 5,
                            head: [element.headers],
                            body: tableRows,
                            theme: 'grid',
                            styles: { fontSize: 10, cellPadding: 2, overflow: 'linebreak' },
                            headStyles: { fillColor: [215, 44, 75], textColor: [255, 255, 255], fontStyle: 'bold' },
                            columnStyles: {
                                0: { cellWidth: 'auto' },
                                1: { cellWidth: 'auto' },
                                2: { cellWidth: 'auto' }
                            },
                            margin: { left: margin, right: margin },
                            didDrawPage: function (data) {
                                yOffset = data.cursor.y;
                            }
                        });
                        yOffset = doc.autoTable.previous.finalY + 10;
                    }
                    break;
                    
                case 'chat_transcript':
                    doc.setFontSize(10);
                    fullChatTranscript.forEach(message => {
                        const senderLabel = message.sender === 'user' ? "Moi :" : "IA :";
                        const messageColor = message.sender === 'user' ? accentColor : grayColor;
                        const messageContent = `${senderLabel} ${message.text}`;
                        yOffset = addText(messageContent, margin, yOffset, {fontSize: 10, textColor: messageColor});
                        yOffset += 5;
                    });
                    break;
            }
        });
        
        pdfProgressBar.style.width = '100%';
        
        const fileName = `rapport_qrqc_${Date.now()}.pdf`;
        doc.save(fileName);
        
        // Gestion du stockage avec gestion d'erreur améliorée
        if (consentStoreReport && consentStoreReport.checked) {
            try {
                const pdfBlob = doc.output('blob');
                const fileReader = new FileReader();
                fileReader.onload = async function() {
                    try {
                        const base64Pdf = this.result.split(',')[1];
                        const problemStatement = reportData.titre_probleme;
                        const storagePayload = {
                            action: 'store_report',
                            nonce: geminiProxConfig.nonce,
                            report_content: base64Pdf,
                            file_name: fileName,
                            problem_statement: problemStatement
                        };
                        
                        const response = await fetch(geminiProxConfig.proxy_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams(storagePayload)
                        });
                        
                        const data = await response.json();
                        
                        if (!data.success) {
                            console.warn('Échec du stockage du rapport:', data.data);
                            // Ne pas afficher d'erreur car le rapport principal a été généré
                        }
                        
                    } catch (storageError) {
                        console.warn('Erreur lors de la sauvegarde du rapport:', storageError);
                        // Le stockage a échoué mais le rapport a été téléchargé, pas d'alerte
                    }
                };
                fileReader.onerror = function() {
                    console.warn('Erreur de lecture du PDF pour stockage');
                };
                fileReader.readAsDataURL(pdfBlob);
            } catch (storageError) {
                console.warn('Erreur lors de la préparation du stockage:', storageError);
            }
        }
        
        loadingIndicator.classList.add('hidden');
        pdfProgressBarContainer.classList.add('hidden');
        problemInputSection.classList.add('hidden');
        analysisSection.classList.add('hidden');
        reportSection.classList.remove('hidden');

    } catch (error) {
        console.error("Error generating PDF:", error);
        
        let errorMessage = "❌ Erreur lors de la génération du rapport. ";
        if (error.message?.includes('format incorrect') || error.message?.includes('incomplètes')) {
            errorMessage += error.message + " ";
        } else if (error.message?.includes('jsPDF')) {
            errorMessage += "Erreur lors de la création du PDF. ";
        } else {
            errorMessage += "Une erreur technique s'est produite. ";
        }
        errorMessage += "Cette application est encore en développement et l'administrateur a été informé. Vous pouvez réessayer dans quelques secondes.";
        
        addMessage('ai', errorMessage);
        
        loadingIndicator.classList.add('hidden');
        pdfProgressBarContainer.classList.add('hidden');
        generateReportBtn.disabled = false;
        responseArea.classList.remove('hidden');
        
        showAlert(errorMessage, 'error');
    }
}

// Event Listeners avec gestion d'erreurs renforcée
document.addEventListener('DOMContentLoaded', async () => {
    try {
        promptsConfig = await fetchConfig(geminiProxConfig.config_json_url);
        reportTemplate = await fetchConfig(geminiProxConfig.template_json_url);
        
        if (!promptsConfig || !reportTemplate) {
            showAlert('Impossible de charger la configuration de l\'application. Cette application est encore en développement. Veuillez recharger la page ou réessayer plus tard.', 'error');
            return;
        }
        
        if (problemDescriptionInput) {
            problemDescriptionInput.focus();
        }

        const autoResize = (element) => {
            if (element) {
                element.style.height = 'auto';
                element.style.height = element.scrollHeight + 'px';
            }
        };

        if (userResponseInput) {
            userResponseInput.addEventListener('input', () => autoResize(userResponseInput));
        }
        
    } catch (error) {
        console.error('Erreur lors de l\'initialisation:', error);
        showAlert('Erreur lors du chargement de l\'application. Cette application est encore en développement. Veuillez recharger la page ou réessayer plus tard.', 'error');
    }
});

// Event listeners avec vérification de l'existence des éléments
if (startAnalysisBtn) {
    startAnalysisBtn.addEventListener('click', startAnalysis);
}

if (sendResponseBtn) {
    sendResponseBtn.addEventListener('click', sendUserResponse);
}

if (generateReportBtn) {
    generateReportBtn.addEventListener('click', generatePdfReport);
}

if (saveDiscussionBtn) {
    saveDiscussionBtn.addEventListener('click', saveDiscussion);
}

if (userResponseInput) {
    userResponseInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendUserResponse();
        }
    });
}

// Gestion globale des erreurs JavaScript avec messages améliorés
window.addEventListener('error', (event) => {
    console.error('Erreur JavaScript:', event.error);
    showAlert('Une erreur inattendue s\'est produite dans l\'interface. Cette application est encore en développement et l\'administrateur a été informé. Veuillez recharger la page si le problème persiste.', 'error');
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Promise rejetée non gérée:', event.reason);
    showAlert('Une erreur de traitement s\'est produite. Cette application est encore en développement et l\'administrateur a été informé. Veuillez réessayer votre dernière action.', 'error');
});

// Vérification périodique si l'application est passée en mode maintenance
function checkMaintenanceMode() {
    // Vérifier toutes les 5 minutes si la page est passée en mode maintenance
    setInterval(() => {
        fetch(window.location.href, { 
            method: 'HEAD',
            cache: 'no-cache' 
        })
        .then(response => {
            // Si le serveur renvoie un code différent ou si on détecte un changement,
            // on peut proposer à l'utilisateur d'actualiser
            if (!response.ok && response.status === 503) {
                // Code 503 = Service Unavailable, souvent utilisé pour la maintenance
                if (confirm('L\'application semble être passée en mode maintenance. Voulez-vous actualiser la page ?')) {
                    location.reload();
                }
            }
        })
        .catch(error => {
            console.log('Vérification maintenance:', error);
        });
    }, 300000); // 5 minutes
}

// Initialiser la vérification du mode maintenance
document.addEventListener('DOMContentLoaded', function() {
    checkMaintenanceMode();
});

// Gestion spécifique des erreurs de maintenance lors du chargement de la page
window.addEventListener('beforeunload', function() {
    // Sauvegarder l'état de la conversation si elle était en cours
    if (fullChatTranscript && fullChatTranscript.length > 0) {
        const currentState = {
            timestamp: Date.now(),
            transcript: fullChatTranscript,
            awaitingReport: awaitingReportGeneration
        };
        try {
            sessionStorage.setItem('qrqc_temp_save', JSON.stringify(currentState));
        } catch (e) {
            console.log('Impossible de sauvegarder temporairement la conversation');
        }
    }
});

// Récupération automatique de la conversation en cas de rechargement après maintenance
window.addEventListener('load', function() {
    try {
        const tempSave = sessionStorage.getItem('qrqc_temp_save');
        if (tempSave) {
            const savedState = JSON.parse(tempSave);
            const timeDiff = Date.now() - savedState.timestamp;
            
            // Si la sauvegarde a moins de 30 minutes, proposer de restaurer
            if (timeDiff < 1800000 && savedState.transcript.length > 1) {
                if (confirm('Une conversation précédente a été détectée. Voulez-vous la restaurer ?')) {
                    // Remplir automatiquement le champ avec la conversation sauvegardée
                    const now = new Date();
                    const dateString = now.toLocaleDateString('fr-FR');
                    const timeString = now.toLocaleTimeString('fr-FR');
                    const header = `interaction précédente, du ${dateString} à ${timeString}\n\n`;
                    const discussion = savedState.transcript.map(m => {
                        const senderLabel = m.sender === 'user' ? "Moi :" : "IA :";
                        return `${senderLabel} ${m.text}`;
                    }).join('\n\n');
                    
                    if (problemDescriptionInput) {
                        problemDescriptionInput.value = header + discussion;
                        showAlert('Conversation précédente restaurée. Cliquez sur "Démarrer l\'analyse QRQC" pour continuer.', 'info');
                    }
                }
                sessionStorage.removeItem('qrqc_temp_save');
            }
        }
    } catch (e) {
        console.log('Erreur lors de la récupération de la conversation temporaire:', e);
    }
});

// Ajout du CSS pour l'alerte de maintenance
const maintenanceCSS = `
.maintenance-alert {
    animation: maintenancePulse 2s infinite;
}

@keyframes maintenancePulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.maintenance-alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    animation: maintenanceShimmer 3s infinite;
    pointer-events: none;
}

@keyframes maintenanceShimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
`;

// Injecter le CSS dans la page
const style = document.createElement('style');
style.textContent = maintenanceCSS;
document.head.appendChild(style);