# quick script for testing classifier accuracy

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import accuracy_score
from sklearn.svm import LinearSVC
from sklearn.calibration import CalibratedClassifierCV
from sklearn.model_selection import train_test_split
from nltk.corpus import stopwords
from scipy.sparse import hstack
import MySQLdb
import MySQLdb.cursors
import os
import numpy as np
import tldextract
import re
import string
import nltk
from nltk.stem import WordNetLemmatizer
from nltk.corpus import wordnet

def getSource(url):
    ext = tldextract.extract(url)
    source = ext.domain
    return source

def get_training_data(c,binary):
    x = []
    y = []
    # get relevant training data 26234
    c.execute("""SELECT idArticle,title,article_text,url,source,GROUP_CONCAT(DISTINCT keyword) as keywords FROM article natural join article_keywords natural join keyword_instances WHERE idArticle <= 30604 GROUP BY idArticle""")
    rows = c.fetchall()
    for r in rows:
        x.append([r["title"],r["article_text"],r["keywords"],r["url"],r["source"]])
        y.append("R")
    # get irrelevant training data 13276
    c.execute("""SELECT * FROM rejectedTrainingData""")
    rows = c.fetchall()
    for r in rows:
        x.append([r["title"],r["text"],r["keywords"],r["url"],getSource(r["url"])])
        code = r["code"]
        if binary and code in ['F','S']:
            code = "U"
        y.append(code)
    return x, y

def convertTextData(x,v_text,v_title,v_keys,mode):
    Xtitle = []
    Xtext = []
    #Xkeys = []
    for row in x:
        Xtitle.append(row[0])
        Xtext.append(row[1])
        #Xkeys.append(row[2])
    if mode == 'train':
        Xtitle = v_title.fit_transform(Xtitle)
        Xtext = v_text.fit_transform(Xtext)
        #Xkeys = v_keys.fit_transform(Xkeys)
    else:
        Xtitle = v_title.transform(Xtitle)
        Xtext = v_text.transform(Xtext)
        #Xkeys = v_keys.transform(Xkeys)
    #x = hstack([Xtext,Xtitle,Xkeys])
    x = hstack([Xtext,Xtitle])
    return x

def preprocess(s):
    s = s.lower()


def prepare_data(training_texts,training_labels):
    Xtrain, Xtest_raw, Ytrain, Ytest = train_test_split(training_texts,training_labels,test_size=0.25)
    Xtrain = [x[:2] for x in Xtrain]
    #s.lower().translate(str.maketrans('', '', string.punctuation))
    stop_words = stopwords.words("english")
    v_text = TfidfVectorizer(stop_words=stop_words,min_df=5)
    v_title = TfidfVectorizer(stop_words=stop_words,ngram_range=(1,3))
    #v_keys = TfidfVectorizer(stop_words=[w.replace("'","") for w in stop_words],min_df=25,binary=True)
    #v_text = TfidfVectorizer(tokenizer=LemmaTokenizer(),min_df=5)
    #v_title = TfidfVectorizer(tokenizer=LemmaTokenizer(),ngram_range=(1,3))
    v_keys = None
    Xtrain = convertTextData(Xtrain,v_text,v_title,v_keys,'train')
    Xtest = [x[:2] for x in Xtest_raw]
    Xtest = convertTextData(Xtest,v_text,v_title,v_keys,'test')
    return Xtrain, Xtest, Ytrain, Ytest, Xtest_raw

class LemmaTokenizer(object):
    def __init__(self):
        self.wnl = WordNetLemmatizer()
        self.stop_words = stopwords.words("english") + ["'s","n't"]
    def __call__(self, doc):
        #doc = doc.lower()
        chars = {"’":"'","‘":"'","\xa0":" ","“":'"',"”":'"'}
        for c in chars: doc = doc.replace(c,chars[c])
        tokens = []
        sents = nltk.sent_tokenize(doc)
        for sent in sents:
            sent_words = nltk.pos_tag(nltk.word_tokenize(sent))
            for sw in sent_words:
                if sw[1][0].isalpha():
                    word = sw[0]
                    if word not in self.stop_words and not word.startswith("http") and not word.startswith("//"):
                        word = re.sub(r'([^\w]|_)+', '', word)
                        if len(word) > 0:
                            tag = get_wordnet_pos(sw[1])
                            lemmatized = self.wnl.lemmatize(word,tag) 
                            tokens.append(lemmatized)
        return tokens

def get_wordnet_pos(tag_full):
    tag = tag_full[0].upper()
    """Map POS tag to first character lemmatize() accepts"""
    tag_dict = {"J": wordnet.ADJ,
                "N": wordnet.NOUN,
                "V": wordnet.VERB,
                "R": wordnet.ADV}
    return tag_dict.get(tag, wordnet.NOUN)

