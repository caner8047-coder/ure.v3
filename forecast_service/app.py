from flask import Flask, request, jsonify
from predictor import ProphetPredictor
import logging

app = Flask(__name__)
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'healthy'}), 200

@app.route('/predict', methods=['POST'])
def predict():
    try:
        data = request.get_json()
        if not data:
            return jsonify({'success': False, 'message': 'No JSON payload provided'}), 400

        history = data.get('history', [])
        periods = int(data.get('periods', 6))
        freq = data.get('freq', 'M')

        if freq not in ['W', 'M', 'weekly', 'monthly']:
            return jsonify({'success': False, 'message': "Invalid frequency. Must be 'W' or 'M'"}), 400

        # Normalize freq for Prophet make_future_dataframe
        norm_freq = 'W' if freq in ['W', 'weekly'] else 'M'

        logger.info(f"Running forecast for {len(history)} data points, periods={periods}, freq={norm_freq}")
        result = ProphetPredictor.forecast(history, periods=periods, freq=norm_freq)

        return jsonify(result), 200

    except Exception as e:
        logger.error(f"Error handling predict request: {str(e)}")
        return jsonify({'success': False, 'message': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
