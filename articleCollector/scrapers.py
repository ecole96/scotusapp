from bs4 import BeautifulSoup
from Article import *
from utilityFunctions import *
import newspaper
import ssl
import datetime
from datetime import timedelta
import re

# This class deals with the scraping aspects of the project, using both generic (Newspaper-powered) and targeted methods
class Scraper:
    # Since we're collecting initial data from RSS feeds, JSON objects, and topic pages, we don't have to scrape everything from a site - generally things like title and date are already provided, possibly author and images as well
    # So, we can import this data as needed into the object so if it's there we don't need to look for it - but if it isn't, we know what to look for
    # if something isn't provided, it should be passed in as None - with the exception of images, which should be passed in as an empty array
    def __init__(self,url,title,author,date,images):
        self.url = url
        if title: 
            self.title = processTitle(title)
        else:
            self.title = title
        self.author = author
        self.date = date
        self.images = images
        self.source = getSource(url)

    # driver function for scraping
    def scrape(self,c,driver,tz):
        # if the page to be scraped is from a source we've already written an individual scraper, use that scraper
        specialSources = ["apnews","cnn","nytimes","jdsupra","latimes","politico","thehill","chicagotribune","wsj","mondaq"]
        if self.source in specialSources:
            article,error_code = self.specificScraper(c,driver,tz)
            if article is None and error_code == 1: # fallback for specific scraper - if it fails, then attempt again using the generic scraper
                print(self.source,"scraper failed - now attempting with generic scraper")
                article = self.genericScraper()
        else: # otherwise, use Newspaper to try to get the data
            article = self.genericScraper()
        return article

    # driver for specific-site scrapers
    # returns an Article object, or if something goes wrong, None + error code
    def specificScraper(self,c,driver,tz):
        if driver: # Selenium required
            wait_elements = {"wsj":"div.article-content p"} # key is source, value is page element necessary to confirm successful load
            soup = alt_downloadPage(driver,self.url,wait_elements[self.source])
        else:
            soup = downloadPage(self.url)
        if soup: # if page is downloaded, scrape!
            try:
                # error code as result of scraper functions determine whether alert is called upon scraper failure: code 0 = success, 1 = failure (alarm), 2 = failure (but no alarm)
                article,error_code = getattr(self,self.source)(soup,tz) # call appropriate scraper based on source name (scraper functions should ALWAYS be named the same as its source for this work, verbatim)
            except Exception as e:
                print("Rejected - SCRAPING ERROR (likely formatting change): ",e)
                article,error_code = None, 1
            if article is None and error_code == 1:
                self.ScraperAlert(self.url,self.source,c)
        else:
            article, error_code = None, 1
        return article, error_code

    # send scraper alert to admins
    def ScraperAlert(self,url,source,c):
        c.execute("""SELECT * FROM alerts WHERE sources=%s AND type='S' AND url=%s LIMIT 1""",(source,url,))
        if c.rowcount == 0:
            c.execute("""INSERT INTO alerts(sources,type,url) VALUES (%s,'S',%s)""",(source,url,))
            subject = "SCOTUSApp - Custom Scraper Failure"
            text =  "During the latest run of the SCOTUSApp article collection script, the following source's custom scraper failed: " + source + "\nThe failure occurred at this URL: " + url
            text += "\n\nThis doesn't necessarily mean a scraper is down - sometimes a non-standard article page will appear in one of our feeds and fail. "
            text += "Any failed custom scraping attempts will fall back to the generic scraper, so it is possible this article may still be added to the database (though perhaps not scraped properly). "
            text += "If you're receiving this alert for a standard article page, it's likely a scraper has become outdated and will require maintenance."
            sendAlert(subject,text)

    # generic scraper for sites that don't have their own scrapers
    def genericScraper(self):
        config = newspaper.Config()
        config.browser_user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/602.2.14 (KHTML, like Gecko) Version/10.0.1 Safari/602.2.14'
        #config.request_timeout = 15
        if self.source not in ["washingtonpost","usnews"]: # washingtonpost and usnews get funky when you set a user agent for some reason (WaPo fails if the timeout isn't long, usnews throws a 403)
            a = newspaper.Article(self.url,config=config)
        else:
            a = newspaper.Article(self.url)
        try: # make sure page download goes smoothly
            a.download()
            a.parse()
        except Exception as e:
            print("Rejected - DOWNLOAD ERROR: ",e)
            return None
        text = cleanText(a.text)
        if len(text) < 500: # not much article text - full article is likely not picked up, and worst case scenario a short article is rejected (probably not all that useful in the long run)
            print("Rejected - Article text was less than 500 characters, likely bad scraping job")
            return None
        # get title, author, date and images as necessary
        if not self.title or self.title.split()[-1] == "...":
            if a.title:
                scrapedTitle = a.title.strip()
                if self.title:
                    self.title = replaceTitle(self.title,scrapedTitle)
                else:
                    self.title = scrapedTitle
        if not self.author:
            if a.authors:
                self.author = a.authors[0]
        if not self.date:
            if a.publish_date:
                self.date = a.publish_date.strftime("%Y-%m-%d %H:%M:%S")
        if not self.images:
            if a.top_image:
                self.images.append(a.top_image)
        article = Article(self.title,self.author,self.date,self.url,self.source,text.strip(),self.images)
        return article

    # scraper for CNN
    # all of these scraping functions are pretty similar, so I'm not commenting on the others unless there's a noticable difference (read the BeautifulSoup docs too)
    def cnn(self,soup,tz):
        if any(category in self.url for category in ["/videos/","/live-news/","/rss/"]): # non-article links - no text to be scraped, DROPPED
            print("Rejected - link is not an article")
            article, error_code = None, 2 # we don't want non-articles anyway, so scraper fails but don't sound alarm or attempt to rescrape with generic scraper
        else:
            opener = soup.find("cite",{"class":"el-editorial-source"})
            if opener:
                opener.decompose()
            if not self.title or self.title.split()[-1] == "...":
                t = soup.find("h1",{"class":"pg-headline"})
                if t:
                    scrapedTitle = t.text.strip()
                    if self.title:
                        self.title = replaceTitle(self.title,scrapedTitle)
                    else:
                        self.title = scrapedTitle
            if not self.author:
                a = soup.find(itemprop="author")
                if a:
                    a = a.get("content")
                    self.author = a.split(",")[0].strip()
            if not self.date:
                d = soup.find(itemprop="datePublished")
                if d:
                    d = d.get("content")
                    self.date = tz.fromutc(datetime.datetime.strptime(d.strip(),"%Y-%m-%dT%H:%M:%SZ")).strftime("%Y-%m-%d %H:%M:%S")
            if not self.images:
                i = soup.find(itemprop="image")
                if i:
                    i = i.get("content")
                    self.images.append(i)
            text = ''
            paragraphs = soup.find_all(["div","p"],{"class":"zn-body__paragraph"}) # paragraphs are contained in <div> and <p> tags with the class 'zn-body__paragraph' - catch 'em all
            if paragraphs:
                for p in paragraphs: # loop through paragraphs and add each one to text string, separated by double new-line
                    text += (p.text + '\n\n')
            if text == '': # scraping probably went wrong because no text, so return None
                print("Rejected - likely bad scraping job (no article text)")
                article,error_code = None, 1 # sound alarm and rescrape
            else:
                article, error_code = Article(self.title,self.author,self.date,self.url,self.source,text.strip(),self.images), 0
        return article,error_code

    def nytimes(self,soup,tz):
        if any(category in self.url for category in ["/video/","/live/"]): # non-article links - no text to be scraped, DROPPED
            print("Rejected - link is not an article")
            article, error_code = None, 2 # we don't want non-articles anyway, so scraper fails but don't sound alarm or attempt to rescrape with generic scraper
        else:
            junk = soup.select("div.g-tracked-refer, div.g-container")
            for j in junk: j.decompose()
            if not self.title or self.title.split()[-1] == "...":
                t = soup.find("meta",property="og:title")
                if t:
                    scrapedTitle = t.get("content").strip()
                    if self.title:
                        self.title = replaceTitle(self.title,scrapedTitle)
                    else:
                        self.title = scrapedTitle
            if not self.author:
                a = soup.find("meta", {"name":"byl"})
                if a:
                    self.author = ' '.join(a['content'].split()[1:])
            if not self.date:
                d = soup.find("meta",{"property":"article:published"})
                if d:
                    self.date = tz.fromutc(datetime.datetime.strptime(d['content'].strip(),"%Y-%m-%dT%H:%M:%S.%fZ")).strftime("%Y-%m-%d %H:%M:%S")
            if not self.images:
                i = soup.find(itemprop="image")
                if i:
                    i = i.get("content")
                    self.images.append(i)
            paragraphs = []
            p_select = soup.select("section[name=articleBody] p")
            if not p_select: p_select = soup.find_all("p",{"class":["g-body","graphic-text"]})
            for ps in p_select:
                ptext = ps.text.strip()
                if len(ptext) > 0:
                    paragraphs.append(ptext)
            text = '\n\n'.join(paragraphs)
            if text == '':
                print("Rejected - likely bad scraping job (no article text)")
                article,error_code = None, 1
            else:
                article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text,self.images), 0
        return article,error_code

    def latimes(self,soup,tz):
        hs = True if "highschool.latimes.com" in self.url else False # checks whether article comes from "High School Insider" subsite (formatted differently than standard LA Times articles)
        if not self.title or self.title.split()[-1] == "...":
            t = soup.find("meta",property="og:title")
            if t:
                scrapedTitle = t.get("content").strip()
                if self.title:
                    self.title = replaceTitle(self.title,scrapedTitle)
                else:
                    self.title = scrapedTitle
        if not self.author:
            a = soup.select_one("div.author-name a") if not hs else soup.select_one("a.author")
            if a:
                self.author = a.text.strip()
        if not self.date:
            d = soup.find("meta",property="article:published_time") if not hs else soup.select_one("time.published")
            if d:
                datestr = d.get("content").strip() if not hs else d.get("datetime").strip()
                if "." in datestr: datestr = datestr.split(".")[0]
                if not hs:
                    self.date = tz.fromutc(datetime.datetime.strptime(datestr,"%Y-%m-%dT%H:%M:%S")).strftime("%Y-%m-%d %H:%M:%S")
                else:
                    dt_regex = re.match(r'(^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})-(\d{2}):\d{2}$',datestr).groups() # get basic datetime str and UTC offset
                    utc_offset = int(dt_regex[1])
                    dt_utc = datetime.datetime.strptime(dt_regex[0],"%Y-%m-%dT%H:%M:%S") + timedelta(hours=utc_offset) # convert to UTC
                    self.date = tz.fromutc(dt_utc).strftime("%Y-%m-%d %H:%M:%S") # localize to Eastern time
        if not self.images:
            i = soup.find("meta",property="og:image")
            if i:
                img_url = i.get("content").strip()
                self.images.append(img_url)
        container = soup.select_one("div.rich-text-article-body-content") if not hs else soup.select_one("div.entry-content")
        paragraphs = [p.text.strip() for p in container.find_all("p",attrs={'class': None}) if len(p.text.strip()) != 0]
        text = '\n\n'.join(paragraphs)
        if text == '':
            print("Rejected - likely bad scraping job (no article text)")
            article,error_code = None, 1
        else:
            article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text.strip(),self.images), 0
        return article,error_code

    def jdsupra(self,soup,tz):
        if not self.title or self.title.split()[-1] == "...":
            t = soup.select_one("h1.doc_name.f2-ns.f3.mv0")
            if t:
                scrapedTitle = t.text.strip()
                if self.title:
                    self.title = replaceTitle(self.title,scrapedTitle)
                else:
                    self.title = scrapedTitle
        if not self.author:
            a = soup.select_one("div.f6.silver.db.dn-l.mt2.tc-ns a")
            if a:
                self.author = a.text
        if not self.date:
            d = soup.find("time")
            if d:
                datestr = d.text.strip() + " 00:00:00"
                self.date = convertDate(datestr,"%B %d, %Y %H:%M:%S")
        text = ''
        container = soup.find("div",{"class":"jds-main-content"})
        if container:
            paragraphs = container.find_all(["p","h2"])
            if paragraphs:
                for p in paragraphs: # differentiating between paragraphs and headers - if <p>, separate by double newline; if <h2>, separate by single newline
                    if p.name == "p": 
                        text += (p.text.strip() + '\n\n')
                    else:
                        text += (p.text.strip() + '\n')
        if text == '':
            print("Rejected - likely bad scraping job (no article text)")
            article, error_code = None, 1
        else:
            article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text.strip(),self.images), 0
        return article,error_code

    def politico(self,soup,tz):
        if not self.title or self.title.split()[-1] == "...":
            t = soup.find("meta",property="og:title")
            if t:
                scrapedTitle = t.get("content").strip()
                if self.title:
                    self.title = replaceTitle(self.title,scrapedTitle)
                else:
                    self.title = scrapedTitle
        if not self.author:
            a = soup.select_one("div.story-intro div.summary p.byline")
            if a:
                self.author = a.text.strip()[3:].strip()
        if not self.date:
            d = soup.find(itemprop="datePublished")
            if d:
                self.date = d.get("datetime")
        if not self.images:
            i = soup.find("meta",property="og:image")
            if i:
                self.images.append(i.get("content"))
        text = ''
        paragraphs = []
        if '/newsletters/' in self.url: # multiple templates for Politico articles (/news/, /newsletters/, /story/ all have different layouts - require different scraping methods)
            container = soup.select_one("div.story-text")
            if container: 
                junk = container.select("div.story-interrupt, aside")
                for j in junk: j.decompose()
                paragraphs = [p.text.strip() for p in container.find_all(["h2","p"],{"class":None})]
        elif '/news/' in self.url:
            container = soup.select("div.story-text")
            paragraphs = []
            for c in container:
                cp = c.find_all(["p","h3"])
                [paragraphs.append(cpt.text.strip()) for cpt in cp]
        elif 'politico.eu' in self.url:
            junk = soup.select("div.related-articles, blockquote, div.wp-caption")
            for j in junk: j.decompose()
            paragraphs = [p.text.strip() for p in soup.select("div.story-text p")]
        else:
            container = soup.select_one("article.story-main-content")
            if container:
                junk = container.find_all(["div","p"],{"class":["footer__copyright","story-continued","story-intro","byline"]}) + container.find_all("aside")
                for j in junk: j.decompose()
                paragraphs = [p.text.strip() for p in container.find_all("p")]
        text = '\n\n'.join(paragraphs)
        if text == '':
            print("Text is empty - likely bad scraping job (no article text)")
            article,error_code = None, 1
        else:
            article, error_code = Article(self.title,self.author,self.date,self.url,self.source,text,self.images), 0
        return article,error_code

    def thehill(self,soup,tz):
        junk = soup.find_all("span",{"class":"rollover-people-block"}) + soup.select("div.dfp-tag-wrapper") # site contains links and headlines for related articles when you rollover a known person in the article - remove these
        for j in junk:
            j.decompose()
        if not self.title or self.title.split()[-1] == "...":
            t = soup.find("meta",property="og:title")
            if t:
                scrapedTitle = t.get("content").strip()
                if self.title:
                    self.title = replaceTitle(self.title,scrapedTitle)
                else:
                    self.title = scrapedTitle
        if not self.author:
            a = soup.find("meta",property="author")
            if a:
                self.author = a.get("content")
        if not self.date:
            d = soup.find("meta",property="article:published_time")
            if d:
                datestr = d.get("content")
                dre = re.match(r'(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2}).*', datestr, re.M|re.I)
                date = dre.group(1) + " " + dre.group(2)
                self.date = date
        if not self.images:
            i = soup.find("meta",property="og:image")
            if i:
                self.images.append(i.get("content"))
        paragraphs = []
        paths = ["div.field-items p","div[dir=ltr] > div","div.field-item.even > div",] # cycle through all known possible text formats - stop when the right one is found
        for path in paths:
            blocks = soup.select(path)
            for b in blocks:
                text = b.text.strip()
                if text != '':
                    paragraphs.append(text)
            if paragraphs:
                break
        text = '\n\n'.join(paragraphs)
        if text == '':
            print("Text is empty - likely bad scraping job (no article text)")
            article,error_code = None, 1
        else:
            article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text,self.images), 0
        return article,error_code

    def chicagotribune(self,soup,tz):
        if not self.title or self.title.split()[-1] == "...":
            t = soup.find("meta",property="og:title")
            if t: self.title = t.get("content")
        if not self.author:
            a = soup.find("meta",{"name":"author"})
            if a and a.get("content"): self.author = a.get("content")
        if not self.date:
            d = soup.find("meta",{"name":"date"})
            if d and d.get("content"): 
                datestr = d.get("content").split(".")[0].strip().replace("Z","")
                self.date = tz.fromutc(datetime.datetime.strptime(datestr,"%Y-%m-%dT%H:%M:%S")).strftime("%Y-%m-%d %H:%M:%S")
        if not self.images:
            i = soup.find("meta",property="og:image")
            if i and i.get("content"): 
                image = i.get("content")
                if 'logo' not in image: self.images.append(image) # if an article doesn't have an image, the ChiTribune logo is used so ignore it
        text = ''
        paragraphs = soup.find_all("div",{"data-type":["text","header"]})
        for p in paragraphs: text += (p.text.strip() + '\n\n')
        if text == '':
            print("Text is empty - likely bad scraping job (no article text)")
            article, error_code = None, 1
        else:
            article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text.strip(),self.images), 0
        return article,error_code

    def wsj(self,soup,tz):
        if not self.title or self.title.split()[-1] == "...":
            t = soup.find("meta",{"name":"article.headline"})
            if t: self.title = t.get("content").strip()
        if not self.author:
            a = soup.find('meta',{"name":"author"})
            if a: self.author = a.get("content").strip()
        if not self.date:
            d = soup.find(itemprop="datePublished")
            if d: 
                datestr = d.get("content").strip()
                self.date = tz.fromutc(datetime.datetime.strptime(datestr,"%Y-%m-%dT%H:%M:%S.%fZ")).strftime("%Y-%m-%d %H:%M:%S")
        if not self.images:
            i = soup.find("meta",property="og:image")
            if i:
                image = i.get("content").strip()
                self.images.append(image)
        paragraphs = []
        for p in soup.select("div.article-content p"):
            ptext = ' '.join(p.text.strip().split())
            if len(ptext) > 0:
                paragraphs.append(ptext)
        text = '\n\n'.join(paragraphs)
        if text == '':
            print("Text is empty - likely bad scraping job (no article text)")
            article, error_code = None, 1
        else:
            article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text.strip(),self.images), 0
        return article,error_code
    
    def apnews(self,soup,tz):
        if not self.title or self.title.split()[-1] == "...":
            t = soup.find("meta",property="og:title")
            if t: self.title = t.get("content").strip()
        if not self.author:
            a = soup.select_one("span.Component-bylines-0-2-57")
            if a:
                self.author = a.text.strip()[3:]
            else:
                self.author = "The Associated Press"
        if not self.date:
            d = soup.find("meta",property="article:published_time")
            if d: 
                datestr = d.get("content").strip()
                self.date = tz.fromutc(datetime.datetime.strptime(datestr,"%Y-%m-%dT%H:%M:%SZ")).strftime("%Y-%m-%d %H:%M:%S")
        if not self.images:
            i = soup.find("meta",property="og:image")
            if i:
                image = i.get("content").strip()
                self.images.append(image)
        paragraphs = [p.text.strip() for p in soup.select("div.Article p") ]
        text = '\n\n'.join(paragraphs)
        if text == '':
            print("Text is empty - likely bad scraping job (no article text)")
            article, error_code = None, 1
        else:
            article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text,self.images), 0
        return article,error_code

    def mondaq(self,soup,tz):
        if not self.title or self.title.split()[-1] == "...":
            t = soup.select_one("div.article-title")
            if t: 
                junk = soup.select_one("span.region-heading")
                if junk: junk.decompose()
                self.title = t.text.strip()
        if not self.author:
            a = soup.select_one("div.author_headline span")
            if a: self.author = a.text.strip()
        if not self.date:
            d = soup.select_one("div.article_date")
            if d:
                datestr = d.text.strip() + " 00:00:00"
                self.date = datetime.datetime.strptime(datestr,"%d %B %Y %H:%M:%S").strftime("%Y-%m-%d %H:%M:%S")
        if not self.images:
            i = soup.find("meta",property="og:image")
            if i:
                image = i.get("content").strip()
                self.images.append(image)
        text_container = soup.select_one("div.article-body")
        paragraphs = [p.text.strip().replace('\r\n',' ') for p in text_container.find_all(["p","h3"])]
        text = '\n\n'.join(paragraphs)
        if text == '':
            print("Text is empty - likely bad scraping job (no article text)")
            article, error_code = None, 1
        else:
            article,error_code = Article(self.title,self.author,self.date,self.url,self.source,text,self.images), 0
        return article,error_code