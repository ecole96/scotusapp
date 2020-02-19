# this file contains functions universal to the project, used in every module
import datetime
import tldextract
import re
import html
import requests
import math
import os
import smtplib
from bs4 import BeautifulSoup
from urllib import parse as urlparse
from sklearn.svm import LinearSVC
from sklearn.calibration import CalibratedClassifierCV
from sklearn.feature_extraction.text import TfidfVectorizer
from nltk.corpus import stopwords
from scipy.sparse import hstack
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By

# download a webpage using BeautifulSoup
# returns soup object we can parse
def downloadPage(url):
    #user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/602.2.14 (KHTML, like Gecko) Version/10.0.1 Safari/602.2.14'
    user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36'
    try:
        request = requests.get(url,headers={'User-Agent':user_agent})
        page = request.content
        soup = BeautifulSoup(page, 'html.parser')
    except Exception as e: # couldn't download page
        print("DOWNLOAD ERROR: ",e)
        soup = None
    return soup

# alternate webpage download function for sites that need Selenium for scraping
def alt_downloadPage(driver,url,waitElement):
    try:
        driver.get(url)
        if waitElement: #waitElement is an element on a seleniumSource webpage that we use to confirm a site loaded successfully
            wait = WebDriverWait(driver,10)
            element_present = wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR,waitElement)))
        page = driver.page_source
        soup = BeautifulSoup(page, 'lxml')
    except Exception as e:
        print("DOWNLOAD ERROR (Selenium):",e)
        soup = None
    return soup

# determines whether to use Selenium or the standard requests library for downloading article webpages, and handles webdriver initializations as they are needed
# returns a 'driver' - if we need Selenium, a Chrome webdriver is returned. Otherwise, return None (and just use our normal download method)
def decideScraperMethod(source,si):
    seleniumSources = ["wsj"]
    driver = None
    if source in seleniumSources: # an article we want is from a page we need Selenium to scrape
        if not si.attemptedDriver: # Chrome webdriver hasn't been opened yet, so do that
            print("Initializing Selenium...")
            si.initializeDriver()
        if si.driver and not si.isLoggedIn(source) and not si.hasAttemptedLogin(source): # if the webdriver opened successfully and we need to login, do that
            print("Logging into ",source+"...")
            si.login(source)
        if si.driver and si.isLoggedIn(source): # if we already have a webdriver and we're already logged into our article source, then use the webdriver
            driver = si.driver
    return driver

# Links in the RSS feed are generated by google, and the actual URL of the article is stored as the 'url' parameter in this link
# this function gets us the actual URL
def getURL(RSS_url):
    parsed = urlparse.urlparse(RSS_url)
    url = urlparse.parse_qs(parsed.query)['url'][0]
    return url

# original date is a long date/time string, raw_format is the format that the date is initially entered as
# for our purposes, we really only need date, not time - so this function extracts the date and converts it into a yyyy-mm-dd format (how MySQL stores dates)
def convertDate(orig_date,raw_format):
    convertedDate = datetime.datetime.strptime(orig_date,raw_format).strftime('%Y-%m-%d %H:%M:%S')
    return convertedDate

# converts a 12 hour time to 24 hour time for storage in the database
def convertTime(timestr,raw_format):
    std = datetime.datetime.strptime(timestr, raw_format)
    return datetime.datetime.strftime(std, "%H:%M:00")

# parses URL to get the domain name of each article's link - the source
# one defect in handling the source is that, as of now, we don't know how to handle multiple-word sources beyond just storing it all as one string (so Fox News would just be stored as foxnews)
def getSource(url):
    ext = tldextract.extract(url)
    source = ext.domain
    return source

# 'Supreme Court' appears in the titles of the RSS feed with bold tags around them
# this function strips the HTML and returns a text-only version of the title
def cleanTitle(original_title):
    cleanr = re.compile('<.*?>')
    cleanTitle = html.unescape(re.sub(cleanr, '', original_title))
    return cleanTitle

# for use with generic scraper - takes out known 'fluff' (like advertisements and prompts to read more), attempts to strip text to the essentials
def cleanText(text):
    cleanedText = ''
    for line in text.split('\n'):
        line = line.strip()
        if line.lower() not in ["ad","advertisement","story continued below",'']:
            cleanedText += (line + '\n\n')
    return cleanedText.strip()

# print preliminary article information
def printBasicInfo(title,url):
    print('Title:',title)
    print('URL:', url)

# checks whether an article is from a known "bad" source - usually aggregate sites, paywalled sites, or obscure sites that don't scrape well and aren't worth writing a scraper for
def isBlockedSource(url):
    blockedSources = ['law360','law','freerepublic','bloomberglaw','nakedcapitalism','independent','mentalfloss','columbustelegram'] 
    if "howappealing.abovethelaw.com" in url or getSource(url) in blockedSources:
        print("Rejected - URL/source known to have a paywall, or does not contain full articles")
        return True
    else:
        return False

# we're getting duplicate articles due to tiny differences in title text (different types of quotation marks, unicode spaces), so we're normalizing them for comparison
def processTitle(title):
    title = title
    chars = {"’":"'","‘":"'","\xa0":" "}
    for c in chars:
        title = title.replace(c,chars[c])
    return title

# checks whether the title of an article is already in the database, avoiding duplicates
# we only check for title and url because the likeliness of identical titles is crazy low, and it cuts down on reposts from other sites
def articleIsDuplicate(title,url,c):
    c.execute("""SELECT idArticle FROM article WHERE title = %s OR url = %s""",(title,url,)) # funky single quotes can sometimes lead to duplicates
    if c.rowcount == 0:
        return False
    else:
        print("Rejected - article already exists in the database")
        return True

