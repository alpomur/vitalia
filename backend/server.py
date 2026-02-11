from fastapi import FastAPI, APIRouter, HTTPException, Request, Response, Depends
from fastapi.responses import JSONResponse
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import logging
from pathlib import Path
from pydantic import BaseModel, Field, ConfigDict
from typing import List, Optional, Dict, Any
import uuid
from datetime import datetime, timezone, timedelta
import hashlib
import httpx
from emergentintegrations.llm.chat import LlmChat, UserMessage

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / '.env')

# MongoDB connection
mongo_url = os.environ['MONGO_URL']
client = AsyncIOMotorClient(mongo_url)
db = client[os.environ['DB_NAME']]

# Emergent LLM Key
EMERGENT_LLM_KEY = os.environ.get('EMERGENT_LLM_KEY', '')

# Create the main app
app = FastAPI(title="Vitalia - Health Advisor API")

# Create a router with the /api prefix
api_router = APIRouter(prefix="/api")

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# ==================== MODELS ====================

class UserCreate(BaseModel):
    email: Optional[str] = None
    name: Optional[str] = None
    picture: Optional[str] = None
    auth_provider: str = "guest"

class User(BaseModel):
    model_config = ConfigDict(extra="ignore")
    user_id: str
    email: Optional[str] = None
    name: Optional[str] = None
    picture: Optional[str] = None
    role: str = "user"
    auth_provider: str = "guest"
    status: str = "active"
    created_at: datetime
    last_login_at: Optional[datetime] = None
    last_active_at: Optional[datetime] = None
    preferred_language: str = "tr"

class Profile(BaseModel):
    model_config = ConfigDict(extra="ignore")
    user_id: str
    age: Optional[int] = None
    height_cm: Optional[int] = None
    weight_kg: Optional[float] = None
    goal: Optional[str] = None  # lose, maintain, gain, healthy
    updated_at: datetime

class ChatMessage(BaseModel):
    model_config = ConfigDict(extra="ignore")
    id: str = Field(default_factory=lambda: str(uuid.uuid4()))
    user_id: str
    role: str  # user, assistant, system
    message_type: str  # routine, faq, motivation, personal_complex, out_of_scope
    message_text: str
    created_at: datetime = Field(default_factory=lambda: datetime.now(timezone.utc))

class ChatRequest(BaseModel):
    message: str
    language: str = "tr"

class DailyLog(BaseModel):
    model_config = ConfigDict(extra="ignore")
    id: str = Field(default_factory=lambda: str(uuid.uuid4()))
    user_id: str
    log_date: str
    mood: Optional[str] = None
    steps_level: Optional[str] = None  # low, medium, high
    water_ml: int = 0
    workout_done: bool = False
    updated_at: datetime = Field(default_factory=lambda: datetime.now(timezone.utc))

class WeightLog(BaseModel):
    model_config = ConfigDict(extra="ignore")
    id: str = Field(default_factory=lambda: str(uuid.uuid4()))
    user_id: str
    log_date: str
    weight_kg: float
    created_at: datetime = Field(default_factory=lambda: datetime.now(timezone.utc))

class QuickActionRequest(BaseModel):
    action_type: str  # water, steps, workout, weight
    value: Optional[Any] = None

class AdminSettings(BaseModel):
    setting_key: str
    setting_value: Any

# ==================== HELPER FUNCTIONS ====================

def get_default_settings():
    return {
        "openai_model": "gpt-4o-mini",
        "openai_max_tokens": 500,
        "openai_temperature": 0.7,
        "water_ml_per_kg": 30,
        "water_exercise_bonus_ml": 500,
        "glass_ml": 250,
        "steps_goal_lose": {"min": 8000, "max": 11000},
        "steps_goal_maintain": {"min": 6000, "max": 8500},
        "steps_goal_gain": {"min": 5000, "max": 7000},
        "weight_checkin_days": 7,
        "daily_message_limit_guest": 10,
        "daily_message_limit_user": 50,
        "daily_token_limit_guest": 2000,
        "daily_token_limit_user": 10000,
        "rate_limit_per_minute": 10,
        "cache_ttl_days": 180,
        "max_suggestions": 6,
        "anon_message_threshold": 8
    }

async def get_setting(key: str):
    setting = await db.admin_settings.find_one({"setting_key": key}, {"_id": 0})
    if setting:
        return setting.get("setting_value")
    defaults = get_default_settings()
    return defaults.get(key)

async def get_or_create_guest_user(guest_id: str):
    user = await db.users.find_one({"user_id": guest_id}, {"_id": 0})
    if not user:
        user_doc = {
            "user_id": guest_id,
            "email": None,
            "name": None,
            "picture": None,
            "role": "user",
            "auth_provider": "guest",
            "status": "active",
            "created_at": datetime.now(timezone.utc).isoformat(),
            "last_login_at": None,
            "last_active_at": datetime.now(timezone.utc).isoformat(),
            "preferred_language": "tr"
        }
        await db.users.insert_one(user_doc)
        user = user_doc
    return user

async def get_current_user(request: Request) -> Optional[Dict]:
    # Check cookie first
    session_token = request.cookies.get("session_token")
    # Then check Authorization header
    if not session_token:
        auth_header = request.headers.get("Authorization")
        if auth_header and auth_header.startswith("Bearer "):
            session_token = auth_header.split(" ")[1]
    
    if not session_token:
        return None
    
    session = await db.user_sessions.find_one({"session_token": session_token}, {"_id": 0})
    if not session:
        return None
    
    # Check expiry
    expires_at = session.get("expires_at")
    if isinstance(expires_at, str):
        expires_at = datetime.fromisoformat(expires_at)
    if expires_at.tzinfo is None:
        expires_at = expires_at.replace(tzinfo=timezone.utc)
    if expires_at < datetime.now(timezone.utc):
        return None
    
    user = await db.users.find_one({"user_id": session["user_id"]}, {"_id": 0})
    return user

