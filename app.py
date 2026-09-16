from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import re
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory

app = Flask(__name__)
CORS(app)

# Load Model
try:
    svm = joblib.load('svm_sentiment.pkl')
    tfidf = joblib.load('tfidf_vectorizer.pkl')
    print("Model SVM DANA berhasil dimuat.")
except Exception as e:
    print("Error memuat model. Pastikan file pkl ada di folder yang sama.", e)

stemmer = StemmerFactory().create_stemmer()
stopword = StopWordRemoverFactory().create_stop_word_remover()

def preprocess(t):
    t = str(t).lower()
    t = re.sub(r'https?://\S+|www\.\S+', '', t)
    t = re.sub(r'@\w+|#\w+', '', t)
    t = re.sub(r'\d+', '', t)
    t = re.sub(r'[^\w\s]', '', t)
    t = re.sub(r'\s+', ' ', t).strip()
    t = stopword.remove(t)
    t = stemmer.stem(t)
    return t

@app.route('/predict', methods=['POST'])
def predict():
    try:
        data = request.get_json(force=True)
        teks = data.get('teks', '')
        
        # Preprocess dan Prediksi
        clean = preprocess(teks)
        vec = tfidf.transform([clean])
        label = svm.predict(vec)[0]
        
        return jsonify({'teks': teks, 'sentimen': label})
    except Exception as e:
        return jsonify({'error': str(e)})

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000, debug=True)