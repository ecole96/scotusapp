import newspaper
import re
from google.cloud import language
from google.cloud.language import enums
from google.cloud.language import types
from Image import Image
import math
import datetime
from utilityFunctions import untitledArticle
from utilityFunctions import convertTextData

# class for article
# needs to add database/image/analysis functions
class Article:
    def __init__(self,title,author,date,url,source,text,imageURLs):
        if title:
            self.title = title
        else:
            self.title = untitledArticle()
        if author:
            self.author = author
        else:
            self.author = "Unknown Author"
        if date:
            self.date = date
        else:
            # set to current date
            self.date = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        self.url = url
        self.source = source
        self.text = text
        self.keywords = self.getKeywords()
        self.sentimentScore = None
        self.magnitude = None
        self.code = None
        self.class_score = None
        self.images = []
        for imageURL in imageURLs:
            image = Image(imageURL)
            self.images.append(image)
        self.metrics = {}

    # prints Article info to script output
    # title and URL are located in printBasicInfo() in UtilityFunctions.py, because we need to check those before we ever create an Article object
    def printInfo(self):
        print("Author:",self.author)
        print("Date:",self.date)
        print("Keywords:",self.keywords)
        print("Number of characters:",len(self.text))

    # prints analysis data to script output
    def printAnalysisData(self):
        if self.sentimentScore is not None and self.magnitude is not None:
            print("Sentiment Score & Magnitude:",str(round(self.sentimentScore,3)) + ",",round(self.magnitude,3))
        print("Social Media Metrics:",self.metrics)
        print()
        for index, image in enumerate(self.images):
            if image.filename:
                filestr = "Saved as " + image.filename
            else:
                filestr = "Not saved"
            
            if image.entities:
                entities = image.entities
            else:
                entities = "Not analyzed"
            print("* Image",str(index+1),"entities ( " + image.url + " / " + filestr + " ):",entities)

    # Newspaper library can get grab keywords from articles in conjunction with the nltk (natural language toolkit) library
    # this function prepares the article for language processing and returns an array of keywords from the article
    def getKeywords(self):
        # UGLY HACK WARNING
        # if a site has a specific scraper written for it, Newspaper is never involved - but Newspaper's keyword functionality is really good and I don't want to write my own function for it
        # so I'm creating a newspaper.Article object and forcibly setting attributes to allow the natural language processing to work and give me keywords
        a = newspaper.Article(self.url)
        a.text = self.text
        a.title = self.title
        a.download_state = 2 # nlp() function below uses direct comparisons to check for download state so I'm getting away with setting it to something arbitrary
        a.is_parsed = True
        a.nlp()
        return a.keywords

    # inserts keywords from the Article keyword array into the database one-by-one 
    def addKeywords(self,idArticle,c):
        # if keyword is a first occurrence, insert it into article_keywords
        for key in self.keywords:
            if not self.keywordIsDuplicate(key,c):
                c.execute("""INSERT INTO article_keywords(keyword) VALUES (%s)""",(key,))
                idKey = c.lastrowid
            else:
                c.execute("""SELECT idKey FROM article_keywords WHERE keyword = %s""",(key,))
                row = c.fetchone()
                idKey = row['idKey']
            c.execute("""INSERT INTO keyword_instances(idArticle,idKey) VALUES (%s,%s)""",(idArticle,idKey))

    # checks whether a keyword is already in the database
    # same implementation as the article check, just specific to keywords
    def keywordIsDuplicate(self,key, c):
        c.execute("""SELECT idKey FROM article_keywords WHERE keyword = %s""",(key,))
        if c.rowcount == 0:
            return False
        else:
            return True

    # uses Google Natural Language API to analyze article text, returning an overall sentiment score and its magnitude
    # sentiment scores correspond to the "emotional leaning of the text" according to Google - scores above 0 are considered positive sentiment, below are negative
    # magnitude is the "strength" of the sentiment
    def analyzeSentiment(self,c):
        # verify that sentiment analysis does not exceed 8000 call monthly limit
        c.execute("""SELECT * from analysisCap""")
        row = c.fetchone()
        currentSentimentRequests = row['currentSentimentRequests']
        expectedSentimentRequests = math.ceil(len(self.text) / 1000)
        if currentSentimentRequests + expectedSentimentRequests > 8000:
            print("Can't analyze sentiment score - API requests exceed limit of 8000")
        else:
            try:
                client = language.LanguageServiceClient() # initialize API
                document = language.types.Document(content=self.text,type=enums.Document.Type.PLAIN_TEXT)
                annotations = client.analyze_sentiment(document=document) # call to analyze sentiment
                # get necessary values
                self.sentimentScore = annotations.document_sentiment.score
                self.magnitude = annotations.document_sentiment.magnitude
                self.updateSentimentRequests(expectedSentimentRequests,c)
            except Exception as e:
                print("Sentiment analysis failed:",e)
    
    # store a .txt file of a newly added article to the /txtfiles/ folder
    def write_txt(self,idArticle):
        try:
            path = "/var/www/html/scotusapp/txtfiles/"
            filename = str(idArticle) + ".txt"
            with open(path + filename,"w") as f:
                f.write(self.text)
        except IOError as e:
            print("Failed to write/store text file:",e)

    # adds all of an article's information to the database
    def addToDatabase(self,c,smm):
        self.analyzeSentiment(c)
        self.metrics = smm.get_metrics(self.url)
        # insert new Article row
        t = tuple([self.url, self.source, self.author, self.date, self.text, self.title, self.sentimentScore, self.magnitude, self.class_score] + list(self.metrics.values()))
        c.execute("""INSERT INTO article(url, source, author, datetime, article_text, title, score, magnitude, relevancy_score,
                     fb_reactions_initial,fb_comments_initial,fb_shares_initial,fb_comment_plugin_initial,
                     tw_tweets_initial,tw_favorites_initial,tw_retweets_initial,tw_top_favorites_initial,tw_top_retweets_initial,
                     rdt_posts_initial,rdt_total_comments_initial,rdt_total_scores_initial,rdt_top_comments_initial,rdt_top_score_initial,rdt_top_ratio_initial,rdt_avg_ratio_initial) 
                     VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",t)

        # then insert the other stuff (keywords and images)
        idArticle = c.lastrowid # article id needed for keywords, images, and storing txt file
        self.addKeywords(idArticle,c)
        self.addImages(idArticle,c)
        self.write_txt(idArticle)

    # driver function for downloading, saving, and analyzing each of an article's images
    def addImages(self,idArticle,c):
        for index, image in enumerate(self.images):
            if image.isLogo():
                print("Image at",image.url,"is likely a logo - it will not be downloaded or analyzed")
            else:
                imageDownloaded = image.downloadImage()
                if imageDownloaded:
                    # images are titled {idArticle}.jpg
                    filename = "id" + str(idArticle)
                    if index > 0: # but if an article has more than one image, any additional image is titled {idArticle-N}.jpg
                        filename += "-" + str(index + 1)
                    filename += ".jpg"
                    imageSaved = image.saveImage(filename)
                    if imageSaved:
                        image.analyzeImage(c)
                        image.addImageToDatabase(idArticle,c)

    # used for state and foreign filters
    # parameter is an array of strings (states/countries and potentially their abbreviations/demonyms) 
    # Supreme courts are commonly referred to as "high" and "top" courts, so this function generates strings such as "[state] supreme court", "[demonym] high court", etc.
    # we can then check these strings against the text and titles of articles to check for state/foreign relevance
    def generate_court_terms(self,strings):
        terms = ["supreme","high","top"]
        comparisons = []
        for s in strings:
            for t in terms:
                comparisons.append(s + " " + t + " court")
        return comparisons

    # used in state and foreign court filters, processes title and text data in a way to make parsing easier
    def processText(self,text):
        #text = text.lower()
        to_remove = ["\'s","’s"] # remove possessives (to normalize "[state's] supreme court" to "[state] supreme court")
        for t in to_remove:
            text = text.replace(t,"") 
        text = text.replace("highest","high") # supreme courts commonly referred to as "highest court" so normalizing to high
        text = text.replace("topmost","top") # unlikely to occur but there are rare references to supreme courts as a "topmost court" so accounting for that
        regex = re.compile('([^\s\w]|_)+') # remove punctuation
        processed_text = regex.sub('', text).strip()
        return processed_text

    # checking whether an article pertains to a foreign supreme court - True if so, False if not
    # very similar to stateCourtDetected()
    def foreignCourtDetected(self):
        foreignSources = ['indiatimes','thehindu','liberianobserver',"allafrica",'vanguardngr','firstpost','ndtv','news18','moneycontrol','newzimbabwe'] # sources that near exclusively (if not entirely) report on foreign supreme court news, so blocking them manually here
        if self.source.lower() in foreignSources or ('india' in self.url.lower() and 'indiana' not in self.url.lower()): # indian supreme court pops ups a lot, so block any source with "india" in it ("indiana" still passes)
            print("Known foreign source detected")
            return True

        # dict in key:[] format where key is country, [] consists of the country's demonyms that could be used to refer to a foreign court (Russia Supreme Court = Russian Supreme Court, etc.)
        countries = {'afghanistan': ['afghan'], 'albania': ['albanian'], 'algeria': ['algerian'], 'andorra': ['andorran'], 'angola': ['angolan'], 'antigua and barbuda': ['antiguan', 
        'barbudan'], 'argentina': ['argentine', 'argentinean', 'argentinian'], 'armenia': ['armenian'], 'australia': ['aussie', 'australian'], 
        'austria': ['austrian'], 'azerbaijan': ['azerbaijani'], 'bahrain': ['bahraini'], 'barbados': ['barbadian'], 'bangladesh': ['bangladeshi'], 
        'lesotho': ['basotho', 'mosotho'], 'botswana': ['botswanan'], 'belarus': ['belarusian'], 'belgium': ['belgian'], 'belize': ['belizean'], 'benin': ['beninois'], 
        'bhutan': ['bhutanese'], 'guinea bissau': ['bissau guinean', 'guinea bissauan'], 'bolivia': ['bolivian'], 'bosnia and herzegovina': ['bosnian', 'bosnian herzegovinian', 'herzegovinian'], 
        'brazil': ['brasileiro', 'brazilian'], 'united kingdom': ['british', 'uk'], 'brunei': ['bruneian'], 'bulgaria': ['bulgarian'], 'burkina faso': ['burkinabe', 'burkinabè'], 'burundi': ['burundian'], 
        'georgia': ['georgian'], 'cambodia': ['cambodian', 'khmer'], 'cameroon': ['cameroonian'], 'canada': ['canadian', 'canadien', 'canadienne'], 
        'cape verde': ['cape verdian'], 'central african republic': ['central african'], 'chad': ['chadian'], 'chile': ['chilean'], 'china': ['chinese'], 'vatican city': ['vatican'], 
        'colombia': ['colombian'], 'comoros': ['comoran', 'comorian'], 'costa rica': ['costa rican'], 'croatia': ['croat', 'croatian'], 'cuba': ['cuban'], 'cyprus': ['cypriot', 'cypriote'], 'czech republic': ['czech'], 
        'denmark': ['danish'], 'djibouti': ['djibouti', 'djiboutian'], 'dominica': ['dominican'], 'netherlands': ['dutch', 'hollander', 'netherlandic'], 'east timor': ['east timorese'], 
        'ecuador': ['ecuadorian', 'ecuadoran'], 'egypt': ['egyptian'], 'united arab emirates': ['emirati', 'emirian','uae'], 'equatorial guinea': ['equatoguinean', 'equatorial guinean'], 'eritrea': ['eritrean'], 
        'estonia': ['estonian'], 'ethiopia': ['ethiopian'], 'fiji': ['fijian'], 'philippines': ['filipino', 'philippine', 'pinay', 'pinoy'], 'finland': ['finn', 'finnic', 'finnish'], 'france': ['french'], 
        'gabon': ['gabonese'], 'germany': ['german'], 'ghana': ['ghanaian'], 'greece': ['greek'], 'grenada': ['grenadian'], 'guatemala': ['guatemalan'], 'nepal': ['nepali'], 
        'guyana': ['guyanese'], 'haiti': ['haitian'], 'honduras': ['honduran'], 'hungary': ['hungarian'], 'iceland': ['icelandic'], 'kiribati': ['i kiribati'], 'india': ['indian'], 'indonesia': ['indonesian'], 'iran': ['irani', 'iranian'], 
        'iraq': ['iraqi'], 'ireland': ['irish'], 'israel': ['israeli', 'israelite'], 'italy': ['italian'], 'ivory coast': ['ivorian'], 'jamaica': ['jamaican'], 'japan': ['japanese'], 'jordan': ['jordanian'], 'kazakhstan': ['kazakhstani'], 
        'kenya': ['kenyan'], 'saint kitts and nevis': ['kittian', 'kittian and nevisian', 'kittitian', 'nevisian'], 'new zealand': ['kiwi', 'nz'], 
        'kuwait': ['kuwaiti'], 'kyrgyzstan': ['kyrgyz'], 'laos': ['lao', 'laotian'], 'latvia': ['latvian'], 'lebanon': ['lebanese'], 'liberia': ['liberian'], 'libya': ['libyan'], 'liechtenstein': ['liechtensteiner'], 
        'lithuania': ['lithuanian'], 'luxembourg': [], 'macedonia': ['macedonian'], 'madagascar': ['malagasy'], 'malawi': ['malawian'], 'malaysia': ['malaysian'], 'maldives': ['maldivian'], 
        'mali': ['malian', 'malinese'], 'malta': ['maltese'], 'marshall islands': ['marshallese'], 'mauritania': ['mauritanian'], 'mauritius': ['mauritian'], 'mexico': ['mexican'], 'micronesia': ['micronesian'], 
        'moldova': ['moldovan'], 'monaco': ['monacan', 'monégasque'], 'mongolia': ['mongol', 'mongolian'], 'montenegro': ['montenegrin'], 'morocco': ['moroccan'], 'mozambique': ['mozambican'], 'myanmar': ['myanmarese'], 
        'namibia': ['namibian'], 'nauru': ['nauruan'], 'nicaragua': ['nicaraguan'], 'nigeria': ['nigerian'], 'niger': ['nigerien'], 'vanuatu': ['vanuatuan'], 'norway': ['norwegian'], 
        'north korea': ['north korean'], 'oman': ['omani'], 'pakistan': ['pakistani'], 'palau': ['palauan'], 'palestine': ['palestinian'], 'panama': ['panamanian'], 'papua new guinea': ['papua new guinean', 'papuan'], 
        'paraguay': ['paraguayan'], 'peru': ['peruvian'], 'poland': ['pole', 'polish'], 'portugal': ['portuguese'], 'qatar': ['qatari'], 'romania': ['romanian'], 'russia': ['russian'], 'rwanda': ['rwandan', 'rwandese'], 
        'saint lucia': ['saint lucian'], 'el salvador': ['salvadoran'], 'san marino': ['sammarinese', 'san marinese'], 'samoa': ['samoan'], 'são tomé and príncipe': ['são toméan'], 'saudi arabia': ['saudi', 'saudi arabian'], 
        'senegal': ['senegalese'], 'serbia': ['serb', 'serbian'], 'seychelles': ['seychellois'], 'sierra leone': ['sierra leonean'], 'singapore': ['singapore', 'singaporean'], 'slovakia': ['slovak', 'slovakian'], 
        'slovenia': ['slovene', 'slovenian'], 'solomon islands': ['solomon island'], 'somalia': ['somali', 'somalian'], 'south africa': ['south african'], 'south korea': ['south korean'], 'south sudan': ['south sudanese'], 
        'spain': ['spaniard', 'spanish'], 'sri lanka': ['sri lankan'], 'sudan': ['sudanese'], 'suriname': ['surinamer', 'surinamese'], 'swaziland': ['swazi'], 'sweden': ['swede', 'swedish'], 'switzerland': ['swiss'], 'syria': ['syrian'], 
        'tajikistan': ['tadzhik', 'tajik', 'tajikistani'], 'tanzania': ['tanzanian'], 'thailand': ['thai'], 'trinidad and tobago': ['tobagonian', 'trinibagonian', 'trinidadian'], 'togo': ['togolese'], 
        'tonga': ['tongan'], 'tunisia': ['tunisian'], 'turkey': ['turk', 'turkish'], 'turkmenistan': ['turkmen', 'turkmenistani'], 'tuvalu': ['tuvaluan'], 'uganda': ['ugandan'], 'ukraine': ['ukrainian'], 
        'uruguay': ['uruguayan'], 'uzbekistan': ['uzbek', 'uzbekistani'], 'venezuela': ['venezuelan'], 'vietnam': ['vietnamese'], 'yemen': ['yemeni', 'yemenite'], 'zambia': ['zambian'], 'zimbabwe': ['zimbabwean']}

        openingText = self.processText(self.text.replace('-', ' ')).lower() # replace dashes with spaces to accomodate names in 'countries' list like guinea-bissau (among others) for parsing
        processed_title = self.processText(self.title.replace('-',' ')).lower()
        title_split = processed_title.split()
        for country in countries:
            terms = [country]
            demonyms = countries[country]
            for d in demonyms:
                if len(d.split()) == 1: # checking split() for single terms in order to avoid potential substring issues
                    t = processed_title
                else:
                    t = title_split
                if d in t:
                    terms.append(d)
            comparisons = self.generate_court_terms(terms)
            if any(c in processed_title or c in openingText for c in comparisons):
                print("Article likely about a foreign Supreme Court")
                return True
        return False
    
    # determine whether the focus of an article is about a state Supreme Court - returns True if deemed so, False otherwise
    # done by parsing the title and part of the text for giveaway terms ("[state] Supreme Court", "[state] high court", "[state] top court"...)
    def stateCourtDetected(self):
        # dict in key:[] format where key is state and [] consists of common state abbreviations
        states = {'alaska': ['ak'], 'alabama': ['al','ala'], 'arkansas': ['ar'], 'arizona': ['az','ariz'], 'california': ['ca','calif'], 'colorado': ['co'], 'connecticut': ['ct'], 'delaware': ['de'], 
        'florida': ['fl','fla'], 'georgia': ['ga'], 'hawaii': ['hi'], 'iowa': ['ia'], 'idaho': ['id'], 'illinois': ['il'], 'indiana': ['in'], 'kansas': ['ks'], 
        'kentucky': ['ky'], 'louisiana': ['la'], 'massachusetts': ['ma','mass'], 'maryland': ['md'], 'maine': ['me'], 'michigan': ['mi'], 'minnesota': ['mn','minn'], 'missouri': ['mo'], 
        'mississippi': ['ms'], 'montana': ['mt'], 'north carolina': ['nc'], 'north dakota': ['nd'], 'nebraska': ['ne'], 'new hampshire': ['nh'], 'new jersey': ['nj'], 'new mexico': ['nm'], 
        'nevada': ['nv'], 'new york': ['ny'], 'ohio': ['oh'], 'oklahoma': ['ok'], 'oregon': ['or'], 'pennsylvania': ['pa','penn'], 'puerto rico': ['pr'], 'rhode island': ['ri'], 
        'south carolina': ['sc'], 'south dakota': ['sd'], 'tennessee': ['tn'], 'texas': ['tx'], 'utah': ['ut'], 'virginia': ['va'], 'vermont': ['vt'], 'washington': ['wa'], 
        'wisconsin': ['wi'], 'west virginia': ['wv','wva'], 'wyoming': ['wy','wyo'],'state':[]}

        # only checking first 500 characters of text, where the main idea of an article is usually introduced (checking the full article could be slow + introduce false negatives)
        openingText = self.processText(self.text[:500]).lower() 
        processed_title = self.processText(self.title)
        title_split = processed_title.split()
        processed_title = processed_title.lower()
        for state in states:
            terms = [state]
            abvs = states[state]
            for t in title_split:
                # specifically checking for state abbreviations here, which are nearly always upper or mixed case (all lower implies a word or part of a string)
                # "In" is an exception to the rule given that it could very well be used as a word and not an abbrevation
                if t.lower() in abvs and not t.islower() and t not in ["In"]: 
                    terms.append(t.lower())
            comparisons = self.generate_court_terms(terms)
            # only checking for terms containing a full state string in the full text (first 3 elements of comparisons array) to avoid false positives
            if any(c in processed_title or (c in openingText and i < 3) for i,c in enumerate(comparisons)):  
                print("Article likely about a state supreme court")
                return True
        return False
    
    # if we can't determine relevancy with giveaways in the text or title, then determine using our text classifier
    def classify(self,clf,v_text,v_title,justice_keys):
        Xraw = [[self.title, self.text]]
        X = convertTextData(Xraw,v_text,v_title,'test')
        predict_probs = clf.predict_proba(X)[0] # array of probabilities for each classification
        prob_by_class = dict(zip(clf.classes_,predict_probs)) # zip probabilities to their according class
        sorted_probs = sorted(prob_by_class.items(),key=lambda kv: kv[1],reverse=True) # sort dict into an array of tuples most to least probable by class format: [(class,probability)]
        print("Class probabilities:",sorted_probs)
        self.code = sorted_probs[0][0] # initial / highest classification
        self.class_score = sorted_probs[0][1] # highest probability
        if self.code == 'R': 
            # our classification is very good but not perfect
            # to account for false negatives, if an article is classified as relevant but certain keywords do not exist, then the classifier must be extra sure (higher probability) it is relevant before truly deeming it relevant
            # this is called our "relevancy threshold" test
            if not (('supreme' in self.keywords and 'court' in self.keywords) or 'scotus' in self.keywords) and not any(justice in self.keywords for justice in justice_keys): 
                relevancy_threshold = 0.75 # numbers subject to change (still trying to find a "sweet spot" but this seems to do well)
            else:
                relevancy_threshold = 0.5
            if self.class_score < relevancy_threshold:
                print("Relevancy threshold test failed - reclassifying...")
                self.code = sorted_probs[1][0] # if article fails threshold test, then finally classify it as the second most likely class
                self.class_score = sorted_probs[1][1] 

    # relevancy check function - True for relevant, False otherwise
    def isRelevant(self,clf,v_text,v_title):
        instantTerms = ["usa supreme court", "us supreme court", "u.s. supreme court", "united states supreme court", "scotus",
                    'john roberts', 'anthony kennedy', 'clarence thomas', 'ruth bader ginsburg', 'stephen breyer', 
                    'samuel alito', 'sonia sotomayor', 'elena kagan', 'neil gorsuch', 'brett kavanaugh', "antonin scalia"] # dead giveaways for relevancy
        justice_keys = ['roberts', 'kennedy', 'thomas', 'ginsburg', 'breyer', 'alito', 
                        'sotomayor', 'kagan', 'gorsuch', 'kavanaugh','scalia'] # last names of the justices for parsing in keywords
        instantSources = ["scotusblog"]
        # check for the "dead giveaways"
        if any(term in self.title.lower() for term in (instantTerms + justice_keys)) or self.source in instantSources:
            self.code = "R"
            self.class_score = 1.0
        elif self.stateCourtDetected() or self.foreignCourtDetected():
            self.code = "U"
            self.class_score = 1.0
        else:
            self.classify(clf,v_text,v_title,justice_keys)    
        return self.code == "R"

    # insert irrelevant article data into the database for training purposes
    # data is coded by nature of irrelevancy; S = article is about state/lower court, F = article is about foreign court, U = unrelated topic
    def buildRejectedTrainingData(self,c):
        t = (self.url, self.date, self.text, self.title, self.code, self.class_score,','.join(self.keywords))
        c.execute("""INSERT INTO rejectedTrainingData(url, datetime, text, title, code, class_score, keywords) VALUES (%s,%s,%s,%s,%s,%s,%s)""",t)
        print("Article added to training data with code",self.code)

    # increment sentiment requests counter in database
    def updateSentimentRequests(self,n,c):
        c.execute("""UPDATE analysisCap SET currentSentimentRequests=currentSentimentRequests+(%s)""",(n,))