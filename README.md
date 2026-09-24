# DANA E-Wallet Review Sentiment Analysis & Automated Resolution Chatbot

An end-to-end NLP pipeline — from scraping real Indonesian-language app reviews to a working customer-service chatbot — built for the PPKD Jakarta Selatan Data Analyst program.

![Python](https://img.shields.io/badge/Python-3776AB?style=flat-square&logo=python&logoColor=white)
![NLP](https://img.shields.io/badge/NLP-333333?style=flat-square)
![Flask](https://img.shields.io/badge/Flask-000000?style=flat-square&logo=flask&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Status](https://img.shields.io/badge/status-completed-2ea44f?style=flat-square)

<p align="center">
  <img src="screenshots/chatbot-ui.png" alt="DANA Automated Resolution Center chatbot UI" width="850">
</p>

<p align="center">
  <a href="laporan-proyek.pdf"><strong>Full Project Report (PDF) →</strong></a>
</p>

## Contents
- [Business Problem](#business-problem)
- [Data](#data)
- [Pipeline](#pipeline)
- [Key Findings](#key-findings)
- [Chatbot Prototype](#chatbot-prototype)
- [Recommendation](#recommendation)
- [How to Run](#how-to-run)
- [Limitations](#limitations)

## Business Problem
DANA is one of Indonesia's largest digital wallets, handling millions of daily transactions. High operational volume means technical issues (pending transfers, stuck balances) regularly surface as public complaints on the Google Play Store. This project analyzes that complaint sentiment at scale and prototypes an AI system to triage it automatically — reducing response time and flagging potential fraud reports for urgent action.

## Data
- **Source:** 11,000 Google Play Store reviews of the DANA app (`id.dana`), scraped 1 March – 23 August 2026
- **Labeling:** Semi-automatic lexicon approach — a custom sentiment dictionary (48 positive / 58 negative Indonesian e-wallet terms) with negation handling and a 16-entry slang-normalization dictionary (e.g. `wd`→`tarik`, `tf`/`trf`→`transfer`, `nyangkut`→`gagal`, `lemot`→`lambat`). Auto-labeling initially produced 3 classes: 5,274 negative, 3,054 neutral, 2,672 positive.
- Manually audited the first 50 rows and corrected 5 sarcasm/context-driven misclassifications (e.g. a review sarcastically giving 5 stars while describing a security flaw, initially auto-labeled neutral, corrected to negative).
- **Final labeled dataset:** the 3,054 neutral-labeled reviews were dropped (the classification task targets binary sentiment only), leaving **7,948 reviews** (2,671 positive, 5,277 negative).

## Pipeline

| Notebook | Task |
|---|---|
| `notebooks/T1_Scraping.ipynb` | Scrape 11,000 reviews via `google-play-scraper` |
| `notebooks/T2_Labeling.ipynb` | Lexicon-based sentiment labeling with negation handling |
| `notebooks/T3_Preprocessing.ipynb` | Case folding, regex cleaning, custom slang normalization, Sastrawi stop-word removal and stemming |
| `notebooks/T4_FeatureExtraction.ipynb` | Bag-of-Words vs. TF-IDF comparison (max_features=500) |
| `notebooks/T5_Classification.ipynb` | Naive Bayes vs. SVM (LinearSVC) benchmarking |
| `notebooks/T6_DistilBERT.ipynb` | Pretrained transformer baseline comparison |
| `notebooks/T7_WordCloud.ipynb` | Visual sentiment-driver analysis |

*Note: the coursework module label is "T6_DistilBERT," but the actual pretrained model tested was [`w11wo/indonesian-roberta-base-sentiment-classifier`](https://huggingface.co/w11wo/indonesian-roberta-base-sentiment-classifier) — an Indonesian-language RoBERTa sentiment model — benchmarked here as the "off-the-shelf transformer" baseline against the tailored classical model.*

## Key Findings
- Top TF-IDF terms overall (mean TF-IDF score): *dana* (0.076), *aplikasi* (0.050), *sangat* (0.046), *transaksi* (0.042), *guna* (0.034)

**Model comparison** (train/test split: 6,358 / 1,590 reviews, stratified):

| Model | Accuracy | F1 (negative) | F1 (positive) |
|---|---|---|---|
| Naive Bayes | 89.31% | 0.92 | 0.82 |
| **SVM (LinearSVC)** | **93.33%** | **0.95** | **0.90** |
| Pretrained IndoRoBERTa | 85.41% | 0.90 | 0.78 |

The tailored classical SVM model **beat the pretrained transformer** on this informal, slang-heavy Indonesian text — showing that a well-preprocessed classical model, trained specifically on this domain's vocabulary, can outperform a larger general-purpose pretrained model when the text is noisy and highly specific (e-wallet slang, abbreviations, sarcasm).

WordCloud analysis: the word "dana" dominates both classes (2,152 occurrences in positive reviews, 5,195 in negative), but negative reviews are disproportionately concentrated around "saldo" (balance, 1,525x) and "akun" (account, 1,252x) — pointing to balance-security and transfer-failure anxiety as the dominant complaint driver, not general dissatisfaction with the app itself.

## Chatbot Prototype
A working **"DANA Automated Resolution Center"** prototype using a **Hybrid NLP Routing** architecture:
- **Frontend:** `src/index.html` / `src/style.css` / `src/script.js`
- **Rule layer:** `src/chat.php` — PHP regex/keyword intent matching for known patterns (top-up, pending status, transfer errors, fraud alerts, account freezes) and Ticket-ID generation
- **ML fallback layer:** `src/app.py` — Python Flask API serving the saved SVM model (`svm_sentiment.pkl` + `tfidf_vectorizer.pkl`) for messages the rule layer can't confidently classify

This hybrid design reflects a real production consideration: rule-based logic handles procedural, SOP-bound questions reliably, while the ML layer catches everything else — rather than relying on either approach alone.

## Recommendation
Preprocessing/slang-normalization quality had the single biggest impact on model accuracy — more than model choice itself. For production, a hybrid rule + ML architecture is more stable than a pure ML or pure rule-based system.

## How to Run
```bash
pip install flask flask-cors joblib scikit-learn Sastrawi
python src/app.py          # starts the Flask ML fallback API on :5000
# serve src/index.html + src/chat.php via a PHP-capable server (e.g. XAMPP)
```

## Limitations
The sentiment lexicon and slang dictionary were built specifically for DANA's e-wallet vocabulary — they would need re-tuning for a different app or domain. The chatbot's rule layer only covers the 9 intents identified in this dataset; production use would need ongoing intent-coverage expansion as new complaint patterns emerge.

---

<sub>**Muhammad Yahya Ayyasy** — [LinkedIn](https://linkedin.com/in/muhammadayyass) · [muhammadayyas22@gmail.com](mailto:muhammadayyas22@gmail.com)</sub>
