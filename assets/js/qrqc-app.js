// Version améliorée du fichier qrqc-app.js

let chatHistory = [];
let currentProblemDescription = "";
let awaitingReportGeneration = false;
let fullChatTranscript = [];
let promptsConfig;
let reportTemplate;
let questionCounter = 0;
let totalEstimatedQuestions = 8; // Estimation basée sur QQOQPC + QCDSM + 5 Pourquoi

// Éléments DOM
const problemInputSection = document.getElementById('problem-input-section');
const analysisSection = document.getElementById('analysis-section');
const problemDescriptionInput = document.getElementById('problem-description');
const startAnalysisBtn = document.getElementById('start-analysis-btn');
const consentContainer = document.getElementById('consent-container');
const consentStoreReport = document.getElementById('consent-store-report');
const chatLog = document.getElementById('chat-log');
const userResponseInput = document.getElementById('user-response-input');
const sendResponseBtn = document.getElementById('send-response-btn');
const responseArea = document.getElementById('response-area');
const loadingIndicator = document.getElementById('loading-indicator');
const generateReportBtn = document.getElementById('generate-report-btn');
const pdfProgressBarContainer = document.getElementById('pdf-progress-bar-container');
const pdfProgressBar = document.getElementById('pdf-progress-bar');
const progressIndicator = document.getElementById('progress-indicator');

