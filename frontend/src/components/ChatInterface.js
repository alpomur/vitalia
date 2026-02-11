import React, { useState, useEffect, useRef, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Send, Droplets, Footprints, Dumbbell, Scale, Sparkles, Menu, X } from 'lucide-react';
import { Button } from '../components/ui/button';
import { ScrollArea } from '../components/ui/scroll-area';
import { useLanguage } from '../i18n';
import confetti from 'canvas-confetti';

const API_URL = process.env.REACT_APP_BACKEND_URL;

// Chat Bubble Component
const ChatBubble = ({ message, isUser, isNew }) => {
  return (
    <motion.div
      initial={isNew ? { opacity: 0, y: 20, scale: 0.95 } : false}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      transition={{ type: "spring", stiffness: 300, damping: 25 }}
      className={`flex ${isUser ? 'justify-end' : 'justify-start'} mb-4`}
    >
      <div
        className={`max-w-[85%] p-4 shadow-sm ${
          isUser
            ? 'bg-primary text-primary-foreground rounded-2xl rounded-tr-none chat-bubble-user'
            : 'bg-card border border-border rounded-2xl rounded-tl-none chat-bubble-ai'
        }`}
        data-testid={isUser ? "chat-bubble-user" : "chat-bubble-ai"}
      >
        <p className="text-sm leading-relaxed whitespace-pre-wrap">{message.message_text}</p>
      </div>
    </motion.div>
  );
};

// Typing Indicator
const TypingIndicator = () => {
  const { t } = useLanguage();
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -10 }}
      className="flex justify-start mb-4"
    >
      <div className="bg-card border border-border rounded-2xl rounded-tl-none p-4 shadow-sm">
        <div className="flex items-center gap-2">
          <div className="flex gap-1">
            <span className="typing-dot"></span>
            <span className="typing-dot"></span>
            <span className="typing-dot"></span>
          </div>
          <span className="text-xs text-muted-foreground ml-2">{t('chat.typing')}</span>
        </div>
      </div>
    </motion.div>
  );
};

// Quick Actions Panel
const QuickActionsPanel = ({ onAction, todayLog, isLoading }) => {
  const { t } = useLanguage();
  const waterGlasses = Math.floor((todayLog?.water_ml || 0) / 250);
  
  return (
    <div className="grid grid-cols-4 gap-2 p-3 bg-muted/50 rounded-xl" data-testid="quick-actions-panel">
      {/* Water */}
      <button
        onClick={() => onAction('water', 250)}
        disabled={isLoading}
        className="flex flex-col items-center gap-1 p-3 rounded-xl bg-card hover:bg-accent transition-all active:scale-95 border border-border"
        data-testid="quick-action-water"
      >
        <Droplets className="w-5 h-5 text-water" />
        <span className="text-xs font-medium">{waterGlasses}</span>
        <span className="text-[10px] text-muted-foreground">{t('quickActions.water')}</span>
      </button>

      {/* Steps */}
      <button
        onClick={() => onAction('steps', 'medium')}
        disabled={isLoading}
        className="flex flex-col items-center gap-1 p-3 rounded-xl bg-card hover:bg-accent transition-all active:scale-95 border border-border"
        data-testid="quick-action-steps"
      >
        <Footprints className="w-5 h-5 text-success" />
        <span className="text-xs font-medium capitalize">{todayLog?.steps_level || '-'}</span>
        <span className="text-[10px] text-muted-foreground">{t('quickActions.steps')}</span>
      </button>

      {/* Workout */}
      <button
        onClick={() => onAction('workout', !todayLog?.workout_done)}
        disabled={isLoading}
        className={`flex flex-col items-center gap-1 p-3 rounded-xl transition-all active:scale-95 border ${
          todayLog?.workout_done 
            ? 'bg-primary/10 border-primary' 
            : 'bg-card hover:bg-accent border-border'
        }`}
        data-testid="quick-action-workout"
      >
        <Dumbbell className={`w-5 h-5 ${todayLog?.workout_done ? 'text-primary' : 'text-warning'}`} />
        <span className="text-xs font-medium">{todayLog?.workout_done ? '✓' : '-'}</span>
        <span className="text-[10px] text-muted-foreground">{t('quickActions.workout')}</span>
      </button>

      {/* Weight */}
      <button
        onClick={() => {
          const weight = prompt(t('profile.weight'));
          if (weight && !isNaN(parseFloat(weight))) {
            onAction('weight', parseFloat(weight));
          }
        }}
        disabled={isLoading}
        className="flex flex-col items-center gap-1 p-3 rounded-xl bg-card hover:bg-accent transition-all active:scale-95 border border-border"
        data-testid="quick-action-weight"
      >
        <Scale className="w-5 h-5 text-secondary" />
        <span className="text-xs font-medium">-</span>
        <span className="text-[10px] text-muted-foreground">{t('quickActions.weight')}</span>
      </button>
    </div>
  );
};

