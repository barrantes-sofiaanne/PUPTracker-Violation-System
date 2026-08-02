import pytest
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.support.ui import WebDriverWait
from webdriver_manager.chrome import ChromeDriverManager

BASE_URL = "http://127.0.0.1:8000"

@pytest.fixture
def driver():
    """Initializes a fresh Chrome browser session."""
    options = webdriver.ChromeOptions()
    options.add_argument("--window-size=1920,1080")
    
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)
    yield driver
    driver.quit()

# --------------------------------------------------------------------------
# Security Test Case: Unauthenticated Route Protection
# --------------------------------------------------------------------------

def test_guest_cannot_access_protected_dashboards(driver):
    # Mapping of protected routes to their expected login redirect targets
    protected_routes = [
        f"{BASE_URL}/admin/dashboard",
        f"{BASE_URL}/security/dashboard",
        f"{BASE_URL}/student/dashboard",
        f"{BASE_URL}/admin/violations",
    ]

    for route in protected_routes:
        driver.get(route)

        # Wait until the URL changes away from the protected path
        WebDriverWait(driver, 5).until(lambda d: d.current_url != route)

        print(f"\n[PASS] Attempted: {route}")
        print(f"       Redirected to: {driver.current_url}")

        # Assertions
        assert route not in driver.current_url, f"SECURITY BREACH: Guest accessed {route}"
        assert "login" in driver.current_url, f"Guest was redirected, but not to a login page. Landed on: {driver.current_url}"