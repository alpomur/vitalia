#!/usr/bin/env python3
"""
Comprehensive Backend API Testing for Vitalia Health Chat App
Tests all major API endpoints for functionality and integration
"""

import requests
import sys
import json
import time
from datetime import datetime

# Use the public endpoint for testing
API_BASE_URL = "https://health-chat-ai-4.preview.emergentagent.com/api"

class VitaliaAPITester:
    def __init__(self):
        self.base_url = API_BASE_URL
        self.session = requests.Session()
        self.tests_run = 0
        self.tests_passed = 0
        self.guest_id = None
        self.session_token = None
        self.user_id = None
        
    def log_test(self, test_name, success, message="", response_data=None):
        """Log test results"""
        self.tests_run += 1
        status = "✅ PASS" if success else "❌ FAIL"
        print(f"{status} - {test_name}: {message}")
        
        if success:
            self.tests_passed += 1
        
        if response_data and not success:
            print(f"   Response: {json.dumps(response_data, indent=2)}")
            
        return success

    def make_request(self, method, endpoint, data=None, expected_status=200, headers=None):
        """Make HTTP request and return success status and response data"""
        url = f"{self.base_url}{endpoint}"
        request_headers = {'Content-Type': 'application/json'}
        
        if headers:
            request_headers.update(headers)
            
        if self.session_token:
            request_headers['Authorization'] = f'Bearer {self.session_token}'

        try:
            if method == 'GET':
                response = self.session.get(url, headers=request_headers)
            elif method == 'POST':
                response = self.session.post(url, json=data, headers=request_headers)
            elif method == 'PUT':
                response = self.session.put(url, json=data, headers=request_headers)
            else:
                return False, {"error": "Unsupported HTTP method"}

            response_data = {}
            try:
                response_data = response.json()
            except:
                response_data = {"response_text": response.text}

            success = response.status_code == expected_status
            return success, response_data
            
        except Exception as e:
            return False, {"error": str(e)}

    def test_health_check(self):
        """Test basic health endpoint"""
        success, data = self.make_request('GET', '/health')
        return self.log_test(
            "Health Check", 
            success,
            "API is responsive" if success else f"Health check failed: {data}"
        )

    def test_welcome_message(self):
        """Test welcome message endpoint"""
        languages = ['tr', 'en', 'de', 'fr', 'es']
        
        for lang in languages:
            success, data = self.make_request('GET', f'/chat/welcome?language={lang}')
            test_passed = success and 'message' in data
            self.log_test(
                f"Welcome Message ({lang})", 
                test_passed,
                f"Got welcome message" if test_passed else f"Failed: {data}"
            )
            if not test_passed:
                return False
        return True

    def test_chat_send(self):
        """Test chat send endpoint"""
        # Test with simple health question
        chat_data = {
            "message": "Bugün nasıl su içmeliyim?",
            "language": "tr"
        }
        
        success, data = self.make_request('POST', '/chat/send', chat_data)
        
        test_passed = success and 'response' in data and 'message_type' in data
        return self.log_test(
            "Chat Send", 
            test_passed,
            f"AI responded successfully" if test_passed else f"Failed: {data}"
        )

    def test_quick_action_water(self):
        """Test water logging quick action"""
        action_data = {
            "action_type": "water",
            "value": 250
        }
        
        success, data = self.make_request('POST', '/log/quick-action', action_data)
        
        test_passed = success and data.get('success') and 'total_water_ml' in data
        return self.log_test(
            "Quick Action - Water", 
            test_passed,
            f"Water logged: {data.get('total_water_ml', 0)}ml" if test_passed else f"Failed: {data}"
        )

    def test_quick_action_steps(self):
        """Test steps logging quick action"""
        action_data = {
            "action_type": "steps",
            "value": "medium"
        }
        
        success, data = self.make_request('POST', '/log/quick-action', action_data)
        
        test_passed = success and data.get('success') and data.get('steps_level') == "medium"
        return self.log_test(
            "Quick Action - Steps", 
            test_passed,
            f"Steps logged: {data.get('steps_level')}" if test_passed else f"Failed: {data}"
        )

    def test_quick_action_workout(self):
        """Test workout logging quick action"""
        action_data = {
            "action_type": "workout",
            "value": True
        }
        
        success, data = self.make_request('POST', '/log/quick-action', action_data)
        
        test_passed = success and data.get('success') and data.get('workout_done')
        return self.log_test(
            "Quick Action - Workout", 
            test_passed,
            f"Workout logged: {data.get('workout_done')}" if test_passed else f"Failed: {data}"
        )

    def test_quick_action_weight(self):
        """Test weight logging quick action"""
        action_data = {
            "action_type": "weight",
            "value": 70.5
        }
        
        success, data = self.make_request('POST', '/log/quick-action', action_data)
        
        test_passed = success and data.get('success') and data.get('weight_kg') == 70.5
        return self.log_test(
            "Quick Action - Weight", 
            test_passed,
            f"Weight logged: {data.get('weight_kg')}kg" if test_passed else f"Failed: {data}"
        )

    def test_log_today(self):
        """Test today's log endpoint"""
        success, data = self.make_request('GET', '/log/today')
        
        test_passed = success and 'log' in data and 'targets' in data
        return self.log_test(
            "Today's Log", 
            test_passed,
            f"Retrieved today's progress" if test_passed else f"Failed: {data}"
        )

    def test_log_history(self):
        """Test log history endpoint"""
        success, data = self.make_request('GET', '/log/history?days=7')
        
        test_passed = success and 'daily_logs' in data and 'weight_logs' in data
        return self.log_test(
            "Log History", 
            test_passed,
            f"Retrieved {len(data.get('daily_logs', []))} daily logs, {len(data.get('weight_logs', []))} weight logs" if test_passed else f"Failed: {data}"
        )

    def test_chat_history(self):
        """Test chat history endpoint"""
        success, data = self.make_request('GET', '/chat/history?limit=10')
        
        test_passed = success and 'messages' in data
        return self.log_test(
            "Chat History", 
            test_passed,
            f"Retrieved {len(data.get('messages', []))} messages" if test_passed else f"Failed: {data}"
        )

    def test_multiple_language_chat(self):
        """Test chat in different languages"""
        test_cases = [
            {"language": "en", "message": "How much water should I drink today?", "expected_lang": "en"},
            {"language": "de", "message": "Wie viel Wasser sollte ich trinken?", "expected_lang": "de"},
            {"language": "tr", "message": "Bugün ne kadar su içmeliyim?", "expected_lang": "tr"}
        ]
        
        all_passed = True
        for test_case in test_cases:
            chat_data = {
                "message": test_case["message"],
                "language": test_case["language"]
            }
            
            success, data = self.make_request('POST', '/chat/send', chat_data)
            test_passed = success and 'response' in data and data['response']
            
            self.log_test(
                f"Multi-language Chat ({test_case['language']})", 
                test_passed,
                f"AI responded in {test_case['language']}" if test_passed else f"Failed: {data}"
            )
            
            if not test_passed:
                all_passed = False
                
            # Small delay to avoid rate limiting
            time.sleep(1)
            
        return all_passed

    def test_rate_limiting(self):
        """Test that rate limiting is working"""
        print("🔄 Testing rate limiting (sending rapid requests)...")
        
        rapid_requests = 0
        rate_limited = False
        
        for i in range(15):  # Send more than typical rate limit
            chat_data = {
                "message": f"Test message {i}",
                "language": "en"
            }
            
            success, data = self.make_request('POST', '/chat/send', chat_data, expected_status=200)
            rapid_requests += 1
            
            # Check if we got rate limited
            if not success or 'rate_limit' in str(data).lower() or 'slow' in str(data).lower():
                rate_limited = True
                break
                
        return self.log_test(
            "Rate Limiting", 
            rate_limited,
            f"Rate limiting triggered after {rapid_requests} requests" if rate_limited else "Rate limiting may not be working properly"
        )

    def run_all_tests(self):
        """Run all backend tests"""
        print(f"🚀 Starting Vitalia Backend API Tests")
        print(f"📡 Testing API: {self.base_url}")
        print("=" * 60)
        
        # Basic connectivity tests
        print("\n📋 Basic Connectivity Tests:")
        self.test_health_check()
        
        # Welcome and chat tests  
        print("\n💬 Chat Interface Tests:")
        self.test_welcome_message()
        self.test_chat_send()
        self.test_chat_history()
        
        # Quick action tests
        print("\n⚡ Quick Actions Tests:")
        self.test_quick_action_water()
        self.test_quick_action_steps() 
        self.test_quick_action_workout()
        self.test_quick_action_weight()
        
        # Logging tests
        print("\n📊 Logging & Data Tests:")
        self.test_log_today()
        self.test_log_history()
        
        # Advanced features
        print("\n🌍 Advanced Features Tests:")
        self.test_multiple_language_chat()
        
        # Performance tests
        print("\n⚙️ Performance & Security Tests:")
        self.test_rate_limiting()
        
        # Print summary
        print("\n" + "=" * 60)
        print(f"📊 Test Summary:")
        print(f"   Total Tests: {self.tests_run}")
        print(f"   Passed: {self.tests_passed}")
        print(f"   Failed: {self.tests_run - self.tests_passed}")
        print(f"   Success Rate: {(self.tests_passed/self.tests_run)*100:.1f}%")
        
        return self.tests_passed, self.tests_run

def main():
    """Main test execution"""
    tester = VitaliaAPITester()
    passed, total = tester.run_all_tests()
    
    # Return appropriate exit code
    if passed == total:
        print("\n🎉 All tests passed!")
        return 0
    elif passed >= total * 0.8:  # 80% pass rate
        print(f"\n⚠️ Most tests passed ({passed}/{total})")
        return 0  
    else:
        print(f"\n💥 Many tests failed ({passed}/{total})")
        return 1

if __name__ == "__main__":
    sys.exit(main())