def classify_message(message: str, language: str = "tr") -> str:
    """Classify message type for routing"""
    message_lower = message.lower()
    
    # Out of scope keywords
    out_of_scope_keywords = [
        "siyaset", "politika", "din", "inanç", "futbol", "maç", "film", "dizi",
        "politics", "religion", "football", "movie", "series", "game",
        "politik", "religion", "fußball", "film", "serie"
    ]
    for kw in out_of_scope_keywords:
        if kw in message_lower:
            return "out_of_scope"
    
    # Routine keywords (water, steps, workout, weight logging)
    routine_keywords = [
        "su", "bardak", "water", "glass", "wasser", "agua", "eau",
        "adım", "yürü", "steps", "walk", "schritt", "paso", "pas",
        "spor", "egzersiz", "workout", "exercise", "sport", "ejercicio",
        "kilo", "tartı", "weight", "gewicht", "peso", "poids"
    ]
    for kw in routine_keywords:
        if kw in message_lower:
            return "routine"
    
    # Motivation keywords
    motivation_keywords = [
        "motivasyon", "motive", "motivation", "teşvik", "encourage",
        "başaramıyorum", "zor", "difficult", "hard", "schwer", "difícil",
        "vazgeç", "give up", "aufgeben", "abandonar"
    ]
    for kw in motivation_keywords:
        if kw in message_lower:
            return "motivation"
    
    # FAQ patterns
    faq_patterns = [
        "ne kadar", "how much", "wie viel", "cuánto", "combien",
        "nedir", "what is", "was ist", "qué es", "qu'est-ce",
        "nasıl", "how to", "wie", "cómo", "comment"
    ]
    for pattern in faq_patterns:
        if pattern in message_lower:
            return "faq"
    
    return "personal_complex"

def get_water_target(weight_kg: float, has_exercise: bool = False) -> int:
    """Calculate daily water target in ml"""
    base = int(weight_kg * 30)
    if has_exercise:
        base += 500
    return base

def get_steps_target(goal: str) -> dict:
    """Get steps target based on goal"""
    targets = {
        "lose": {"min": 8000, "max": 11000},
        "maintain": {"min": 6000, "max": 8500},
        "gain": {"min": 5000, "max": 7000},
        "healthy": {"min": 7000, "max": 10000}
    }
    return targets.get(goal, {"min": 7000, "max": 10000})

async def get_cached_response(question: str) -> Optional[str]:
    """Check FAQ and cache for similar questions"""
    # Normalize question
    normalized = question.lower().strip()
    fingerprint = hashlib.sha256(normalized.encode()).hexdigest()[:32]
    
    # Check cache
    cached = await db.qa_cache.find_one({"question_fingerprint": fingerprint}, {"_id": 0})
    if cached:
        await db.qa_cache.update_one(
            {"question_fingerprint": fingerprint},
            {"$inc": {"hit_count": 1}}
        )
        return cached.get("answer_text")
    
    return None

async def cache_response(question: str, answer: str, topic: str = "general"):
    """Cache AI response for future use"""
    normalized = question.lower().strip()
    fingerprint = hashlib.sha256(normalized.encode()).hexdigest()[:32]
    
    cache_doc = {
        "topic": topic,
        "question_fingerprint": fingerprint,
        "normalized_question": normalized[:255],
        "answer_text": answer,
        "hit_count": 0,
        "expires_at": (datetime.now(timezone.utc) + timedelta(days=180)).isoformat(),
        "created_at": datetime.now(timezone.utc).isoformat(),
        "updated_at": datetime.now(timezone.utc).isoformat()
    }
    
    await db.qa_cache.update_one(
        {"question_fingerprint": fingerprint},
        {"$set": cache_doc},
        upsert=True
    )

async def check_rate_limit(user_id: str) -> bool:
    """Check if user has exceeded rate limits"""
    now = datetime.now(timezone.utc)
    one_minute_ago = now - timedelta(minutes=1)
    
    count = await db.chat_messages.count_documents({
        "user_id": user_id,
        "role": "user",
        "created_at": {"$gte": one_minute_ago.isoformat()}
    })
    
    limit = await get_setting("rate_limit_per_minute")
    return count < limit

async def check_daily_limit(user_id: str, is_guest: bool) -> bool:
    """Check daily message limit"""
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    
    count = await db.chat_messages.count_documents({
        "user_id": user_id,
        "role": "user",
        "created_at": {"$regex": f"^{today}"}
    })
    
    limit_key = "daily_message_limit_guest" if is_guest else "daily_message_limit_user"
    limit = await get_setting(limit_key)
    return count < limit

# ==================== SYSTEM PROMPTS ====================

