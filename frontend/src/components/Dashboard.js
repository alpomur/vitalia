import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { X, Droplets, Footprints, Dumbbell, Scale, TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Progress } from '../components/ui/progress';
import { useLanguage } from '../i18n';

const API_URL = process.env.REACT_APP_BACKEND_URL;

// Water Progress Component
const WaterProgress = ({ current, target, glassSize }) => {
  const { t } = useLanguage();
  const percentage = Math.min((current / target) * 100, 100);
  const glasses = Math.floor(current / glassSize);
  const targetGlasses = Math.floor(target / glassSize);

  return (
    <div className="bg-card border border-border rounded-xl p-4 card-hover" data-testid="water-progress">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Droplets className="w-5 h-5 text-water" />
          <span className="font-medium">{t('dashboard.waterProgress')}</span>
        </div>
        <span className="text-2xl font-bold font-heading text-water">{glasses}/{targetGlasses}</span>
      </div>
      
      <div className="relative h-4 bg-muted rounded-full overflow-hidden">
        <motion.div
          initial={{ width: 0 }}
          animate={{ width: `${percentage}%` }}
          transition={{ duration: 0.5, ease: "easeOut" }}
          className="absolute inset-y-0 left-0 bg-water rounded-full"
        />
      </div>
      
      <div className="flex justify-between mt-2 text-xs text-muted-foreground">
        <span>{current}ml</span>
        <span>{target}ml</span>
      </div>
    </div>
  );
};