// Login Prompt Component
const LoginPrompt = ({ onLogin, onDismiss }) => {
  const { t } = useLanguage();
  
  const handleGoogleLogin = () => {
    // REMINDER: DO NOT HARDCODE THE URL, OR ADD ANY FALLBACKS OR REDIRECT URLS, THIS BREAKS THE AUTH
    const redirectUrl = window.location.origin + '/auth/callback';
    window.location.href = `https://auth.emergentagent.com/?redirect=${encodeURIComponent(redirectUrl)}`;
  };
  
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -20 }}
      className="bg-card border border-border rounded-2xl p-5 shadow-lg mx-4 mb-4"
      data-testid="login-prompt"
    >
      <div className="flex items-start gap-3">
        <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
          <Sparkles className="w-5 h-5 text-primary" />
        </div>
        <div className="flex-1">
          <p className="font-medium text-foreground mb-1">{t('auth.loginPrompt')}</p>
          <p className="text-sm text-muted-foreground mb-4">{t('auth.loginPromptSub')}</p>
          <div className="flex flex-col gap-2">
            <Button 
              onClick={handleGoogleLogin}
              className="w-full bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 font-medium"
              data-testid="google-login-btn"
            >
              <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
              {t('auth.loginWithGoogle')}
            </Button>
            <Button 
              variant="ghost" 
              onClick={onDismiss}
              className="w-full text-muted-foreground"
              data-testid="dismiss-login-btn"
            >
              {t('auth.notNow')}
            </Button>
          </div>
        </div>
      </div>
    </motion.div>
  );
};