SYSTEM_PROMPTS = {
    "tr": """Sen Vitalia, kişisel sağlıklı yaşam danışmanısın. Görevin kullanıcıya beslenme, hareket, spor, su tüketimi ve kilo yönetimi konularında yardımcı olmak.

KURALLAR:
1. Sadece sağlık, beslenme, egzersiz, su ve kilo konularında yardım et
2. Sağlık dışı konularda nazikçe reddet: "Bu konuda yardımcı olamıyorum, ama sağlık hedeflerinle ilgili sorularını yanıtlamaktan mutluluk duyarım!"
3. ASLA tıbbi teşhis koyma veya ilaç önerme
4. Yargılayıcı olma, "bozdun" gibi ifadeler kullanma
5. Önerilerde en fazla 6 seçenek sun
6. Kısa ve motive edici cevaplar ver
7. Türkçe karakterlere dikkat et (ş, ğ, ü, ö, ç, ı)

ÖRNEK YANITLAR:
- Su: "Harika! Bir bardak daha su içtin. Bugünkü hedefe yaklaşıyorsun! 💧"
- Hareket: "Bugün orta seviye hareket etmişsin, harika gidiyorsun! 🚶‍♂️"
- Motivasyon: "Her adım önemli! Bugün küçük de olsa ilerleme kaydettin, devam et! 💪"
""",
    "en": """You are Vitalia, a personal healthy living advisor. Your job is to help users with nutrition, movement, exercise, hydration, and weight management.

RULES:
1. Only help with health, nutrition, exercise, water, and weight topics
2. Politely decline non-health topics: "I can't help with that, but I'd be happy to answer questions about your health goals!"
3. NEVER make medical diagnoses or recommend medications
4. Don't be judgmental, avoid phrases like "you failed"
5. Offer maximum 6 suggestions
6. Give short and motivating answers

EXAMPLE RESPONSES:
- Water: "Great! You drank another glass. You're getting closer to today's goal! 💧"
- Movement: "You had moderate activity today, you're doing great! 🚶‍♂️"
- Motivation: "Every step counts! You made progress today, keep going! 💪"
""",
    "de": """Du bist Vitalia, eine persönliche Gesundheitsberaterin. Deine Aufgabe ist es, Nutzern bei Ernährung, Bewegung, Sport, Flüssigkeitsaufnahme und Gewichtsmanagement zu helfen.

REGELN:
1. Hilf nur bei Gesundheit, Ernährung, Bewegung, Wasser und Gewicht
2. Lehne Nicht-Gesundheitsthemen höflich ab
3. Stelle NIEMALS medizinische Diagnosen
4. Sei nicht wertend
5. Biete maximal 6 Vorschläge an
6. Gib kurze und motivierende Antworten
""",
    "fr": """Tu es Vitalia, une conseillère en santé personnelle. Ton rôle est d'aider les utilisateurs avec la nutrition, le mouvement, l'exercice, l'hydratation et la gestion du poids.

RÈGLES:
1. N'aide qu'avec les sujets de santé, nutrition, exercice, eau et poids
2. Refuse poliment les sujets non liés à la santé
3. Ne fais JAMAIS de diagnostics médicaux
4. Ne sois pas critique
5. Offre maximum 6 suggestions
6. Donne des réponses courtes et motivantes
""",
    "es": """Eres Vitalia, una asesora de vida saludable personal. Tu trabajo es ayudar a los usuarios con nutrición, movimiento, ejercicio, hidratación y control de peso.

REGLAS:
1. Solo ayuda con temas de salud, nutrición, ejercicio, agua y peso
2. Rechaza educadamente los temas no relacionados con la salud
3. NUNCA hagas diagnósticos médicos
4. No seas crítico
5. Ofrece máximo 6 sugerencias
6. Da respuestas cortas y motivadoras
""",
    "it": """Sei Vitalia, una consulente personale per la vita sana. Il tuo compito è aiutare gli utenti con nutrizione, movimento, esercizio, idratazione e gestione del peso.

REGOLE:
1. Aiuta solo con argomenti di salute, nutrizione, esercizio, acqua e peso
2. Rifiuta educatamente gli argomenti non legati alla salute
3. Non fare MAI diagnosi mediche
4. Non essere critico
5. Offri massimo 6 suggerimenti
6. Dai risposte brevi e motivanti
""",
    "pt": """Você é Vitalia, uma consultora pessoal de vida saudável. Seu trabalho é ajudar os usuários com nutrição, movimento, exercício, hidratação e controle de peso.

REGRAS:
1. Ajude apenas com temas de saúde, nutrição, exercício, água e peso
2. Recuse educadamente temas não relacionados à saúde
3. NUNCA faça diagnósticos médicos
4. Não seja crítico
5. Ofereça no máximo 6 sugestões
6. Dê respostas curtas e motivadoras
""",
    "ru": """Ты Vitalia, персональный консультант по здоровому образу жизни. Твоя задача — помогать пользователям с питанием, движением, упражнениями, гидратацией и контролем веса.

ПРАВИЛА:
1. Помогай только с темами здоровья, питания, упражнений, воды и веса
2. Вежливо отклоняй темы, не связанные со здоровьем
3. НИКОГДА не ставь медицинские диагнозы
4. Не будь критичным
5. Предлагай максимум 6 вариантов
6. Давай короткие и мотивирующие ответы
""",
    "ar": """أنت فيتاليا، مستشار الحياة الصحية الشخصية. مهمتك مساعدة المستخدمين في التغذية والحركة والتمارين والترطيب وإدارة الوزن.

القواعد:
1. ساعد فقط في موضوعات الصحة والتغذية والتمارين والماء والوزن
2. ارفض بأدب المواضيع غير الصحية
3. لا تقم أبداً بتشخيصات طبية
4. لا تكن حكمياً
5. قدم 6 اقتراحات كحد أقصى
6. أعط إجابات قصيرة ومحفزة
""",
    "zh": """你是Vitalia，一位个人健康生活顾问。你的工作是帮助用户进行营养、运动、锻炼、补水和体重管理。

规则：
1. 只帮助健康、营养、运动、水和体重方面的话题
2. 礼貌地拒绝非健康话题
3. 绝不做医学诊断
4. 不要批判
5. 最多提供6个建议
6. 给出简短而有激励性的回答
"""
}

