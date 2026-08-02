import pytest
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager

BASE_URL = "http://127.0.0.1:8000"

@pytest.fixture
def driver():
    """Starts a fresh browser for each login test."""
    options = webdriver.ChromeOptions()
    options.add_argument("--window-size=1920,1080")
    
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)
    yield driver
    driver.quit()

# --------------------------------------------------------------------------
# Auth Test Case 1: Invalid Credentials Should Show Error
# --------------------------------------------------------------------------
def test_admin_login_failure(driver):
    driver.get(f"{BASE_URL}/admin/login")

    # 1. Fill in wrong credentials
    driver.find_element(By.NAME, "email").clear()
    driver.find_element(By.NAME, "email").send_keys("wrong_admin@puptracker.com")

    driver.find_element(By.NAME, "password").clear()
    driver.find_element(By.NAME, "password").send_keys("WrongPassword123")

    # 2. Submit the form
    driver.find_element(By.XPATH, "//button[@type='submit']").click()

    # 3. Assert we are STILL on the login page
    WebDriverWait(driver, 5).until(EC.url_contains("admin/login"))
    assert "admin/login" in driver.current_url

    print("\n[PASS] Invalid login attempt rejected successfully.")


# --------------------------------------------------------------------------
# Auth Test Case 2: Valid Credentials Should Access Dashboard
# --------------------------------------------------------------------------
def test_admin_login_success(driver):
    driver.get(f"{BASE_URL}/admin/login")

    # Update these values with an actual admin user in your database/seeder!
    VALID_EMAIL = "sabarrantes2911@gmail.com" 
    VALID_PASSWORD = "YourStrongPassword123!"

    # 1. Fill in valid credentials
    driver.find_element(By.NAME, "email").clear()
    driver.find_element(By.NAME, "email").send_keys(VALID_EMAIL)

    driver.find_element(By.NAME, "password").clear()
    driver.find_element(By.NAME, "password").send_keys(VALID_PASSWORD)

    # 2. Submit the form
    driver.find_element(By.XPATH, "//button[@type='submit']").click()

    # 3. Wait to see if we get redirected to the Admin Dashboard (or MFA if enabled)
    WebDriverWait(driver, 10).until(lambda d: "admin/login" not in d.current_url)

    print(f"\n[PASS] Login successful! Current page: {driver.current_url}")
    assert "admin/dashboard" in driver.current_url or "mfa" in driver.current_url