// Main Chat Interface
export const ChatInterface = ({ user, onOpenDashboard, onOpenProfile }) => {
  const { t, language } = useLanguage();
  const [messages, setMessages] = useState([]);
  const [inputValue, setInputValue] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [showLoginPrompt, setShowLoginPrompt] = useState(false);
  const [todayLog, setTodayLog] = useState(null);
  const [targets, setTargets] = useState({ water_ml: 2000, glass_ml: 250 });
  const messagesEndRef = useRef(null);
  const inputRef = useRef(null);

  // Scroll to bottom
  const scrollToBottom = useCallback(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, []);

  useEffect(() => {
    scrollToBottom();
  }, [messages, scrollToBottom]);

  // Load initial data
  useEffect(() => {
    loadChatHistory();
    loadTodayLog();
  }, []);

  const loadChatHistory = async () => {
    try {
      const response = await fetch(`${API_URL}/api/chat/history`, {
        credentials: 'include'
      });
      const data = await response.json();
      
      if (data.messages && data.messages.length > 0) {
        setMessages(data.messages);
      } else {
        // Add welcome message
        const welcomeResponse = await fetch(`${API_URL}/api/chat/welcome?language=${language}`);
        const welcomeData = await welcomeResponse.json();
        setMessages([{
          id: 'welcome',
          role: 'assistant',
          message_text: welcomeData.message || t('chat.welcome'),
          created_at: new Date().toISOString()
        }]);
      }
    } catch (error) {
      console.error('Failed to load chat history:', error);
      setMessages([{
        id: 'welcome',
        role: 'assistant',
        message_text: t('chat.welcome'),
        created_at: new Date().toISOString()
      }]);
    }
  };

  const loadTodayLog = async () => {
    try {
      const response = await fetch(`${API_URL}/api/log/today`, {
        credentials: 'include'
      });
      const data = await response.json();
      setTodayLog(data.log);
      if (data.targets) {
        setTargets(data.targets);
      }
    } catch (error) {
      console.error('Failed to load today log:', error);
    }
  };

  const triggerConfetti = () => {
    confetti({
      particleCount: 100,
      spread: 70,
      origin: { y: 0.6 },
      colors: ['#F97316', '#0EA5E9', '#22C55E']
    });
  };

  const sendMessage = async () => {
    if (!inputValue.trim() || isLoading) return;

    const userMessage = {
      id: `temp-${Date.now()}`,
      role: 'user',
      message_text: inputValue.trim(),
      created_at: new Date().toISOString(),
      isNew: true
    };

    setMessages(prev => [...prev, userMessage]);
    setInputValue('');
    setIsLoading(true);

    try {
      const response = await fetch(`${API_URL}/api/chat/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          message: userMessage.message_text,
          language: language
        })
      });

      const data = await response.json();

      const assistantMessage = {
        id: `response-${Date.now()}`,
        role: 'assistant',
        message_text: data.response,
        message_type: data.message_type,
        created_at: new Date().toISOString(),
        isNew: true
      };

      setMessages(prev => [...prev, assistantMessage]);

      // Show login prompt if suggested
      if (data.prompt_login && !user) {
        setShowLoginPrompt(true);
      }
    } catch (error) {
      console.error('Failed to send message:', error);
      setMessages(prev => [...prev, {
        id: `error-${Date.now()}`,
        role: 'assistant',
        message_text: t('chat.errorResponse'),
        created_at: new Date().toISOString(),
        isNew: true
      }]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleQuickAction = async (actionType, value) => {
    setIsLoading(true);
    try {
      const response = await fetch(`${API_URL}/api/log/quick-action`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action_type: actionType, value })
      });

      const data = await response.json();

      if (data.success) {
        // Update local state
        setTodayLog(prev => ({
          ...prev,
          ...(actionType === 'water' && { water_ml: data.total_water_ml }),
          ...(actionType === 'steps' && { steps_level: data.steps_level }),
          ...(actionType === 'workout' && { workout_done: data.workout_done })
        }));

        // Check for goal completion
        if (actionType === 'water' && data.total_water_ml >= targets.water_ml) {
          triggerConfetti();
        }

        // Add a motivational message
        let motivationMessage = '';
        if (actionType === 'water') {
          const glasses = Math.floor(data.total_water_ml / 250);
          const targetGlasses = Math.floor(targets.water_ml / 250);
          if (data.total_water_ml >= targets.water_ml) {
            motivationMessage = t('motivation.goalReached');
          } else {
            motivationMessage = `💧 ${glasses}/${targetGlasses} ${t('dashboard.glasses')} - ${t('motivation.keepGoing')}`;
          }
        } else if (actionType === 'workout' && data.workout_done) {
          motivationMessage = `🏋️ ${t('motivation.keepGoing')}`;
          triggerConfetti();
        } else if (actionType === 'steps') {
          motivationMessage = `🚶 ${t('quickActions.steps')}: ${data.steps_level}`;
        }

        if (motivationMessage) {
          setMessages(prev => [...prev, {
            id: `quick-${Date.now()}`,
            role: 'assistant',
            message_text: motivationMessage,
            created_at: new Date().toISOString(),
            isNew: true
          }]);
        }
      }
    } catch (error) {
      console.error('Quick action failed:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyPress = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  return (
    <div className="flex flex-col h-full" data-testid="chat-interface">
      {/* Chat Messages Area */}
      <ScrollArea className="flex-1 p-4">
        <div className="space-y-2 pb-4">
          <AnimatePresence mode="popLayout">
            {messages.map((msg, index) => (
              <ChatBubble
                key={msg.id || index}
                message={msg}
                isUser={msg.role === 'user'}
                isNew={msg.isNew}
              />
            ))}
          </AnimatePresence>
          
          {isLoading && <TypingIndicator />}
          
          <div ref={messagesEndRef} />
        </div>
      </ScrollArea>

      {/* Login Prompt */}
      <AnimatePresence>
        {showLoginPrompt && !user && (
          <LoginPrompt 
            onLogin={() => {}} 
            onDismiss={() => setShowLoginPrompt(false)} 
          />
        )}
      </AnimatePresence>

      {/* Quick Actions */}
      <div className="px-4 pb-2">
        <QuickActionsPanel 
          onAction={handleQuickAction}
          todayLog={todayLog}
          isLoading={isLoading}
        />
      </div>

      {/* Input Area */}
      <div className="p-4 bg-background/80 backdrop-blur-lg border-t border-border">
        <div className="flex items-center gap-2">
          <input
            ref={inputRef}
            type="text"
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            onKeyPress={handleKeyPress}
            placeholder={t('chat.placeholder')}
            disabled={isLoading}
            className="flex-1 bg-muted border-0 focus:ring-2 focus:ring-primary/50 rounded-full px-5 py-3 text-sm"
            data-testid="chat-input"
          />
          <Button
            onClick={sendMessage}
            disabled={!inputValue.trim() || isLoading}
            className="rounded-full w-12 h-12 p-0 bg-primary hover:bg-primary/90"
            data-testid="send-button"
          >
            <Send className="w-5 h-5" />
          </Button>
        </div>
      </div>
    </div>
  );
};

export default ChatInterface;