OUT_OF_SCOPE_RESPONSES = {
    "tr": "Bu konuda yardımcı olamıyorum, ama sağlık hedeflerinle ilgili sorularını yanıtlamaktan mutluluk duyarım! 🌿",
    "en": "I can't help with that topic, but I'd be happy to answer questions about your health goals! 🌿",
    "de": "Bei diesem Thema kann ich leider nicht helfen, aber ich beantworte gerne Fragen zu deinen Gesundheitszielen! 🌿",
    "fr": "Je ne peux pas aider sur ce sujet, mais je serais ravi de répondre à tes questions sur tes objectifs de santé ! 🌿",
    "es": "No puedo ayudar con ese tema, pero estaré encantado de responder preguntas sobre tus objetivos de salud! 🌿",
    "it": "Non posso aiutarti con questo argomento, ma sarò felice di rispondere alle domande sui tuoi obiettivi di salute! 🌿",
    "pt": "Não posso ajudar com esse tema, mas ficarei feliz em responder perguntas sobre seus objetivos de saúde! 🌿",
    "ru": "Я не могу помочь с этой темой, но буду рад ответить на вопросы о ваших целях по здоровью! 🌿",
    "ar": "لا أستطيع المساعدة في هذا الموضوع، لكنني سأكون سعيداً بالإجابة على أسئلتك حول أهدافك الصحية! 🌿",
    "zh": "我无法帮助这个话题，但我很乐意回答关于你健康目标的问题！🌿"
}

DAILY_LIMIT_RESPONSES = {
    "tr": "Bugünlük destek limitine ulaştık. Yarın devam edelim! Bu arada su içmeyi ve hareket etmeyi unutma! 💧🚶",
    "en": "We've reached today's support limit. Let's continue tomorrow! Meanwhile, don't forget to drink water and stay active! 💧🚶",
    "de": "Wir haben das heutige Limit erreicht. Machen wir morgen weiter! Vergiss nicht, Wasser zu trinken und aktiv zu bleiben! 💧🚶",
    "fr": "Nous avons atteint la limite d'aujourd'hui. Continuons demain ! En attendant, n'oublie pas de boire de l'eau et de rester actif ! 💧🚶",
    "es": "Hemos alcanzado el límite de hoy. ¡Continuemos mañana! Mientras tanto, no olvides beber agua y mantenerte activo! 💧🚶",
    "it": "Abbiamo raggiunto il limite di oggi. Continuiamo domani! Nel frattempo, non dimenticare di bere acqua e restare attivo! 💧🚶",
    "pt": "Atingimos o limite de hoje. Vamos continuar amanhã! Enquanto isso, não esqueça de beber água e se manter ativo! 💧🚶",
    "ru": "Мы достигли сегодняшнего лимита. Продолжим завтра! А пока не забывай пить воду и двигаться! 💧🚶",
    "ar": "لقد وصلنا إلى حد اليوم. لنكمل غداً! في هذه الأثناء، لا تنسَ شرب الماء والبقاء نشيطاً! 💧🚶",
    "zh": "我们已达到今天的支持限制。明天继续吧！同时，别忘了喝水和保持活动！💧🚶"
}

WELCOME_MESSAGES = {
    "tr": "Merhaba! 🌿 Ben Vitalia, senin kişisel sağlık danışmanın. Bugün kendini nasıl hissediyorsun?",
    "en": "Hello! 🌿 I'm Vitalia, your personal health advisor. How are you feeling today?",
    "de": "Hallo! 🌿 Ich bin Vitalia, deine persönliche Gesundheitsberaterin. Wie fühlst du dich heute?",
    "fr": "Bonjour! 🌿 Je suis Vitalia, ta conseillère santé personnelle. Comment te sens-tu aujourd'hui?",
    "es": "¡Hola! 🌿 Soy Vitalia, tu asesora de salud personal. ¿Cómo te sientes hoy?",
    "it": "Ciao! 🌿 Sono Vitalia, la tua consulente di salute personale. Come ti senti oggi?",
    "pt": "Olá! 🌿 Sou Vitalia, sua consultora de saúde pessoal. Como você está se sentindo hoje?",
    "ru": "Привет! 🌿 Я Vitalia, твой персональный консультант по здоровью. Как ты себя чувствуешь сегодня?",
    "ar": "مرحباً! 🌿 أنا فيتاليا، مستشارتك الصحية الشخصية. كيف تشعر اليوم؟",
    "zh": "你好！🌿 我是Vitalia，你的个人健康顾问。你今天感觉怎么样？"
}

# ==================== AUTH ENDPOINTS ====================

