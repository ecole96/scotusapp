# Data collection script for the Supreme Court Coverage & Analytics Application, version 2.0, November 2018 - for the UK Political Science department, Drs. Zilis and Wedeking
# written by Evan Cole and Darin Ellis, with some past contributions from John Tompkins, Jonathan Dingess, and Alec Gilbert
# scrapes articles from Google Alerts RSS feeds, NewsAPI search results, and Supreme Court topic pages (from major news sources), analyzes them using the Google Cloud API, and stores them in a MySQL database
# Ideally, a cronjob should be set to run this script every few hours for continuous data collection

import ssl
from collectionSources import *
from SeleniumInstance import *
from SocialMediaMetrics import *
import MySQLdb
import MySQLdb.cursors
import os
import pytz

def main():
    ssl._create_default_https_context = ssl._create_unverified_context # monkey patch for getting past SSL errors (this might be a system-specific issue)

    # database credentials need to be set as these environment variables
    db = MySQLdb.connect(host=os.environ['DB_HOST'],port=int(os.environ['DB_PORT']),user=os.environ['DB_USER'],password=os.environ['DB_PASSWORD'],db="SupremeCourtApp",use_unicode=True,charset="utf8")
    db.autocommit(True)
    c = db.cursor(MySQLdb.cursors.DictCursor)

    print("*** SCOTUSApp Data Collection Script ***\n")

    # check for new billing cycle before running
    try:
        if isNewBillingCycle(c):
            resetRequests(c)
            print("New billing cycle - sentiment requests reset\n")
        clf, v_text, v_title = train_relevancy(c) # build training dataset for relevancy check
        v_simtext = generate_similarity_model() # get model for determining article similarity
        smm = SocialMediaMetrics() # initialize/authentication for social media metrics
        gdrive = GDrive_auth(os.environ['GDRIVE_CONFIG_PATH'],os.environ['GDRIVE_CRED_PATH'])
        tz = pytz.timezone('US/Eastern') # initialize timezone for converting article publication datetimes to Eastern time (if necessary)
    except MySQLdb.Error as e:
        print("Database error - ",e)
        print("Script aborted.\n")
        return

    # RSS feeds
    feed_urls = ['https://www.google.com/alerts/feeds/04514219544348410405/10161046346160726598', 'https://www.google.com/alerts/feeds/04514219544348410405/7765273799045579732', 'https://www.google.com/alerts/feeds/04514219544348410405/898187730492460176', 'https://www.google.com/alerts/feeds/04514219544348410405/16898242761418666298']
    feeds = RSSFeeds(feed_urls)
    feeds.parseFeeds(c,clf,v_text,v_title,v_simtext,tz,smm,gdrive)

    # newsAPI results
    newsapi_key = os.environ['NEWSAPI_KEY']
    queries  = ["USA Supreme Court","US Supreme Court", "United States Supreme Court","SCOTUS"]
    newsapi = NewsAPICollection(newsapi_key,queries)
    newsapi.parseResults(c,clf,v_text,v_title,v_simtext,tz,smm,gdrive)

    # topic sites
    t = TopicSites()
    t.collect(c,clf,v_text,v_title,v_simtext,tz,smm,gdrive)

    print()
    generate_full_dl(c)

main()