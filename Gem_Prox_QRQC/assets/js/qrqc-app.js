let chatHistory = [];
let currentProblemDescription = "";
let awaitingReportGeneration = false;
let fullChatTranscript = [];
let promptsConfig;
let reportTemplate;
const problemInputSection = document.getElementById('problem-input-section');
const analysisSection = document.getElementById('analysis-section');
const reportSection = document.getElementById('report-section');
const problemDescriptionInput = document.getElementById('problem-description');
const startAnalysisBtn = document.getElementById('start-analysis-btn');
const consentStoreReport = document.getElementById('consent-store-report');
const chatLog = document.getElementById('chat-log');
const userResponseInput = document.getElementById('user-response-input');
const sendResponseBtn = document.getElementById('send-response-btn');
const loadingIndicator = document.getElementById('loading-indicator');
const generateReportBtn = document.getElementById('generate-report-btn');
const downloadReportLink = document.getElementById('download-report-link');
const pdfProgressBarContainer = document.getElementById('pdf-progress-bar-container');
const pdfProgressBar = document.getElementById('pdf-progress-bar');

async function fetchConfig(url) {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error(`Could not fetch config from ${url}:`, error);
        alert("Erreur de chargement de l'application. Veuillez réessayer plus tard.");
        return null;
    }
}
function scrollToBottom() {
    chatLog.scrollTop = chatLog.scrollHeight;
}
function addMessage(sender, text, isInitialProblem = false) {
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('chat-message', sender === 'user' ? 'user-message' : 'ai-message');
    messageDiv.innerHTML = text.replace(/\n/g, '<br>');
    chatLog.appendChild(messageDiv);
    scrollToBottom();
    fullChatTranscript.push({ sender: sender, text: text, isInitialProblem: isInitialProblem });
}
async function sendMessageToGemini(prompt, isReportGeneration = false) {
    loadingIndicator.classList.remove('hidden');
    sendResponseBtn.disabled = true;
    userResponseInput.disabled = true;

    try {
        let payload = {
            contents: chatHistory,
            generationConfig: isReportGeneration ? {
                responseMimeType: "application/json",
                responseSchema: promptsConfig.schema
            } : {}
        };
        payload.contents.push({ role: "user", parts: [{ text: prompt }] });
        
        const formData = new URLSearchParams();
        formData.append('action', 'gemini_proxy_request');
        formData.append('nonce', geminiProxConfig.nonce);
        // ✅ Envoi direct du JSON sans encodage base64
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
            return "Désolé, je n'ai pas pu obtenir de réponse de l'IA. Veuillez réessayer.";
        }
    } catch (error) {
        console.error("Error calling Gemini API:", error);
        return "Désolé, une erreur est survenue lors de la communication avec l'IA. Veuillez réessayer.";
    } finally {
        loadingIndicator.classList.add('hidden');
        sendResponseBtn.disabled = false;
        userResponseInput.disabled = false;
    }
}
async function startAnalysis() {
    chatLog.innerHTML = '';
    chatHistory = [];
    fullChatTranscript = [];
    awaitingReportGeneration = false;
    generateReportBtn.disabled = true;
    generateReportBtn.classList.add('opacity-50', 'cursor-not-allowed');
    problemInputSection.classList.add('hidden');
    analysisSection.classList.remove('hidden');
    const problemDescription = problemDescriptionInput.value.trim();
    if (!problemDescription) {
        alert("Veuillez décrire le problème pour démarrer l'analyse.");
        problemInputSection.classList.remove('hidden');
        return;
    }
    currentProblemDescription = problemDescription;
    addMessage('user', currentProblemDescription, true);
    chatHistory.push({ role: "user", parts: [{ text: currentProblemDescription }] });
    const initialPrompt = promptsConfig.prompts.initial.replace('{{probleme_initial}}', currentProblemDescription);
    const aiResponse = await sendMessageToGemini(initialPrompt);
    addMessage('ai', aiResponse);
    chatHistory.push({ role: "model", parts: [{ text: aiResponse }] });
    if (aiResponse.includes("J'ai suffisamment d'informations pour générer le rapport.")) {
        awaitingReportGeneration = true;
        generateReportBtn.disabled = false;
        generateReportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}
async function sendUserResponse() {
    const userResponse = userResponseInput.value.trim();
    if (!userResponse) return;
    addMessage('user', userResponse);
    chatHistory.push({ role: "user", parts: [{ text: userResponse }] });
    userResponseInput.value = '';
    const prompt = promptsConfig.prompts.followUp.replace('{{reponse_utilisateur}}', userResponse);
    const aiResponse = await sendMessageToGemini(prompt);
    addMessage('ai', aiResponse);
    chatHistory.push({ role: "model", parts: [{ text: aiResponse }] });
    if (aiResponse.includes("J'ai suffisamment d'informations pour générer le rapport.")) {
        awaitingReportGeneration = true;
        generateReportBtn.disabled = false;
        generateReportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}
async function generatePdfReport() {
    if (!awaitingReportGeneration) {
        alert("L'IA n'a pas encore indiqué qu'elle a suffisamment d'informations.");
        return;
    }
    pdfProgressBarContainer.classList.remove('hidden');
    pdfProgressBar.style.width = '0%';
    generateReportBtn.disabled = true;
    generateReportBtn.classList.add('opacity-50', 'cursor-not-allowed');
    addMessage('ai', "Parfait ! Je vais maintenant générer votre rapport d'analyse QRQC. Cela peut prendre un moment...");
    const reportGenerationPrompt = promptsConfig.prompts.reportGeneration;
    try {
        const jsonResponseText = await sendMessageToGemini(reportGenerationPrompt, true);
        pdfProgressBar.style.width = '50%';
        let reportData;
        try {
            reportData = JSON.parse(jsonResponseText);
            reportData.etape1_detection_reaction.probleme_initial = fullChatTranscript.find(m => m.isInitialProblem)?.text || currentProblemDescription;
        } catch (parseError) {
            console.error("Failed to parse JSON from AI:", jsonResponseText, parseError);
            addMessage('ai', "Désolé, j'ai eu du mal à comprendre le format du rapport généré. Veuillez réessayer ou reformuler.");
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        let yOffset = 20;
        const margin = 15;
        const lineHeight = 7;
        const headerColor = '#1a202c';
        const accentColor = '#d72c4b';
        const grayColor = '#666';
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
                        headStyles: { fillColor: [102, 102, 102], textColor: [255, 255, 255], fontStyle: 'bold' },
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
        pdfProgressBar.style.width = '100%';
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
                    const pdfUrl = URL.createObjectURL(pdfBlob);
                    downloadReportLink.href = pdfUrl;
                    downloadReportLink.download = fileName;
                    pdfProgressBarContainer.classList.add('hidden');
                    problemInputSection.classList.add('hidden');
                    analysisSection.classList.add('hidden');
                    reportSection.classList.remove('hidden');
                })
                .catch(error => {
                    console.error('Erreur lors de la sauvegarde du rapport:', error);
                    alert('Erreur lors de la sauvegarde du rapport. Veuillez réessayer.');
                    pdfProgressBarContainer.classList.add('hidden');
                    generateReportBtn.disabled = false;
                    generateReportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                });
            };
            fileReader.readAsDataURL(pdfBlob);
        } else {
            const pdfBlob = doc.output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            downloadReportLink.href = pdfUrl;
            downloadReportLink.download = `rapport_qrqc_${Date.now()}.pdf`;
            pdfProgressBarContainer.classList.add('hidden');
            problemInputSection.classList.add('hidden');
            analysisSection.classList.add('hidden');
            reportSection.classList.remove('hidden');
        }
    } catch (error) {
        console.error("Error generating PDF or processing report data:", error);
        addMessage('ai', "Désolé, une erreur est survenue lors de la génération du rapport PDF. Veuillez vérifier la console pour plus de détails.");
        pdfProgressBarContainer.classList.add('hidden');
        generateReportBtn.disabled = false;
        generateReportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}
document.addEventListener('DOMContentLoaded', async () => {
    promptsConfig = await fetchConfig(geminiProxConfig.config_json_url);
    reportTemplate = await fetchConfig(geminiProxConfig.template_json_url);
    if (!promptsConfig || !reportTemplate) {
        return;
    }
});
startAnalysisBtn.addEventListener('click', () => startAnalysis());
sendResponseBtn.addEventListener('click', sendUserResponse);
userResponseInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        sendUserResponse();
    }
});
generateReportBtn.addEventListener('click', generatePdfReport);