def test_raw(c,n,binary):
    header = "Test: Classifier Only, "
    modestr = "Binary Label (R / U), " if binary else "Multi-Label (R / U / S / F), "
    header += modestr + str(n) + " Runs"
    print(header)
    scores = []
    x, y = get_training_data(c,binary)
    for run in range(n):
        Xtrain, Xtest, Ytrain, Ytest, Xtest_raw = prepare_data(x, y)
        clf = CalibratedClassifierCV(LinearSVC(class_weight='balanced'),method='sigmoid',cv=5).fit(Xtrain,Ytrain)
        predict = clf.predict(Xtest)
        accuracy = accuracy_score(Ytest,predict) * 100
        scores.append(accuracy)
        print("Run",str(run+1) + ": ",str(accuracy)+"%")
    avg = (sum(scores) / len(scores))
    print("Average:",str(avg)+"%\n")
    return avg

def test_practical(c,n,binary):
    header = "Test: Classifer + Processing, "
    modestr = "Binary Label (R / U), " if binary else "Multi-Label (R / U / S / F), "
    header += modestr + str(n) + " Runs"
    print(header)
    instantTerms = ["usa supreme court", "us supreme court", "u.s. supreme court", "united states supreme court", "scotus",
                    'john roberts', 'anthony kennedy', 'clarence thomas', 'ruth bader ginsburg', 'stephen breyer', 
                    'samuel alito', 'sonia sotomayor', 'elena kagan', 'neil gorsuch', 'brett kavanaugh', "antonin scalia"]
    justice_keys = ['roberts', 'kennedy', 'thomas', 'ginsburg', 'breyer', 'alito', 
                    'sotomayor', 'kagan', 'gorsuch', 'kavanaugh','scalia']
    instantSources = ["scotusblog"]
    scores = []
    x, y = get_training_data(c,binary)
    for run in range(n):
        Xtrain, Xtest, Ytrain, Ytest, Xtest_raw = prepare_data(x, y)
        clf = CalibratedClassifierCV(LinearSVC(class_weight='balanced'),method='sigmoid',cv=5).fit(Xtrain,Ytrain)
        predict_probs = clf.predict_proba(Xtest)
        Y_initial = []
        Y_process = []
        i = 0
        for p in predict_probs:
            keywords = Xtest_raw[i][2].split(",")
            title = Xtest_raw[i][0]
            source = Xtest_raw[i][4]
            url = Xtest_raw[i][3]
            text = Xtest_raw[i][1]
            prob_by_class = dict(zip(clf.classes_,p)) # zip probabilities to their according class
            sorted_probs = sorted(prob_by_class.items(),key=lambda kv: kv[1],reverse=True) # sort dict into an array of tuples most to least probable by class format: [(class,probability)]
            result = sorted_probs[0][0] # initial / highest classification
            Y_initial.append(result)
            result_prob = sorted_probs[0][1] # highest probability
            if any(term in title.lower() for term in (instantTerms + justice_keys)) or source in instantSources: 
                result = "R"
            elif stateCourtDetected(text,title):
                result = "S"
            elif foreignCourtDetected(source,text,title,url):
                result = "F"
            else:
                if result == 'R':
                    if not (('supreme' in keywords and 'court' in keywords) or 'scotus' in keywords) and not any(justice in keywords for justice in justice_keys): 
                        relevancy_threshold = 0.75 # numbers subject to change (still trying to find a "sweet spot" but this seems to do well)
                    else:
                        relevancy_threshold = 0.5
                    if result_prob < relevancy_threshold:
                            result = sorted_probs[1][0]
                            result_prob = sorted_probs[1][1]
            if binary and result in ['F','S']:
                result = "U"
            Y_process.append(result)
            i += 1
        accuracy = accuracy_score(Ytest,Y_process) * 100
        print("Run",str(run+1) + ": ",str(accuracy)+"%")
        scores.append(accuracy)
    avg = (sum(scores) / len(scores))
    print("Average:",str(avg)+"%\n")
    return avg

# checking whether an article pertains to a foreign supreme court - True if so, False if not
# very similar to stateCourtDetected()
def foreignCourtDetected(source,text,title,url):
    foreignSources = ['indiatimes','thehindu','liberianobserver','vanguardngr','allafrica','firstpost','ndtv','news18'] # sources that near exclusively (if not entirely) report on foreign supreme court news, so blocking them manually here
    if source.lower() in foreignSources or ('india' in url.lower() and 'indiana' not in url.lower()): # indian supreme court pops ups a lot, so block any source with "india" in it ("indiana" still passes)
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
    openingText = processText(text.replace('-', ' ')).lower() # replace dashes with spaces to accomodate names in 'countries' list like guinea-bissau (among others) for parsing
    processed_title = processText(title.replace('-',' ')).lower()
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
        comparisons = generate_court_terms(terms)
        if any(c in processed_title or c in openingText for c in comparisons):
            return True
    return False

