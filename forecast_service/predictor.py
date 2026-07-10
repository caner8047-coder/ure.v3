import pandas as pd
import numpy as np
from prophet import Prophet
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class ProphetPredictor:
    @staticmethod
    def forecast(history_data, periods=6, freq='M'):
        """
        Generate demand forecast using Facebook Prophet.
        :param history_data: List of dicts with 'ds' (YYYY-MM-DD or similar) and 'y' (float/int value)
        :param periods: Number of future periods to forecast
        :param freq: 'W' for weekly, 'M' for monthly
        :return: Dict containing forecasts list or error message
        """
        if not history_data or len(history_data) < 3:
            # Fallback to simple moving average if history data is too sparse
            return ProphetPredictor._fallback_forecast(history_data, periods)

        try:
            # Convert input data to pandas DataFrame
            df = pd.DataFrame(history_data)
            df['ds'] = pd.to_datetime(df['ds'])
            df['y'] = pd.to_numeric(df['y'])

            # Fit Prophet model
            # For monthly data, seasonality yearly=True. For weekly, weekly=True too.
            model = Prophet(
                yearly_seasonality=len(history_data) >= 12,
                weekly_seasonality=freq == 'W',
                daily_seasonality=False,
                interval_width=0.80 # 80% confidence interval
            )
            model.fit(df)

            # Create future dataframe
            future = model.make_future_dataframe(periods=periods, freq=freq, include_history=True)
            forecast = model.predict(future)

            # Merge predictions back with actual values to calculate metrics
            merged = forecast.merge(df, on='ds', how='left')
            
            # Calculate MAPE (Mean Absolute Percentage Error) for historical fit
            historical_fit = merged.dropna(subset=['y'])
            mape = None
            if len(historical_fit) > 0:
                y_true = historical_fit['y'].values
                y_pred = historical_fit['yhat'].values
                # Avoid divide by zero
                mask = y_true != 0
                if np.sum(mask) > 0:
                    mape = float(np.mean(np.abs((y_true[mask] - y_pred[mask]) / y_true[mask])))

            # Format the output predictions
            results = []
            
            # Only return future forecasted rows
            future_forecast = forecast.tail(periods)
            for _, row in future_forecast.iterrows():
                results.append({
                    'ds': row['ds'].strftime('%Y-%m-%d'),
                    'yhat': max(0.0, float(row['yhat'])),
                    'yhat_lower': max(0.0, float(row['yhat_lower'])),
                    'yhat_upper': max(0.0, float(row['yhat_upper'])),
                    'mape': mape
                })

            return {
                'success': True,
                'predictions': results,
                'mape': mape,
                'method': 'prophet'
            }

        except Exception as e:
            logger.error(f"Prophet forecast execution failed: {str(e)}")
            return ProphetPredictor._fallback_forecast(history_data, periods, error_log=str(e))

    @staticmethod
    def _fallback_forecast(history_data, periods, error_log=None):
        """
        Simple moving average fallback forecast.
        """
        logger.info("Using fallback forecasting method due to insufficient data or error.")
        
        y_values = [row['y'] for row in history_data] if history_data else [0]
        avg_value = float(np.mean(y_values)) if y_values else 0.0
        std_val = float(np.std(y_values)) if len(y_values) > 1 else 0.0
        
        # Determine last date to project future dates
        if history_data:
            last_date = pd.to_datetime(history_data[-1]['ds'])
        else:
            last_date = pd.to_datetime('today')

        results = []
        for i in range(1, periods + 1):
            future_date = last_date + pd.DateOffset(months=i) # Assuming monthly fallback
            results.append({
                'ds': future_date.strftime('%Y-%m-%d'),
                'yhat': max(0.0, avg_value),
                'yhat_lower': max(0.0, avg_value - 1.28 * std_val), # 80% confidence interval approx
                'yhat_upper': max(0.0, avg_value + 1.28 * std_val),
                'mape': 0.0
            })

        return {
            'success': True,
            'predictions': results,
            'mape': 0.0,
            'method': 'moving_average_fallback',
            'error_log': error_log
        }