@api_router.post("/auth/session")
async def create_session(request: Request, response: Response):
    """Exchange session_id for session_token after Google OAuth"""
    body = await request.json()
    session_id = body.get("session_id")
    guest_id = body.get("guest_id")
    
    if not session_id:
        raise HTTPException(status_code=400, detail="session_id required")
    
    # Call Emergent Auth to get user data
    async with httpx.AsyncClient() as client:
        try:
            auth_response = await client.get(
                "https://demobackend.emergentagent.com/auth/v1/env/oauth/session-data",
                headers={"X-Session-ID": session_id}
            )
            if auth_response.status_code != 200:
                raise HTTPException(status_code=401, detail="Invalid session_id")
            
            auth_data = auth_response.json()
        except Exception as e:
            logger.error(f"Auth error: {e}")
            raise HTTPException(status_code=401, detail="Authentication failed")
    
    # Check if user exists
    existing_user = await db.users.find_one({"email": auth_data["email"]}, {"_id": 0})
    
    if existing_user:
        user_id = existing_user["user_id"]
        # Update last login
        await db.users.update_one(
            {"user_id": user_id},
            {"$set": {
                "last_login_at": datetime.now(timezone.utc).isoformat(),
                "last_active_at": datetime.now(timezone.utc).isoformat(),
                "name": auth_data.get("name"),
                "picture": auth_data.get("picture")
            }}
        )
    else:
        # Create new user
        user_id = f"user_{uuid.uuid4().hex[:12]}"
        user_doc = {
            "user_id": user_id,
            "email": auth_data["email"],
            "name": auth_data.get("name"),
            "picture": auth_data.get("picture"),
            "role": "user",
            "auth_provider": "google",
            "status": "active",
            "created_at": datetime.now(timezone.utc).isoformat(),
            "last_login_at": datetime.now(timezone.utc).isoformat(),
            "last_active_at": datetime.now(timezone.utc).isoformat(),
            "preferred_language": "tr"
        }
        await db.users.insert_one(user_doc)
    
    # Merge guest data if guest_id provided
    if guest_id:
        await db.chat_messages.update_many(
            {"user_id": guest_id},
            {"$set": {"user_id": user_id}}
        )
        await db.daily_logs.update_many(
            {"user_id": guest_id},
            {"$set": {"user_id": user_id}}
        )
        await db.weight_logs.update_many(
            {"user_id": guest_id},
            {"$set": {"user_id": user_id}}
        )
        # Delete guest user
        await db.users.delete_one({"user_id": guest_id})
    
    # Create session
    session_token = auth_data.get("session_token", f"session_{uuid.uuid4().hex}")
    session_doc = {
        "user_id": user_id,
        "session_token": session_token,
        "expires_at": (datetime.now(timezone.utc) + timedelta(days=7)).isoformat(),
        "created_at": datetime.now(timezone.utc).isoformat()
    }
    
    await db.user_sessions.update_one(
        {"user_id": user_id},
        {"$set": session_doc},
        upsert=True
    )
    
    # Set cookie
    response.set_cookie(
        key="session_token",
        value=session_token,
        httponly=True,
        secure=True,
        samesite="none",
        path="/",
        max_age=7*24*60*60
    )
    
    user = await db.users.find_one({"user_id": user_id}, {"_id": 0})
    return {"user": user, "session_token": session_token}

@api_router.get("/auth/me")
async def get_current_user_endpoint(request: Request):
    """Get current authenticated user"""
    user = await get_current_user(request)
    if not user:
        raise HTTPException(status_code=401, detail="Not authenticated")
    return user

@api_router.post("/auth/logout")
async def logout(request: Request, response: Response):
    """Logout user"""
    session_token = request.cookies.get("session_token")
    if session_token:
        await db.user_sessions.delete_one({"session_token": session_token})
    
    response.delete_cookie(key="session_token", path="/")
    return {"message": "Logged out successfully"}

# ==================== CHAT ENDPOINTS ====================