# determine whether the focus of an article is about a state Supreme Court - returns True if deemed so, False otherwise
# done by parsing the title and part of the text for giveaway terms ("[state] Supreme Court", "[state] high court", "[state] top court"...)
def stateCourtDetected(text,title):
    # dict in key:[] format where key is state and [] consists of common state abbreviations
    states = {'alaska': ['ak'], 'alabama': ['al','ala'], 'arkansas': ['ar'], 'arizona': ['az','ariz'], 'california': ['ca','calif'], 'colorado': ['co'], 'connecticut': ['ct'], 'delaware': ['de'], 
    'florida': ['fl','fla'], 'georgia': ['ga'], 'hawaii': ['hi'], 'iowa': ['ia'], 'idaho': ['id'], 'illinois': ['il'], 'indiana': ['in'], 'kansas': ['ks'], 
    'kentucky': ['ky'], 'louisiana': ['la'], 'massachusetts': ['ma','mass'], 'maryland': ['md'], 'maine': ['me'], 'michigan': ['mi'], 'minnesota': ['mn','minn'], 'missouri': ['mo'], 
    'mississippi': ['ms'], 'montana': ['mt'], 'north carolina': ['nc'], 'north dakota': ['nd'], 'nebraska': ['ne'], 'new hampshire': ['nh'], 'new jersey': ['nj'], 'new mexico': ['nm'], 
    'nevada': ['nv'], 'new york': ['ny'], 'ohio': ['oh'], 'oklahoma': ['ok'], 'oregon': ['or'], 'pennsylvania': ['pa','penn'], 'puerto rico': ['pr'], 'rhode island': ['ri'], 
    'south carolina': ['sc'], 'south dakota': ['sd'], 'tennessee': ['tn'], 'texas': ['tx'], 'utah': ['ut'], 'virginia': ['va'], 'vermont': ['vt'], 'washington': ['wa'], 
    'wisconsin': ['wi'], 'west virginia': ['wv','wva'], 'wyoming': ['wy','wyo'],'state':[]}
    # only checking first 500 characters of text, where the main idea of an article is usually introduced (checking the full article could be slow + introduce false negatives)
    openingText = processText(text[:500]).lower() 
    processed_title = processText(title)
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
        comparisons = generate_court_terms(terms)
        # only checking for terms containing a full state string in the full text (first 3 elements of comparisons array) to avoid false positives
        if any(c in processed_title or (c in openingText and i < 3) for i,c in enumerate(comparisons)):  
            return True
    return False

# used for state and foreign filters
# parameter is an array of strings (states/countries and potentially their abbreviations/demonyms) 
# Supreme courts are commonly referred to as "high" and "top" courts, so this function generates strings such as "[state] supreme court", "[demonym] high court", etc.
# we can then check these strings against the text and titles of articles to check for state/foreign relevance
def generate_court_terms(strings):
    terms = ["supreme","high","top"]
    comparisons = []
    for s in strings:
        for t in terms:
            comparisons.append(s + " " + t + " court")
    return comparisons

# used in state and foreign court filters, processes title and text data in a way to make parsing easier
def processText(text):
    #text = text.lower()
    to_remove = ["\'s","’s"] # remove possessives (to normalize "[state's] supreme court" to "[state] supreme court")
    for t in to_remove:
        text = text.replace(t,"") 
    text = text.replace("highest","high") # supreme courts commonly referred to as "highest court" so normalizing to high
    text = text.replace("topmost","top") # unlikely to occur but there are rare references to supreme courts as a "topmost court" so accounting for that
    regex = re.compile('([^\s\w]|_)+') # remove punctuation
    processed_text = regex.sub('', text).strip()
    return processed_text

def main():
    db = MySQLdb.connect(host=os.environ['DB_HOST'],port=int(os.environ['DB_PORT']),user=os.environ['DB_USER'],password=os.environ['DB_PASSWORD'],db="SupremeCourtApp",use_unicode=True,charset="utf8")
    db.autocommit(True)
    c = db.cursor(MySQLdb.cursors.DictCursor)  
    n = 5
    #binary_raw = test_raw(c,n,True)
    #multi_raw = test_raw(c,n,False)
    binary_prac = test_practical(c,n,True)
    #multi_prac = test_practical(c,n,False)
main()