/* =============================================================
   DANA Enterprise Dashboard — Frontend Logic (Final V4)
   ============================================================= */

const $form = document.getElementById('chatForm');
const $input = document.getElementById('messageInput');
const $messages = document.getElementById('messages');
const $typing = document.getElementById('typingIndicator');
const $micBtn = document.getElementById('micBtn');
const $voiceToggle = document.getElementById('voiceToggle');

let voiceEnabled = false;
let posCount = 0;
let negCount = 0;

const state = {
    messageCount: 0,
    intents: new Set(),
    sending: false,
    historyLog: []
};

// Fungsi Memperbarui Statistik
function updateStats() {
    if (document.getElementById('statMsg')) document.getElementById('statMsg').innerText = state.messageCount;
    if (document.getElementById('statIntent')) document.getElementById('statIntent').innerText = state.intents.size;
    if (document.getElementById('countPos')) document.getElementById('countPos').innerText = posCount;
    if (document.getElementById('countNeg')) document.getElementById('countNeg').innerText = negCount;
}

// Suara Bot
function speakText(text) {
    if (!voiceEnabled || !window.speechSynthesis) return;
    window.speechSynthesis.cancel(); 
    let cleanText = text.replace(/[*_#]/g, '').replace(/<[^>]*>?/gm, '').replace(/[\u{1F600}-\u{1F6FF}]/gu, ''); 
    const utterance = new SpeechSynthesisUtterance(cleanText);
    utterance.lang = 'id-ID';
    utterance.rate = 1.05;
    window.speechSynthesis.speak(utterance);
}

// Sakelar Suara
if ($voiceToggle) {
    $voiceToggle.addEventListener('click', () => {
        voiceEnabled = !voiceEnabled;
        $voiceToggle.textContent = voiceEnabled ? '🔊 Voice AI: ON' : '🔊 Voice AI: OFF';
        $voiceToggle.classList.toggle('active', voiceEnabled);
    });
}

// Mic / Speech Recognition
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
if (SpeechRecognition && $micBtn) {
    const recognition = new SpeechRecognition();
    recognition.lang = 'id-ID';
    recognition.continuous = false;

    $micBtn.addEventListener('click', () => {
        recognition.start();
        $micBtn.classList.add('recording');
        if($input) $input.placeholder = "🔴 Mendengarkan keluhan Anda...";
    });

    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        if($input) $input.value = transcript;
        sendMessage(transcript);
    };

    recognition.onend = () => {
        $micBtn.classList.remove('recording');
        if($input) $input.placeholder = "Masukkan keluhan atau uji algoritma NLP...";
    };
} else if ($micBtn) {
    $micBtn.style.display = 'none';
}

function renderMarkdown(text) {
    if (!text) return '';
    let html = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    return html.replace(/\n/g, '<br>');
}

// Render Chat ke DOM
function addMessage(text, sender, intent = null) {
    if (!$messages) return;
    
    const row = document.createElement('div');
    row.className = `message-row ${sender}`;
    
    const timeNow = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    state.historyLog.push({ waktu: timeNow, pengirim: sender, teks: text.replace(/<[^>]*>?/gm, ''), intent: intent || '-' });
    
    let html = '';
    // Menyisipkan Avatar Khusus untuk Bot
    if (sender === 'bot') {
        html += `<div class="bot-icon-chat"><img src="https://upload.wikimedia.org/wikipedia/commons/7/72/Logo_dana_blue.svg" alt="DANA" onerror="this.style.display='none'"></div>`;
    }
    
    html += `<div style="display:flex; flex-direction:column; max-width: 100%;">`;
    html += `<div class="message-bubble">${renderMarkdown(text)}</div>`;
    html += `<div class="msg-time" style="text-align: ${sender === 'user' ? 'right' : 'left'};">${timeNow}</div>`;
    
    if (intent) {
        html += `<div class="intent-tag">SYS_INTENT: ${intent}</div>`;
    }
    html += `</div>`;
    
    row.innerHTML = html;
    $messages.appendChild(row);
    
    // Scroll otomatis dengan jeda ringan agar transisi terlihat mulus
    setTimeout(() => {
        $messages.scrollTop = $messages.scrollHeight;
    }, 50);
    
    if (sender === 'bot') speakText(text);
}

// Panggil Backend
async function sendMessage(msg) {
    if (!msg.trim() || state.sending) return;

    state.sending = true;
    addMessage(msg, 'user');
    
    state.messageCount++;
    updateStats();

    if ($input) $input.value = '';
    if ($typing) $typing.classList.add('active'); 

    try {
        const res = await fetch('chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(msg)
        });
        
        const data = await res.json();
        if ($typing) $typing.classList.remove('active');
        
        if (data.reply) {
            if (data.reply.includes('POSITIF') || data.reply.includes('🟢')) {
                posCount++;
            } else if (data.reply.includes('NEGATIF') || data.reply.includes('🔴') || data.reply.includes('🚨')) {
                negCount++;
            }
        }
        
        if (data.intent && data.intent !== 'empty') {
            state.intents.add(data.intent);
        }
        updateStats();
        addMessage(data.reply || "Respon kosong dari server.", 'bot', data.intent);

    } catch (e) {
        if ($typing) $typing.classList.remove('active');
        addMessage('⚠️ Koneksi ke server ML terputus. Pastikan Flask API (app.py) di laptop Anda berjalan.', 'bot', 'system_error');
    }
    
    state.sending = false;
    if ($input) $input.focus();
}

// Events
if ($form) {
    $form.addEventListener('submit', e => {
        e.preventDefault();
        if ($input) sendMessage($input.value);
    });
}

document.querySelectorAll('.topic-btn').forEach(btn => {
    btn.addEventListener('click', () => sendMessage(btn.dataset.msg));
});

if (document.getElementById('clearBtn')) {
    document.getElementById('clearBtn').addEventListener('click', () => {
        if ($messages) $messages.innerHTML = '';
        state.messageCount = 0;
        state.intents.clear();
        state.historyLog = [];
        posCount = 0;
        negCount = 0;
        updateStats();
        setTimeout(() => {
            addMessage("Halo Kak! 👋 Layanan telah di-reset. Ucapkan keluhan atau uji algoritma NLP kembali.", 'bot');
        }, 400);
    });
}

if (document.getElementById('downloadBtn')) {
    document.getElementById('downloadBtn').addEventListener('click', () => {
        if (state.historyLog.length === 0) {
            alert("Belum ada data untuk diekspor!");
            return;
        }
        let csvContent = "Waktu,Pengirim,Pesan,Intent Terdeteksi\n";
        state.historyLog.forEach(row => {
            let cleanMsg = row.teks.replace(/"/g, '""').replace(/\n/g, ' '); 
            csvContent += `"${row.waktu}","${row.pengirim}","${cleanMsg}","${row.intent}"\n`;
        });
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Log_Sentiment_DANA_${new Date().toISOString().slice(0,10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}

// Initial Greeting
setTimeout(() => {
    addMessage("Halo Kak! 👋 Selamat datang di DANA Care AI. Ada kendala transaksi atau ingin menguji model **Machine Learning Sentimen** yang telah dilatih?", 'bot');
}, 600);