# this script goes through our training data spreadsheet and moves false positives into the rejectedTrainingData table of our database, rather than the primary front-facing one

import csv
import MySQLdb
import MySQLdb.cursors
import os
from tqdm import tqdm
import sys

def move_data(c,filename):
    with open(filename) as csvfile: # change filename appropriately here
        reader = csv.DictReader(csvfile)
        total = len(list(reader))
        csvfile.seek(0,0)
        reader = csv.DictReader(csvfile)
        for row in tqdm(iterable=reader,desc='Training Data',total=total):
            try:
                if row['Code'] != 'R':
                    ID = row['Article ID'].strip()
                    c.execute("""SELECT url, title, datetime, article_text, GROUP_CONCAT(DISTINCT keyword) as keywords 
                        FROM article NATURAL JOIN article_keywords NATURAL JOIN keyword_instances 
                        WHERE idArticle=%s GROUP BY idArticle LIMIT 1""",(ID,))
                    if c.rowcount == 1:
                        article = c.fetchone()
                        t = (article['url'],article['datetime'],article['title'],article['article_text'],article['keywords'],row['Code'].strip())
                        c.execute("""INSERT INTO rejectedTrainingData(url,datetime,title,text,keywords,code) VALUES (%s,%s,%s,%s,%s,%s)""",t)
                        c.execute("""DELETE FROM article WHERE idArticle=%s LIMIT 1""",(ID,))
            except Exception as e:
                print(e)
                continue

def delete_images(c):
    x=3

def delete_extra_db(c):
    c.execute("""DELETE FROM article_keywords WHERE idKey NOT IN (SELECT DISTINCT idKey FROM keyword_instances)""")
    c.execute("""DELETE FROM image_entities WHERE idEntity NOT IN (SELECT DISTINCT idEntity FROM entity_instances)""")

def main():
    if len(sys.argv) < 2:
        print("Needs CSV argument.")
    else:
        filename = sys.argv[1]
        db = MySQLdb.connect(host=os.environ['DB_HOST'],port=int(os.environ['DB_PORT']),user=os.environ['DB_USER'],password=os.environ['DB_PASSWORD'],db="SupremeCourtApp",use_unicode=True,charset="utf8")
        db.autocommit(True)
        c = db.cursor(MySQLdb.cursors.DictCursor)
        move_data(c,filename)
main()