// Fonctions utilitaires
async function fetchConfig(url) {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error(`Could not fetch config from ${url}:`, error);
        showAlert("Erreur de chargement de l'application. Veuillez réessayer plus tard.", 'error');
        return null;
    }
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} fade-in`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.gemini-qrqc-app-container');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function scrollToBottom() {
    chatLog.scrollTop = chatLog.scrollHeight;
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
    
    // Créer l'avatar
    const avatar = document.createElement('div');
    avatar.classList.add('message-avatar', sender === 'user' ? 'user-avatar' : 'ai-avatar');
    avatar.textContent = sender === 'user' ? '👤' : '🤖';
    
    // Créer le contenu du message
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
    fullChatTranscript.push({ sender: sender, text: text, isInitialProblem: isInitialProblem });
    
    if (sender === 'user' && !isInitialProblem) {
        questionCounter++;
        updateProgressIndicator();
    }
}

function setLoadingState(isLoading) {
    if (isLoading) {
        loadingIndicator.classList.remove('hidden');
        sendResponseBtn.disabled = true;
        userResponseInput.disabled = true;
        
        // Ajouter un message de chargement temporaire
        const loadingMessage = createMessageElement('ai', '<span class="loading-dots"><span></span><span></span><span></span></span> Je réfléchis...');
        loadingMessage.id = 'temp-loading-message';
        chatLog.appendChild(loadingMessage);
        scrollToBottom();
    } else {
        loadingIndicator.classList.add('hidden');
        sendResponseBtn.disabled = false;
        userResponseInput.disabled = false;
        
        // Supprimer le message de chargement temporaire
        const tempMessage = document.getElementById('temp-loading-message');
        if (tempMessage) {
            tempMessage.remove();
        }
    }
}

function showReportGenerationControls() {
    // Masquer la zone de réponse utilisateur
    responseArea.classList.add('hidden');
    
    // Afficher les contrôles de génération de rapport
    consentContainer.classList.remove('hidden');
    generateReportBtn.classList.remove('hidden');
    generateReportBtn.disabled = false;
    generateReportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    
    // Focus sur le bouton de génération
    setTimeout(() => {
        generateReportBtn.focus();
    }, 100);
}

function validateInput(input) {
    const value = input.value.trim();
    if (!value) {
        input.style.borderColor = '#ef665c';
        input.style.boxShadow = '0 0 0 3px rgba(239, 102, 92, 0.3)';
        showAlert('Veuillez saisir votre réponse avant de continuer.', 'warning');
        input.focus();
        return false;
    }
    
    // Réinitialiser le style
    input.style.borderColor = '';
    input.style.boxShadow = '';
    return true;
}

async function sendMessageToGemini(prompt, isReportGeneration = false) {
    setLoadingState(true);

    try {
        // Structure corrigée pour l'API Gemini 2.0
        let payload = {
            contents: [...chatHistory]
        };
        
        // Ajouter le message utilisateur
        payload.contents.push({ 
            role: "user", 
            parts: [{ text: prompt }] 
        });

        // Configuration pour génération de rapport JSON
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

        const result = await response.json();

        if (result.success && result.data.candidates && result.data.candidates.length > 0 &&
            result.data.candidates[0].content && result.data.candidates[0].content.parts &&
            result.data.candidates[0].content.parts.length > 0) {
            const text = result.data.candidates[0].content.parts[0].text;
            return text;
        } else {
            console.error("Unexpected API response structure:", result);
            throw new Error("Réponse inattendue de l'IA");
        }
    } catch (error) {
        console.error("Error calling Gemini API:", error);
        showAlert("Erreur de communication avec l'IA. Veuillez réessayer.", 'error');
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
    
    // Masquer les contrôles de génération de rapport
    consentContainer.classList.add('hidden');
    generateReportBtn.classList.add('hidden');
    generateReportBtn.disabled = true;
    generateReportBtn.classList.add('opacity-50', 'cursor-not-allowed');
    
    // Transition des sections
    problemInputSection.classList.add('hidden');
    analysisSection.classList.remove('hidden');
    analysisSection.classList.add('fade-in');
    
    currentProblemDescription = problemDescriptionInput.value.trim();
    
    // Afficher l'indicateur de progression
    updateProgressIndicator();
    
    // Ajouter le message initial de l'utilisateur
    addMessage('user', currentProblemDescription, true);
    chatHistory.push({ role: "user", parts: [{ text: currentProblemDescription }] });
    
    try {
        const initialPrompt = promptsConfig.prompts.initial.replace('{{probleme_initial}}', currentProblemDescription);
        const aiResponse = await sendMessageToGemini(initialPrompt);
        
        addMessage('ai', aiResponse);
        chatHistory.push({ role: "model", parts: [{ text: aiResponse }] });
        
        // Vérifier si l'IA est prête à générer le rapport
        if (aiResponse.includes("J'ai suffisamment d'informations pour générer le rapport.")) {
            awaitingReportGeneration = true;
            updateProgressIndicator();
            showReportGenerationControls();
        } else {
            // Afficher la zone de réponse utilisateur
            responseArea.classList.remove('hidden');
            userResponseInput.focus();
        }
    } catch (error) {
        // En cas d'erreur, revenir à la section initiale
        problemInputSection.classList.remove('hidden');
        analysisSection.classList.add('hidden');
    }
}

async function sendUserResponse() {
    if (!validateInput(userResponseInput)) {
        return;
    }

    const userResponse = userResponseInput.value.trim();
    
    addMessage('user', userResponse);
    chatHistory.push({ role: "user", parts: [{ text: userResponse }] });
    userResponseInput.value = '';
    
    try {
        const prompt = promptsConfig.prompts.followUp.replace('{{reponse_utilisateur}}', userResponse);
        const aiResponse = await sendMessageToGemini(prompt);
        
        addMessage('ai', aiResponse);
        chatHistory.push({ role: "model", parts: [{ text: aiResponse }] });
        
        if (aiResponse.includes("J'ai suffisamment d'informations pour générer le rapport.")) {
            awaitingReportGeneration = true;
            updateProgressIndicator();
            showReportGenerationControls();
        } else {
            // Garder le focus sur le champ de saisie
            userResponseInput.focus();
        }
    } catch (error) {
        // En cas d'erreur, permettre à l'utilisateur de réessayer
        userResponseInput.focus();
    }
}

async function generatePdfReport() {
    if (!awaitingReportGeneration) {
        showAlert("L'IA n'a pas encore indiqué qu'elle a suffisamment d'informations.", 'warning');
        return;
    }
    
    // Afficher la barre de progression
    pdfProgressBarContainer.classList.remove('hidden');
    pdfProgressBar.style.width = '0%';
    generateReportBtn.disabled = true;
    generateReportBtn.classList.add('opacity-50', 'cursor-not-allowed');
    
    addMessage('ai', "Parfait ! Je génère votre rapport d'analyse QRQC maintenant...");
    
    try {
        const reportGenerationPrompt = promptsConfig.prompts.reportGeneration;
        
        // Progression à 30%
        pdfProgressBar.style.width = '30%';
        
        const jsonResponseText = await sendMessageToGemini(reportGenerationPrompt, true);
        
        // Progression à 60%
        pdfProgressBar.style.width = '60%';
        
        let reportData;
        try {
            reportData = JSON.parse(jsonResponseText);
            reportData.etape1_detection_reaction.probleme_initial = fullChatTranscript.find(m => m.isInitialProblem)?.text || currentProblemDescription;
        } catch (parseError) {
            console.error("Failed to parse JSON from AI:", jsonResponseText, parseError);
            throw new Error("Format de rapport invalide");
        }
        
        // Progression à 80%
        pdfProgressBar.style.width = '80%';
        
        // Générer le PDF
        await generatePDF(reportData);
        
        // Progression à 100%
        pdfProgressBar.style.width = '100%';
        
        // Message de succès
        addMessage('ai', "✅ Votre rapport QRQC a été généré avec succès ! Le téléchargement va commencer automatiquement.");
        
        // Masquer la barre de progression après un délai
        setTimeout(() => {
            pdfProgressBarContainer.classList.add('hidden');
        }, 2000);
        
    } catch (error) {
        console.error("Error generating PDF:", error);
        addMessage('ai', "❌ Désolé, une erreur est survenue lors de la génération du rapport. Veuillez réessayer.");
        
        // Réactiver le bouton en cas d'erreur
        pdfProgressBarContainer.classList.add('hidden');
        generateReportBtn.disabled = false;
        generateReportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

async function generatePDF(reportData) {
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
    
    // Générer le PDF selon le template
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
                const tableData = getNestedValue(reportData, element.data_path).map(action => [
                    action.action, action.qui, action.quand
                ]);
                const estimatedTableHeight = (tableData.length + 1) * (10 + 2 * 2);
                yOffset = checkPageBreak(yOffset, estimatedTableHeight + 20);
                doc.autoTable({
                    startY: yOffset + 5,
                    head: [element.headers],
                    body: tableData,
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
    
    // Gérer le stockage si demandé
    if (consentStoreReport.checked) {
        const pdfBlob = doc.output('blob');
        const fileReader = new FileReader();
        fileReader.onload = function() {
            const base64Pdf = this.result.split(',')[1];
            const fileName = `rapport_qrqc_${Date.now()}.pdf`;
            const storagePayload = {
                action: 'store_report',
                nonce: geminiProxConfig.nonce,
                report_content: base64Pdf,
                file_name: fileName
            };
            
            fetch(geminiProxConfig.proxy_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(storagePayload)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Rapport sauvegardé:', data);
            })
            .catch(error => {
                console.error('Erreur lors de la sauvegarde du rapport:', error);
            });
        };
        fileReader.readAsDataURL(pdfBlob);
    }
    
    // Téléchargement automatique
    const fileName = `rapport_qrqc_${Date.now()}.pdf`;
    doc.save(fileName);
}

// Event Listeners
document.addEventListener('DOMContentLoaded', async () => {
    promptsConfig = await fetchConfig(geminiProxConfig.config_json_url);
    reportTemplate = await fetchConfig(geminiProxConfig.template_json_url);
    
    if (!promptsConfig || !reportTemplate) {
        return;
    }
    
    // Focus initial sur le textarea
    problemDescriptionInput.focus();
});

startAnalysisBtn.addEventListener('click', startAnalysis);
sendResponseBtn.addEventListener('click', sendUserResponse);
generateReportBtn.addEventListener('click', generatePdfReport);

// Permettre l'envoi avec Entrée
userResponseInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendUserResponse();
    }
});

problemDescriptionInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && e.ctrlKey) {
        e.preventDefault();
        startAnalysis();
    }
});