@api_router.post("/chat/send")
async def send_chat_message(chat_request: ChatRequest, request: Request):
    """Main chat endpoint"""
    message = chat_request.message.strip()
    language = chat_request.language
    
    # Get user (authenticated or guest)
    user = await get_current_user(request)
    is_guest = False
    
    if not user:
        # Create or get guest user
        guest_id = request.cookies.get("guest_id") or f"guest_{uuid.uuid4().hex[:12]}"
        user = await get_or_create_guest_user(guest_id)
        is_guest = True
    
    user_id = user["user_id"]
    
    # Update last active
    await db.users.update_one(
        {"user_id": user_id},
        {"$set": {"last_active_at": datetime.now(timezone.utc).isoformat()}}
    )
    
    # Check rate limit
    if not await check_rate_limit(user_id):
        return {
            "response": "Biraz yavaşla! Çok hızlı mesaj gönderiyorsun. 🐢",
            "message_type": "rate_limit",
            "user_id": user_id,
            "is_guest": is_guest
        }
    
    # Check daily limit
    if not await check_daily_limit(user_id, is_guest):
        return {
            "response": DAILY_LIMIT_RESPONSES.get(language, DAILY_LIMIT_RESPONSES["en"]),
            "message_type": "daily_limit",
            "user_id": user_id,
            "is_guest": is_guest
        }
    
    # Save user message
    user_msg_doc = {
        "id": str(uuid.uuid4()),
        "user_id": user_id,
        "role": "user",
        "message_type": "pending",
        "message_text": message,
        "created_at": datetime.now(timezone.utc).isoformat()
    }
    await db.chat_messages.insert_one(user_msg_doc)
    
    # Classify message
    message_type = classify_message(message, language)
    
    # Update message type
    await db.chat_messages.update_one(
        {"id": user_msg_doc["id"]},
        {"$set": {"message_type": message_type}}
    )
    
    # Handle out of scope
    if message_type == "out_of_scope":
        response_text = OUT_OF_SCOPE_RESPONSES.get(language, OUT_OF_SCOPE_RESPONSES["en"])
        assistant_msg = {
            "id": str(uuid.uuid4()),
            "user_id": user_id,
            "role": "assistant",
            "message_type": "out_of_scope",
            "message_text": response_text,
            "created_at": datetime.now(timezone.utc).isoformat()
        }
        await db.chat_messages.insert_one(assistant_msg)
        return {
            "response": response_text,
            "message_type": "out_of_scope",
            "user_id": user_id,
            "is_guest": is_guest
        }
    
    # Check cache for FAQ
    cached_response = await get_cached_response(message)
    if cached_response:
        assistant_msg = {
            "id": str(uuid.uuid4()),
            "user_id": user_id,
            "role": "assistant",
            "message_type": "faq",
            "message_text": cached_response,
            "created_at": datetime.now(timezone.utc).isoformat()
        }
        await db.chat_messages.insert_one(assistant_msg)
        return {
            "response": cached_response,
            "message_type": "faq",
            "user_id": user_id,
            "is_guest": is_guest,
            "cached": True
        }
    
    # Get user profile for context
    profile = await db.profiles.find_one({"user_id": user_id}, {"_id": 0})
    
    # Build context
    context_parts = []
    if profile:
        if profile.get("weight_kg"):
            water_target = get_water_target(profile["weight_kg"])
            context_parts.append(f"User weight: {profile['weight_kg']}kg, daily water target: {water_target}ml")
        if profile.get("goal"):
            steps_target = get_steps_target(profile["goal"])
            context_parts.append(f"User goal: {profile['goal']}, steps target: {steps_target['min']}-{steps_target['max']}")
    
    # Get today's logs
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    today_log = await db.daily_logs.find_one({"user_id": user_id, "log_date": today}, {"_id": 0})
    if today_log:
        context_parts.append(f"Today's progress - Water: {today_log.get('water_ml', 0)}ml, Steps: {today_log.get('steps_level', 'not logged')}, Workout: {'Yes' if today_log.get('workout_done') else 'No'}")
    
    context_message = "\n".join(context_parts) if context_parts else ""
    
    # Call AI
    try:
        system_prompt = SYSTEM_PROMPTS.get(language, SYSTEM_PROMPTS["en"])
        if context_message:
            system_prompt += f"\n\nCurrent user context:\n{context_message}"
        
        chat = LlmChat(
            api_key=EMERGENT_LLM_KEY,
            session_id=f"vitalia_{user_id}",
            system_message=system_prompt
        ).with_model("openai", "gpt-4o-mini")
        
        user_message = UserMessage(text=message)
        response_text = await chat.send_message(user_message)
        
        # Cache response if it's a general question
        if message_type in ["faq", "motivation"]:
            await cache_response(message, response_text, message_type)
        
        # Log usage
        today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
        await db.openai_usage_daily.update_one(
            {"user_id": user_id, "usage_date": today},
            {"$inc": {"calls_count": 1, "input_tokens": len(message) // 4, "output_tokens": len(response_text) // 4}},
            upsert=True
        )
        
    except Exception as e:
        logger.error(f"AI error: {e}")
        response_text = "Şu anda yanıt veremiyorum, lütfen biraz sonra tekrar dene. 🙏"
    
    # Save assistant message
    assistant_msg = {
        "id": str(uuid.uuid4()),
        "user_id": user_id,
        "role": "assistant",
        "message_type": message_type,
        "message_text": response_text,
        "created_at": datetime.now(timezone.utc).isoformat()
    }
    await db.chat_messages.insert_one(assistant_msg)
    
    # Check if we should prompt for login
    prompt_login = False
    if is_guest:
        message_count = await db.chat_messages.count_documents({"user_id": user_id, "role": "user"})
        threshold = await get_setting("anon_message_threshold")
        if message_count >= threshold:
            prompt_login = True
    
    return {
        "response": response_text,
        "message_type": message_type,
        "user_id": user_id,
        "is_guest": is_guest,
        "prompt_login": prompt_login
    }

@api_router.get("/chat/history")
async def get_chat_history(request: Request, limit: int = 50):
    """Get chat history for current user"""
    user = await get_current_user(request)
    
    if not user:
        guest_id = request.cookies.get("guest_id")
        if not guest_id:
            return {"messages": [], "user_id": None}
        user = {"user_id": guest_id}
    
    messages = await db.chat_messages.find(
        {"user_id": user["user_id"]},
        {"_id": 0}
    ).sort("created_at", -1).limit(limit).to_list(limit)
    
    messages.reverse()  # Return in chronological order
    return {"messages": messages, "user_id": user["user_id"]}

@api_router.get("/chat/welcome")
async def get_welcome_message(language: str = "tr"):
    """Get welcome message for new users"""
    return {"message": WELCOME_MESSAGES.get(language, WELCOME_MESSAGES["en"])}

# ==================== LOGGING ENDPOINTS ====================

@api_router.post("/log/quick-action")
async def log_quick_action(action: QuickActionRequest, request: Request):
    """Handle quick actions (water, steps, workout, weight)"""
    user = await get_current_user(request)
    
    if not user:
        guest_id = request.cookies.get("guest_id") or f"guest_{uuid.uuid4().hex[:12]}"
        user = await get_or_create_guest_user(guest_id)
    
    user_id = user["user_id"]
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    
    if action.action_type == "water":
        amount = action.value or 250  # Default 1 glass
        await db.daily_logs.update_one(
            {"user_id": user_id, "log_date": today},
            {
                "$inc": {"water_ml": amount},
                "$set": {"updated_at": datetime.now(timezone.utc).isoformat()},
                "$setOnInsert": {"id": str(uuid.uuid4()), "mood": None, "steps_level": None, "workout_done": False}
            },
            upsert=True
        )
        log = await db.daily_logs.find_one({"user_id": user_id, "log_date": today}, {"_id": 0})
        return {"success": True, "action": "water", "total_water_ml": log.get("water_ml", 0)}
    
    elif action.action_type == "steps":
        level = action.value or "medium"  # low, medium, high
        await db.daily_logs.update_one(
            {"user_id": user_id, "log_date": today},
            {
                "$set": {"steps_level": level, "updated_at": datetime.now(timezone.utc).isoformat()},
                "$setOnInsert": {"id": str(uuid.uuid4()), "mood": None, "water_ml": 0, "workout_done": False}
            },
            upsert=True
        )
        return {"success": True, "action": "steps", "steps_level": level}
    
    elif action.action_type == "workout":
        done = action.value if action.value is not None else True
        await db.daily_logs.update_one(
            {"user_id": user_id, "log_date": today},
            {
                "$set": {"workout_done": done, "updated_at": datetime.now(timezone.utc).isoformat()},
                "$setOnInsert": {"id": str(uuid.uuid4()), "mood": None, "water_ml": 0, "steps_level": None}
            },
            upsert=True
        )
        return {"success": True, "action": "workout", "workout_done": done}
    
    elif action.action_type == "weight":
        if action.value is None:
            raise HTTPException(status_code=400, detail="Weight value required")
        
        weight_kg = float(action.value)
        weight_doc = {
            "id": str(uuid.uuid4()),
            "user_id": user_id,
            "log_date": today,
            "weight_kg": weight_kg,
            "created_at": datetime.now(timezone.utc).isoformat()
        }
        
        await db.weight_logs.update_one(
            {"user_id": user_id, "log_date": today},
            {"$set": weight_doc},
            upsert=True
        )
        
        # Also update profile
        await db.profiles.update_one(
            {"user_id": user_id},
            {
                "$set": {"weight_kg": weight_kg, "updated_at": datetime.now(timezone.utc).isoformat()},
                "$setOnInsert": {"age": None, "height_cm": None, "goal": None}
            },
            upsert=True
        )
        
        return {"success": True, "action": "weight", "weight_kg": weight_kg}
    
    raise HTTPException(status_code=400, detail="Invalid action type")

@api_router.get("/log/today")
async def get_today_log(request: Request):
    """Get today's daily log"""
    user = await get_current_user(request)
    
    if not user:
        guest_id = request.cookies.get("guest_id")
        if not guest_id:
            # Return empty log structure for new anonymous users
            return {
                "log": {"water_ml": 0, "steps_level": None, "workout_done": False, "mood": None},
                "targets": {"water_ml": 2000, "glass_ml": 250}
            }
        user = {"user_id": guest_id}
    
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    log = await db.daily_logs.find_one({"user_id": user["user_id"], "log_date": today}, {"_id": 0})
    
    # Get profile for targets
    profile = await db.profiles.find_one({"user_id": user["user_id"]}, {"_id": 0})
    
    water_target = 2000  # Default
    if profile and profile.get("weight_kg"):
        water_target = get_water_target(profile["weight_kg"])
    
    # Return proper empty structure if no log exists
    default_log = {"water_ml": 0, "steps_level": None, "workout_done": False, "mood": None}
    
    return {
        "log": log or default_log,
        "targets": {
            "water_ml": water_target,
            "glass_ml": 250
        }
    }

@api_router.get("/log/history")
async def get_log_history(request: Request, days: int = 7):
    """Get logging history"""
    user = await get_current_user(request)
    
    if not user:
        guest_id = request.cookies.get("guest_id")
        if not guest_id:
            return {"daily_logs": [], "weight_logs": []}
        user = {"user_id": guest_id}
    
    # Get daily logs
    daily_logs = await db.daily_logs.find(
        {"user_id": user["user_id"]},
        {"_id": 0}
    ).sort("log_date", -1).limit(days).to_list(days)
    
    # Get weight logs
    weight_logs = await db.weight_logs.find(
        {"user_id": user["user_id"]},
        {"_id": 0}
    ).sort("log_date", -1).limit(days).to_list(days)
    
    return {"daily_logs": daily_logs, "weight_logs": weight_logs}

# ==================== PROFILE ENDPOINTS ====================

@api_router.get("/profile")
async def get_profile(request: Request):
    """Get user profile"""
    user = await get_current_user(request)
    if not user:
        raise HTTPException(status_code=401, detail="Not authenticated")
    
    profile = await db.profiles.find_one({"user_id": user["user_id"]}, {"_id": 0})
    return {"user": user, "profile": profile}

@api_router.post("/profile")
async def update_profile(request: Request):
    """Update user profile"""
    user = await get_current_user(request)
    if not user:
        raise HTTPException(status_code=401, detail="Not authenticated")
    
    body = await request.json()
    update_data = {
        "updated_at": datetime.now(timezone.utc).isoformat()
    }
    
    for field in ["age", "height_cm", "weight_kg", "goal"]:
        if field in body:
            update_data[field] = body[field]
    
    await db.profiles.update_one(
        {"user_id": user["user_id"]},
        {"$set": update_data},
        upsert=True
    )
    
    # Update preferred language if provided
    if "preferred_language" in body:
        await db.users.update_one(
            {"user_id": user["user_id"]},
            {"$set": {"preferred_language": body["preferred_language"]}}
        )
    
    profile = await db.profiles.find_one({"user_id": user["user_id"]}, {"_id": 0})
    return {"profile": profile}

# ==================== ADMIN ENDPOINTS ====================

async def require_admin(request: Request):
    """Check if user is admin"""
    user = await get_current_user(request)
    if not user or user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin access required")
    return user

@api_router.get("/admin/dashboard")
async def admin_dashboard(request: Request):
    """Get admin dashboard data"""
    await require_admin(request)
    
    now = datetime.now(timezone.utc)
    today = now.strftime("%Y-%m-%d")
    week_ago = (now - timedelta(days=7)).isoformat()
    month_ago = (now - timedelta(days=30)).isoformat()
    
    # User stats
    total_users = await db.users.count_documents({})
    new_users_7d = await db.users.count_documents({"created_at": {"$gte": week_ago}})
    new_users_30d = await db.users.count_documents({"created_at": {"$gte": month_ago}})
    
    # Active users
    dau = await db.users.count_documents({"last_active_at": {"$regex": f"^{today}"}})
    wau = await db.users.count_documents({"last_active_at": {"$gte": week_ago}})
    
    # Message stats
    total_messages = await db.chat_messages.count_documents({})
    messages_today = await db.chat_messages.count_documents({"created_at": {"$regex": f"^{today}"}})
    
    # OpenAI usage
    usage_today = await db.openai_usage_daily.find_one({"usage_date": today}, {"_id": 0})
    
    # Cache stats
    total_cached = await db.qa_cache.count_documents({})
    cache_hits = await db.qa_cache.aggregate([
        {"$group": {"_id": None, "total_hits": {"$sum": "$hit_count"}}}
    ]).to_list(1)
    
    return {
        "users": {
            "total": total_users,
            "new_7d": new_users_7d,
            "new_30d": new_users_30d,
            "dau": dau,
            "wau": wau
        },
        "messages": {
            "total": total_messages,
            "today": messages_today
        },
        "openai": usage_today or {"calls_count": 0, "input_tokens": 0, "output_tokens": 0},
        "cache": {
            "total_entries": total_cached,
            "total_hits": cache_hits[0]["total_hits"] if cache_hits else 0
        }
    }

@api_router.get("/admin/users")
async def admin_get_users(request: Request, skip: int = 0, limit: int = 50):
    """Get all users for admin"""
    await require_admin(request)
    
    users = await db.users.find({}, {"_id": 0}).skip(skip).limit(limit).to_list(limit)
    total = await db.users.count_documents({})
    
    return {"users": users, "total": total, "skip": skip, "limit": limit}

@api_router.get("/admin/users/{user_id}")
async def admin_get_user(user_id: str, request: Request):
    """Get user details for admin"""
    await require_admin(request)
    
    user = await db.users.find_one({"user_id": user_id}, {"_id": 0})
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    profile = await db.profiles.find_one({"user_id": user_id}, {"_id": 0})
    
    # Get recent logs
    daily_logs = await db.daily_logs.find(
        {"user_id": user_id},
        {"_id": 0}
    ).sort("log_date", -1).limit(30).to_list(30)
    
    weight_logs = await db.weight_logs.find(
        {"user_id": user_id},
        {"_id": 0}
    ).sort("log_date", -1).limit(30).to_list(30)
    
    # Calculate compliance score
    compliance = {}
    if daily_logs:
        water_days = sum(1 for log in daily_logs if log.get("water_ml", 0) > 0)
        steps_days = sum(1 for log in daily_logs if log.get("steps_level"))
        workout_days = sum(1 for log in daily_logs if log.get("workout_done"))
        
        total_days = len(daily_logs)
        compliance = {
            "water_compliance": round(water_days / total_days * 100) if total_days else 0,
            "steps_compliance": round(steps_days / total_days * 100) if total_days else 0,
            "workout_compliance": round(workout_days / total_days * 100) if total_days else 0
        }
    
    return {
        "user": user,
        "profile": profile,
        "daily_logs": daily_logs,
        "weight_logs": weight_logs,
        "compliance": compliance
    }

@api_router.post("/admin/users/{user_id}/ban")
async def admin_ban_user(user_id: str, request: Request):
    """Ban/unban user"""
    await require_admin(request)
    
    body = await request.json()
    status = "banned" if body.get("ban", True) else "active"
    
    result = await db.users.update_one(
        {"user_id": user_id},
        {"$set": {"status": status}}
    )
    
    if result.matched_count == 0:
        raise HTTPException(status_code=404, detail="User not found")
    
    return {"success": True, "user_id": user_id, "status": status}

@api_router.get("/admin/settings")
async def admin_get_settings(request: Request):
    """Get all admin settings"""
    await require_admin(request)
    
    settings = await db.admin_settings.find({}, {"_id": 0}).to_list(100)
    defaults = get_default_settings()
    
    # Merge with defaults
    current = {s["setting_key"]: s["setting_value"] for s in settings}
    for key, value in defaults.items():
        if key not in current:
            current[key] = value
    
    return {"settings": current}

@api_router.post("/admin/settings")
async def admin_update_settings(request: Request):
    """Update admin settings"""
    await require_admin(request)
    
    body = await request.json()
    
    for key, value in body.items():
        await db.admin_settings.update_one(
            {"setting_key": key},
            {"$set": {"setting_key": key, "setting_value": value, "updated_at": datetime.now(timezone.utc).isoformat()}},
            upsert=True
        )
    
    return {"success": True}

@api_router.get("/admin/faq")
async def admin_get_faq(request: Request):
    """Get FAQ entries"""
    await require_admin(request)
    
    faqs = await db.qa_faq.find({}, {"_id": 0}).to_list(100)
    return {"faqs": faqs}

@api_router.post("/admin/faq")
async def admin_create_faq(request: Request):
    """Create FAQ entry"""
    await require_admin(request)
    
    body = await request.json()
    faq_doc = {
        "id": str(uuid.uuid4()),
        "topic": body.get("topic", "general"),
        "tag": body.get("tag", ""),
        "question_example": body.get("question_example", ""),
        "answer_text": body.get("answer_text", ""),
        "is_active": body.get("is_active", True),
        "created_at": datetime.now(timezone.utc).isoformat()
    }
    
    await db.qa_faq.insert_one(faq_doc)
    return {"success": True, "faq": faq_doc}

# ==================== BASIC ENDPOINTS ====================

@api_router.get("/")
async def root():
    return {"message": "Vitalia API - Personal Health Advisor"}

@api_router.get("/health")
async def health_check():
    return {"status": "healthy", "timestamp": datetime.now(timezone.utc).isoformat()}

# Include the router in the main app
app.include_router(api_router)

app.add_middleware(
    CORSMiddleware,
    allow_credentials=True,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.on_event("shutdown")
async def shutdown_db_client():
    client.close()
