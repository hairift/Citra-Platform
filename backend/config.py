import os

from dotenv import load_dotenv

load_dotenv()

BASE_DIR = os.path.dirname(os.path.abspath(__file__))


def _database_uri() -> str:
    """
    Build the SQLAlchemy URI.

    An explicit ``DATABASE_URL`` always wins. Otherwise the MySQL settings are
    assembled from the same DB_* variables Laravel uses, so both halves of the
    platform point at one database without duplicating credentials.
    """
    explicit = os.getenv('DATABASE_URL')
    if explicit:
        # Accept the postgres:// / mysql:// shorthands SQLAlchemy 2 rejects.
        if explicit.startswith('postgres://'):
            explicit = explicit.replace('postgres://', 'postgresql://', 1)
        if explicit.startswith('mysql://'):
            explicit = explicit.replace('mysql://', 'mysql+pymysql://', 1)
        return explicit

    host = os.getenv('DB_HOST', '127.0.0.1')
    port = os.getenv('DB_PORT', '3307')
    name = os.getenv('DB_DATABASE', 'citra_db')
    user = os.getenv('DB_USERNAME', 'root')
    password = os.getenv('DB_PASSWORD', '')

    from urllib.parse import quote_plus
    auth = quote_plus(user)
    if password:
        auth += f':{quote_plus(password)}'

    return f'mysql+pymysql://{auth}@{host}:{port}/{name}?charset=utf8mb4'


class Config:
    SECRET_KEY = os.getenv('SECRET_KEY', 'citra-secret-key-2024')
    JWT_SECRET_KEY = os.getenv('JWT_SECRET_KEY', 'citra-jwt-secret-2024')
    JWT_ACCESS_TOKEN_EXPIRES_DAYS = int(os.getenv('JWT_EXPIRES_DAYS', '7'))

    SQLALCHEMY_DATABASE_URI = _database_uri()
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    # MySQL closes idle connections after 8 hours; recycle well before that so
    # a long-running socket server never serves a dead connection.
    SQLALCHEMY_ENGINE_OPTIONS = {
        'pool_pre_ping': True,
        'pool_recycle': 3600,
    }

    UPLOAD_FOLDER = os.path.join(BASE_DIR, 'uploads')
    MAESTRO_FOLDER = os.path.join(BASE_DIR, 'maestro_data')
    RAW_VIDEO_FOLDER = os.path.join(BASE_DIR, 'maestro_data', 'raw')
    DATASET_FOLDER = os.path.join(BASE_DIR, 'maestro_data', 'dataset')
    MODEL_FOLDER = os.path.join(BASE_DIR, 'models')

    MAX_CONTENT_LENGTH = 512 * 1024 * 1024  # 512 MB - maestro videos are large
    ALLOWED_VIDEO_EXTENSIONS = {'mp4', 'webm', 'avi', 'mov', 'mkv'}
    ALLOWED_AUDIO_EXTENSIONS = {'mp3', 'wav', 'ogg', 'm4a'}

    CORS_ORIGINS = os.getenv('CORS_ORIGINS', '*')

    # Live evaluation tuning
    ANGLE_TOLERANCE_DEG = float(os.getenv('ANGLE_TOLERANCE_DEG', '12'))
    POSE_CORRECT_THRESHOLD = float(os.getenv('POSE_CORRECT_THRESHOLD', '70'))
    SESSION_IDLE_TIMEOUT = int(os.getenv('SESSION_IDLE_TIMEOUT', '1800'))  # seconds

    HOST = os.getenv('FLASK_HOST', '0.0.0.0')
    PORT = int(os.getenv('FLASK_PORT', '5000'))
    DEBUG = os.getenv('FLASK_DEBUG', '0') in ('1', 'true', 'True')
