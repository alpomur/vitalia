import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, useLocation, useNavigate } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { Menu, User, BarChart3, Sun, Moon } from 'lucide-react';
import { Button } from './components/ui/button';
import { Toaster } from './components/ui/sonner';
import { LanguageProvider, useLanguage } from './i18n';
import { ChatInterface } from './components/ChatInterface';
import { Dashboard } from './components/Dashboard';
import { ProfilePage } from './components/ProfilePage';
import { LanguageSelector } from './components/LanguageSelector';
import { AuthCallback } from './components/AuthCallback';
import { AdminPanel } from './components/AdminPanel';
import './App.css';

const API_URL = process.env.REACT_APP_BACKEND_URL;

// Theme Toggle Component
const ThemeToggle = () => {
  const [isDark, setIsDark] = useState(() => {
    if (typeof window !== 'undefined') {
      return localStorage.getItem('vitalia_theme') === 'dark' ||
        (!localStorage.getItem('vitalia_theme') && 
         window.matchMedia('(prefers-color-scheme: dark)').matches);
    }
    return false;
  });

  useEffect(() => {
    if (isDark) {
      document.documentElement.classList.add('dark');
      localStorage.setItem('vitalia_theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem('vitalia_theme', 'light');
    }
  }, [isDark]);

  return (
    <Button
      variant="ghost"
      size="icon"
      onClick={() => setIsDark(!isDark)}
      className="rounded-full"
      data-testid="theme-toggle"
    >
      {isDark ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
    </Button>
  );
};

// Header Component
const Header = ({ user, onOpenDashboard, onOpenProfile }) => {
  const { t } = useLanguage();
  
  return (
    <header className="flex items-center justify-between p-4 border-b border-border bg-background/80 backdrop-blur-lg sticky top-0 z-30">
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center">
          <span className="text-white font-bold text-lg">V</span>
        </div>
        <div>
          <h1 className="font-bold font-heading text-lg">Vitalia</h1>
          <p className="text-xs text-muted-foreground">
            {user?.name ? `${t('auth.welcomeBack')}, ${user.name.split(' ')[0]}` : 'Health Advisor'}
          </p>
        </div>
      </div>
      
      <div className="flex items-center gap-1">
        <LanguageSelector />
        <ThemeToggle />
        <Button 
          variant="ghost" 
          size="icon" 
          onClick={onOpenDashboard}
          className="rounded-full"
          data-testid="open-dashboard-btn"
        >
          <BarChart3 className="w-5 h-5" />
        </Button>
        <Button 
          variant="ghost" 
          size="icon" 
          onClick={onOpenProfile}
          className="rounded-full"
          data-testid="open-profile-btn"
        >
          {user?.picture ? (
            <img src={user.picture} alt="" className="w-8 h-8 rounded-full" />
          ) : (
            <User className="w-5 h-5" />
          )}
        </Button>
      </div>
    </header>
  );
};

// Main App Layout
const MainApp = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const [user, setUser] = useState(location.state?.user || null);
  const [showDashboard, setShowDashboard] = useState(false);
  const [showProfile, setShowProfile] = useState(false);
  const [showAdmin, setShowAdmin] = useState(false);
  const [isCheckingAuth, setIsCheckingAuth] = useState(!location.state?.user);

  // Check auth on mount (if user not passed from AuthCallback)
  useEffect(() => {
    if (!location.state?.user) {
      checkAuth();
    }
  }, []);

  const checkAuth = async () => {
    try {
      const response = await fetch(`${API_URL}/api/auth/me`, {
        credentials: 'include'
      });
      if (response.ok) {
        const userData = await response.json();
        setUser(userData);
      }
    } catch (error) {
      console.log('Not authenticated');
    } finally {
      setIsCheckingAuth(false);
    }
  };

  const handleLogout = () => {
    setUser(null);
    setShowProfile(false);
    navigate('/', { replace: true });
  };

  if (isCheckingAuth) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="animate-spin w-10 h-10 border-4 border-primary border-t-transparent rounded-full"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background">
      <div className="max-w-md mx-auto h-screen flex flex-col bg-background shadow-2xl overflow-hidden relative border-x border-border">
        <AnimatePresence mode="wait">
          {showProfile ? (
            <ProfilePage 
              key="profile"
              user={user}
              onBack={() => setShowProfile(false)}
              onLogout={handleLogout}
            />
          ) : (
            <motion.div 
              key="main"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="flex flex-col h-full"
            >
              <Header 
                user={user}
                onOpenDashboard={() => setShowDashboard(true)}
                onOpenProfile={() => setShowProfile(true)}
              />
              <div className="flex-1 overflow-hidden">
                <ChatInterface 
                  user={user}
                  onOpenDashboard={() => setShowDashboard(true)}
                  onOpenProfile={() => setShowProfile(true)}
                />
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Dashboard Drawer */}
        <AnimatePresence>
          {showDashboard && (
            <>
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 0.5 }}
                exit={{ opacity: 0 }}
                className="fixed inset-0 bg-black z-40"
                onClick={() => setShowDashboard(false)}
              />
              <Dashboard 
                isOpen={showDashboard}
                onClose={() => setShowDashboard(false)}
              />
            </>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
};

// App Router with auth callback handling
function AppRouter() {
  const location = useLocation();
  
  // Check URL fragment for session_id (SYNCHRONOUS - prevents race conditions)
  // REMINDER: DO NOT HARDCODE THE URL, OR ADD ANY FALLBACKS OR REDIRECT URLS, THIS BREAKS THE AUTH
  if (location.hash?.includes('session_id=')) {
    return <AuthCallback />;
  }

  return (
    <Routes>
      <Route path="/auth/callback" element={<AuthCallback />} />
      <Route path="/*" element={<MainApp />} />
    </Routes>
  );
}

// Root App Component
function App() {
  return (
    <LanguageProvider>
      <BrowserRouter>
        <AppRouter />
        <Toaster position="top-center" />
      </BrowserRouter>
    </LanguageProvider>
  );
}

export default App;