# check whether an irrelevant article is already in the training data
def rejectedIsDuplicate(title,url,c):
    c.execute("""SELECT id FROM rejectedTrainingData WHERE title = %s OR url = %s""",(title,url,))
    if c.rowcount == 0:
        return False
    else:
        print("Rejected article is already in training data")
        return True

# determines if a new billing cycle for the Google Cloud API has been reached
def isNewBillingCycle(c):
    now = datetime.datetime.now().date()
    newBillingDate = c.execute("""SELECT newBillingDate FROM analysisCap""")
    row = c.fetchone()
    newBillingDate = datetime.datetime.strptime(row['newBillingDate'].strftime("%Y-%m-%d"),"%Y-%m-%d").date()
    if now >= newBillingDate: # check if billing date has been passed
        return True
    else:
        return False

# resets analysisCap table in database for new month of API requests
def resetRequests(c):
    now = datetime.date.today()
    newBillingDate = (now + datetime.timedelta(days=32)).replace(day=1) # reset to the first of next month
    c.execute("UPDATE analysisCap SET newBillingDate=(%s),currentSentimentRequests=0,currentImageRequests=0",(newBillingDate,))

# in the small chance an article doesn't have a title (it does happen, rarely), title it Untitled [date] [time].
def untitledArticle():
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    return "Untitled " + now

# The Google Alerts RSS feeds sometimes truncate long titles with a "..." - this function gets the full title by comparing the feed title against the scraped title
# worst-case scenario, just use the original title
def replaceTitle(originalTitle, scrapedTitle):
    split_title = originalTitle.split()
    title_no_ellipsis = ' '.join(split_title[:-1])
    if title_no_ellipsis.lower() in scrapedTitle.lower():
        print("Truncated title changed to -",scrapedTitle)
        return scrapedTitle
    else:
        return originalTitle

# create training dataset for relevancy check using a LinearSVC model
# return vectorizer and clf (classifier) because we need them to predict relevancy for individual articles later on
# our model will consist of two separate tf-idf matrices (one for article text, another for titles) combined into one
def train_relevancy(c):
    print("Training relevancy check dataset...")
    Xraw, Y = get_training_data(c,True)
    v_text = TfidfVectorizer(stop_words=stopwords.words("english"),min_df=5)
    v_title = TfidfVectorizer(stop_words=stopwords.words("english"),ngram_range=(1,3))
    X = convertTextData(Xraw,v_text,v_title,'train')
    clf = CalibratedClassifierCV(LinearSVC(class_weight='balanced'),method='sigmoid',cv=5).fit(X,Y) #LinearSVC() doesn't have probability functionality by default so wrapping it into CalibratedClassiferCV()
    return clf, v_text, v_title

# training data consists of input (x) and output (y)
# x = 2d array of [article title, article_text]
# y = classification labels ('R' = relevant, 'U' = unrelated topics, 'F' = foreign courts, 'S' = state/local courts)
# if True, binary parameter splits labels only into 'R' and 'U' ('F' and 'S' folded into the latter)
def get_training_data(c,binary):
    x = []
    y = []
    # get relevant training data - label R for relevant
    c.execute("""SELECT article_text,title FROM article WHERE idArticle <= 30604""") # only using training articles I've tested for now (so up to a certain id)
    rows = c.fetchall()
    for r in rows:
        x.append([r["title"],r["article_text"]])
        y.append("R")
    # get irrelevant training data - label U for irrelevant
    c.execute("""SELECT code,text,title FROM rejectedTrainingData WHERE id <= 26301""")
    rows = c.fetchall()
    for r in rows:
        x.append([r["title"],r["text"]])
        code = r["code"]
        if binary and code in ['S','F']:
            code = "U"
        y.append(code)
    return x, y

# in order to use the classifier we have to convert text data (our articles and titles) into numerical data (tf-idf matrices)
# 'mode' parameter determines how to feed the data to the tf-idf vectorizers - if 'train', the data is used to train/fit it. Otherwise, only used to test it/predict.
# returns a combined tf-idf matrix we can use to train our classifier
def convertTextData(x,v_text,v_title,mode):
    Xtitle = []
    Xtext = []
    for row in x:
        Xtitle.append(row[0])
        Xtext.append(row[1])
    if mode == 'train':
        Xtitle = v_title.fit_transform(Xtitle)
        Xtext = v_text.fit_transform(Xtext)
    else:
        Xtitle = v_title.transform(Xtitle)
        Xtext = v_text.transform(Xtext)
    x = hstack([Xtext,Xtitle]) # merge text and title matrices
    return x

# universal function for sending an alert email (used in ScraperAlert and TopicSiteAlert functions)
def sendAlert(subject,text):
    admins = get_admins()
    try:
        print("Sending alert email...")
        server = smtplib.SMTP('smtp.gmail.com', 587)
        server.starttls()
        server.login(os.environ['APP_EMAIL'], os.environ['EMAIL_PASSWORD'])
        toaddr = admins[0]
        cc = []
        if len(admins) > 1:
            cc = [admin for admin in admins[1:]]
        fromaddr = os.environ['APP_EMAIL']
        message = "From: %s\r\n" % fromaddr + "To: %s\r\n" % toaddr + "CC: %s\r\n" % ",".join(cc) + "Subject: %s\r\n" % subject + "\r\n" + text
        toaddrs = [toaddr] + cc
        server.sendmail(fromaddr, toaddrs, message)
        server.quit()
    except SMTPException as e:
        print("Alert email failed to send:",e)

# parses ADMINS environmental variable into a list of emails (used in email alerts)
def get_admins():
    admin_str = os.environ['ADMINS']
    admin_emails = [a for a in admin_str.split(',')]
    return admin_emails