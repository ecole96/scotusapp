import praw
import requests
import urllib.parse
import base64
import json
import os
import time

# this class handles the social media metrics we gather on each article, using data from Facebook, Twitter, and Reddit APIs
# we gather social media metrics for each article at three different intervals:
    # when an article is first entered into the database via main.py (columns postfixed in database with _initial)
    # a day after the publication date (postfixed with _d1)
    # one week after publication date (postfixed with _d7)
# the latter two intervals are collected via the update_smm.py script, scheduled via a cronjob on the server

class SocialMediaMetrics():
    def __init__(self): # initialize by authenticating each API (they return None if they fail))
        self.twitter_token = self.twitter_auth()
        self.fb_token = self.fb_auth()
        self.reddit = self.reddit_auth()
    
    # function to download JSON responses from REST APIs (Facebook, Twitter)
    # parameters: url and headers should be self-explanatory, 
    # data is any data we need to send (only used in POST for our needs), 
    # source is used to check specific error codes ('tw' for Twitter, 'fb' for Facebook) - more specifically to handle rate limiting
    def downloadJSON(self,url,method,headers,data,source):
        user_agent = 'University of Kentucky SCOTUSApp'
        if not headers:
            headers = {'User-Agent':user_agent}
        else: 
            headers['User-Agent'] = user_agent
        try:
            if method == 'post':
                request = requests.post(url,headers=headers,data=data)
            else:
                request = requests.get(url,headers=headers)
            code = request.status_code
            if code == 200:
                json = request.json()
            elif source == 'tw' and code == 429: 
                print("Sleeping for 15 minutes due to Twitter rate limit...")
                time.sleep(905)
                json = self.downloadJSON(url,method,headers,data,source)
                if not json: return None
            elif source == 'fb' and code == 403:
                code = request['error']['code']
                if code in [4,17,32,613]: 
                    print("Sleeping for an hour due to Facebook rate limit...")
                    time.sleep(3605) 
                    json = self.downloadJSON(url,method,headers,data,source)
                    if not json: return None
            else:
                json = None
                print("Request failed - error code",request.status_code) 
        except Exception as e: # couldn't download page
            print("Error at",url,":",e)
            json = None
        return json

    # authenticating Reddit API via the PRAW library
    def reddit_auth(self):
        try:
            reddit = praw.Reddit(client_id=os.environ['REDDIT_ID'],
                                 client_secret=os.environ['REDDIT_SECRET'],
                                 user_agent='University of Kentucky SCOTUSApp')
        except Exception as e:
            print("Couldn't authorize Reddit:",e)
            reddit = None
        return reddit

    # following Facebook Graph API authentication protocol (read their docs)
    def fb_auth(self):
        app_id = os.environ['FB_ID']
        app_secret = os.environ['FB_SECRET']
        auth_url = "https://graph.facebook.com/oauth/access_token?client_id=" + app_id + "&client_secret=" + app_secret + "&grant_type=client_credentials"
        json = self.downloadJSON(auth_url,'get',None,None,'fb')
        if json:
            token = json['access_token']
        else:
            token = None
            print("Couldn't authorize Facebook.")
        return token  

    # following Twitter API authentication protocol (read their docs)
    def twitter_auth(self):
        key = urllib.parse.quote(os.environ['TW_KEY'], safe='')
        secret = urllib.parse.quote(os.environ['TW_SECRET'], safe='')
        token = bytes(key + ":" + secret,'utf-8')
        token = str(base64.b64encode(token),'utf-8')
        auth_url = "https://api.twitter.com/oauth2/token"
        headers = {"Authorization":"Basic " + token,"Content-Type":"application/x-www-form-urlencoded;charset=UTF-8"}
        data = {"grant_type":"client_credentials"}
        json = self.downloadJSON(auth_url,'post',headers,data,'tw')
        if json:
            token = json['access_token']
        else:
            token = None
            print("Couldn't authorize Twitter.")
        return token
    
    # get metrics from all three of our social media sources, returns a dict with all of our data
    def get_metrics(self,url):
        metrics = {}
        fb = self.fb_metrics(url)
        metrics.update(fb)
        tw = self.twitter_metrics(url)
        metrics.update(tw)
        rdt = self.reddit_metrics(url)
        metrics.update(rdt)
        return metrics

    # gather Reddit metrics
    # rdt_posts = total # of Reddit posts linking to article URL, 
    # rdt_total_comments = total # of comments across all posts, 
    # rdt_total_scores = total "score" across all posts
    # rdt_top_comments = highest number of comments belonging to a single post
    # rdt_top_score = highest "score" belonging to a single post
    # rdt_top_ratio = highest upvote ratio (% of upvotes out of all "votes" on a post) belonging to a single post
    # rdt_avg_ratio = average upvote ratio across all posts
    def reddit_metrics(self,url):
        data = {'rdt_posts':None,'rdt_total_comments':None,'rdt_total_scores':None,'rdt_top_comments':None,'rdt_top_score':None,'rdt_top_ratio':None,'rdt_avg_ratio':None}
        if self.reddit:
            try:
                posts = self.reddit.subreddit('all').search(query='url:'+url,sort='new')
                data = {'rdt_posts':0,'rdt_total_comments':0,'rdt_total_scores':0,'rdt_top_comments':0,'rdt_top_score':0,'rdt_top_ratio':0,'rdt_avg_ratio':0}
                for post in posts:
                    comments = post.num_comments
                    score = post.score
                    ratio = post.upvote_ratio
                    data['rdt_posts'] += 1
                    data['rdt_total_comments'] += comments
                    data['rdt_total_scores'] += score
                    data['rdt_avg_ratio'] += ratio
                    if score > data['rdt_top_score']:
                        data['rdt_top_score'] = score
                    if comments > data['rdt_top_comments']:
                        data['rdt_top_comments'] = comments
                    if ratio > data['rdt_top_ratio']:
                        data['rdt_top_ratio'] = ratio
                if data['rdt_posts'] > 0: 
                    data['rdt_avg_ratio'] = round(data['rdt_avg_ratio'] / data['rdt_posts'],3)
            except Exception as e:
                print("Reddit error occurred:",e)
                data = {'rdt_posts':None,'rdt_total_comments':None,'rdt_total_scores':None,'rdt_top_comments':None,'rdt_top_score':None,'rdt_top_ratio':None,'rdt_avg_ratio':None}
        return data

    # gather Facebook metrics
    # fb_reactions = total # of reactions (combined # of likes and emoji reactions) to posts linking to article URL
    # fb_comments = total # of comments on posts linking to article URL
    # fb_shares = total # of shares of article URL
    # fb_comment_plugin = total # of comments on article webpages powered by the Facebook comment plugin
    def fb_metrics(self,url):
        data = {"fb_reactions":None,"fb_comments":None,"fb_shares":None,"fb_comment_plugin":None}
        if self.fb_token:
            encoded_url = urllib.parse.quote(url, safe='')
            api_url = "https://graph.facebook.com/v4.0/?id=" + encoded_url + "&fields=engagement" + "&access_token=" + self.fb_token
            json = self.downloadJSON(api_url,'get',None,None,'fb')
            if json:
                results = json['engagement']
                data["fb_reactions"] = results['reaction_count']
                data['fb_comments'] = results['comment_count']
                data['fb_shares'] = results['share_count']
                data['fb_comment_plugin'] = results['comment_plugin_count']
        return data

    # gather Twitter metrics
    # tw_tweets = total # of UNIQUE tweets linking to article URL (excludes retweets of other tweets, as this leads to highly inflated results)
    # tw_favorites = total # of favorites across all tweets linking to article URL
    # tw_retweets = total # of retweets across all tweets linking to article URL
    # tw_top_favorites = highest # of favorites belonging to a single tweet
    # tw_top_retweets = highest # of retweets belonging to a single tweet
    def twitter_metrics(self,url):
        data = {'tw_tweets':None,'tw_favorites':None,'tw_retweets':None,'tw_top_favorites':None,'tw_top_retweets':None}
        if self.twitter_token:
            encoded_url = urllib.parse.quote(url, safe='')
            headers = {"Authorization":"Bearer " + self.twitter_token}
            data = {'tw_tweets':0,'tw_favorites':0,'tw_retweets':0,'tw_top_favorites':0,'tw_top_retweets':0}
            query_str = "?q=" + encoded_url + " -filter:retweets&result_type=mixed&count=100"
            keepSearching = True
            while keepSearching:
                api_url = "https://api.twitter.com/1.1/search/tweets.json" + query_str
                results = self.downloadJSON(api_url,'get',headers,None,'tw')
                if not results: # search failed at some point - return what we have
                    return data
                else:
                    for tweet in results['statuses']:
                        #print(tweet)
                        data['tw_tweets'] += 1
                        data['tw_favorites'] += tweet['favorite_count']
                        data['tw_retweets'] += tweet['retweet_count']
                        if tweet['favorite_count'] > data['tw_top_favorites']:
                            data['tw_top_favorites'] = tweet['favorite_count']
                        if tweet['retweet_count'] > data['tw_top_retweets']:
                            data['tw_top_retweets'] = tweet['retweet_count']
                    if 'next_results' in results['search_metadata']: # go to next page of tweets
                        query_str = results['search_metadata']['next_results']
                    else:
                        keepSearching = False # end of results
                    #print(json.dumps(results, indent=4, sort_keys=True))
                    #break
        return data