// Activity Card Component
const ActivityCard = ({ icon: Icon, title, value, color, subtitle }) => {
  return (
    <div className="bg-card border border-border rounded-xl p-4 card-hover">
      <div className="flex items-center gap-3">
        <div className={`w-10 h-10 rounded-full flex items-center justify-center ${color}`}>
          <Icon className="w-5 h-5" />
        </div>
        <div>
          <p className="text-sm text-muted-foreground">{title}</p>
          <p className="text-lg font-bold font-heading">{value}</p>
          {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
        </div>
      </div>
    </div>
  );
};

// Weight Trend Component
const WeightTrend = ({ weightLogs }) => {
  const { t } = useLanguage();
  
  if (!weightLogs || weightLogs.length < 2) {
    return (
      <div className="bg-card border border-border rounded-xl p-4">
        <div className="flex items-center gap-2 mb-2">
          <Scale className="w-5 h-5 text-secondary" />
          <span className="font-medium">{t('dashboard.weightTrend')}</span>
        </div>
        <p className="text-sm text-muted-foreground">-</p>
      </div>
    );
  }

  const latest = weightLogs[0]?.weight_kg;
  const previous = weightLogs[1]?.weight_kg;
  const diff = latest - previous;
  
  let TrendIcon = Minus;
  let trendColor = 'text-muted-foreground';
  
  if (diff < -0.1) {
    TrendIcon = TrendingDown;
    trendColor = 'text-success';
  } else if (diff > 0.1) {
    TrendIcon = TrendingUp;
    trendColor = 'text-warning';
  }

  return (
    <div className="bg-card border border-border rounded-xl p-4 card-hover" data-testid="weight-trend">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Scale className="w-5 h-5 text-secondary" />
          <span className="font-medium">{t('dashboard.weightTrend')}</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-2xl font-bold font-heading">{latest?.toFixed(1)}</span>
          <span className="text-sm text-muted-foreground">kg</span>
          <TrendIcon className={`w-5 h-5 ${trendColor}`} />
        </div>
      </div>
      {diff !== 0 && (
        <p className={`text-sm mt-2 ${trendColor}`}>
          {diff > 0 ? '+' : ''}{diff.toFixed(1)} kg
        </p>
      )}
    </div>
  );
};

// Weekly Progress Chart (Simple)
const WeeklyProgress = ({ dailyLogs }) => {
  const { t } = useLanguage();
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  
  // Create a map of date to log
  const logMap = {};
  dailyLogs?.forEach(log => {
    logMap[log.log_date] = log;
  });

  // Get last 7 days
  const last7Days = [];
  for (let i = 6; i >= 0; i--) {
    const date = new Date();
    date.setDate(date.getDate() - i);
    last7Days.push(date.toISOString().split('T')[0]);
  }

  return (
    <div className="bg-card border border-border rounded-xl p-4" data-testid="weekly-progress">
      <h3 className="font-medium mb-4">{t('dashboard.weeklyProgress')}</h3>
      <div className="flex justify-between gap-1">
        {last7Days.map((date, index) => {
          const log = logMap[date];
          const hasWater = log?.water_ml > 0;
          const hasSteps = !!log?.steps_level;
          const hasWorkout = log?.workout_done;
          
          return (
            <div key={date} className="flex flex-col items-center gap-1">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs ${
                hasWater || hasSteps || hasWorkout 
                  ? 'bg-primary/20 text-primary' 
                  : 'bg-muted text-muted-foreground'
              }`}>
                {hasWorkout ? '💪' : hasWater ? '💧' : hasSteps ? '🚶' : '-'}
              </div>
              <span className="text-[10px] text-muted-foreground">
                {days[new Date(date).getDay() === 0 ? 6 : new Date(date).getDay() - 1]}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
};

// Main Dashboard Component
export const Dashboard = ({ isOpen, onClose }) => {
  const { t } = useLanguage();
  const [todayLog, setTodayLog] = useState(null);
  const [targets, setTargets] = useState({ water_ml: 2000, glass_ml: 250 });
  const [history, setHistory] = useState({ daily_logs: [], weight_logs: [] });
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (isOpen) {
      loadData();
    }
  }, [isOpen]);

  const loadData = async () => {
    setIsLoading(true);
    try {
      // Load today's log
      const todayResponse = await fetch(`${API_URL}/api/log/today`, {
        credentials: 'include'
      });
      const todayData = await todayResponse.json();
      setTodayLog(todayData.log);
      if (todayData.targets) {
        setTargets(todayData.targets);
      }

      // Load history
      const historyResponse = await fetch(`${API_URL}/api/log/history?days=7`, {
        credentials: 'include'
      });
      const historyData = await historyResponse.json();
      setHistory(historyData);
    } catch (error) {
      console.error('Failed to load dashboard data:', error);
    } finally {
      setIsLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <motion.div
      initial={{ x: '100%' }}
      animate={{ x: 0 }}
      exit={{ x: '100%' }}
      transition={{ type: 'spring', damping: 25, stiffness: 300 }}
      className="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-background border-l border-border shadow-2xl"
      data-testid="dashboard-drawer"
    >
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b border-border">
        <h2 className="text-xl font-bold font-heading">{t('dashboard.title')}</h2>
        <Button variant="ghost" size="icon" onClick={onClose} data-testid="close-dashboard">
          <X className="w-5 h-5" />
        </Button>
      </div>

      {/* Content */}
      <div className="p-4 space-y-4 overflow-y-auto h-[calc(100%-60px)]">
        {isLoading ? (
          <div className="flex items-center justify-center h-32">
            <div className="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full"></div>
          </div>
        ) : (
          <>
            {/* Water Progress */}
            <WaterProgress
              current={todayLog?.water_ml || 0}
              target={targets.water_ml}
              glassSize={targets.glass_ml}
            />

            {/* Activity Cards */}
            <div className="grid grid-cols-2 gap-3">
              <ActivityCard
                icon={Footprints}
                title={t('dashboard.stepsLevel')}
                value={todayLog?.steps_level || '-'}
                color="bg-success/10 text-success"
              />
              <ActivityCard
                icon={Dumbbell}
                title={t('dashboard.workoutStatus')}
                value={todayLog?.workout_done ? t('dashboard.completed') : t('dashboard.notCompleted')}
                color={todayLog?.workout_done ? "bg-primary/10 text-primary" : "bg-muted text-muted-foreground"}
              />
            </div>

            {/* Weight Trend */}
            <WeightTrend weightLogs={history.weight_logs} />

            {/* Weekly Progress */}
            <WeeklyProgress dailyLogs={history.daily_logs} />
          </>
        )}
      </div>
    </motion.div>
  );
};

export default Dashboard;
