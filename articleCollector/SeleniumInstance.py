from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
import os

# class for handling web scraping via Selenium
# Selenium is needed for sites with complex login systems or designs (such as the use of Javascript) that make using standard download methods difficult or impossible
class SeleniumInstance:
    def __init__(self):
        self.driver = None
        self.attemptedDriver = False
        # dictionary of login status for sites using Selenium to scrape - key is site name, data is a tuple where first element is whether you are logged in, second is whether login has been attempted
        self.loginStates = {"wsj":(False,False)} 

    # intialize Chrome webdriver for selenium use on certain sources (returns driver is successful)
    def initializeDriver(self):
        try:
            options = Options()
            options.headless = True
            opts = Options()
            opts.add_argument("user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36")
            self.driver = webdriver.Chrome(options=options, executable_path='/usr/bin/chromedriver')
            self.driver.set_window_size(1200, 600) # even though we're headless this helps us avoid issues for some reason
        except Exception as e:
            print("Selenium Driver Error:",e)
            self.driver = None
        self.attemptedDriver = True

    def isLoggedIn(self,source): # checks whether you're logged into a site
        return self.loginStates[source][0] == True
    
    def hasAttemptedLogin(self,source): # checks whether you've attempted login (necessary for avoiding login retries after failure)
        return self.loginStates[source][1] == True

    # log into a specific source
    def login(self,source):
        getattr(self,source+"_login")() # all login functions need to follow this format
        return self.isLoggedIn(source)

    def wsj_login(self):
        if self.driver:
            try:
                login_url = "https://accounts.wsj.com/login"
                self.driver.get(login_url)
                wait = WebDriverWait(self.driver,10)
                element_present = wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR,"button.basic-login-submit"))) # wait until login button is visible to begin typing credentials
                self.driver.find_element_by_name("username").send_keys(os.environ['WSJ_EMAIL']) # send credentials
                self.driver.find_element_by_name("password").send_keys(os.environ['WSJ_PASSWORD'])
                self.driver.find_element_by_css_selector("button.basic-login-submit").click() # login
                element_present = wait.until(EC.presence_of_element_located((By.CSS_SELECTOR,"a.style--fullname--3RYDOD92 "))) # wait until username appears on WSJ homepage after login to confirm login status
                self.loginStates['wsj'] = (True,True)
            except Exception as e:
                print("WSJ Login Error: ",e)
                self.loginStates['wsj'] = (False,True)