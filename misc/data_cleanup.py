# this script goes through our training data spreadsheet and moves false positives into the rejectedTrainingData table of our database, rather than the primary front-facing one
import csv
import MySQLdb
import MySQLdb.cursors
import os
from tqdm import tqdm
import sys

# wrapper function for performing cleanup tasks
# reads cleanup CSV row by row, grabs article data, deletes the article's images, transfers the data to the rejectedTrainingData table, and then removes the article (and any leftover data) from the primary database
def cleanup(c,filename,img_dir):
    with open(filename) as csvfile: # change filename appropriately here
        reader = csv.DictReader(csvfile)
        total = len(list(reader))
        csvfile.seek(0,0)
        reader = csv.DictReader(csvfile)
        for row in tqdm(iterable=reader,desc='Training Data',total=total):
            try:
                if row['Code'] != 'R': # only moving irrelevant articles
                    ID = row['Article ID'].strip()
                    c.execute("""SELECT a.url, title, datetime, article_text, GROUP_CONCAT(DISTINCT keyword) as keywords, GROUP_CONCAT(DISTINCT path) as images 
                                FROM article A NATURAL JOIN article_keywords NATURAL JOIN keyword_instances LEFT JOIN image I ON A.idArticle = I.idArticle 
                                WHERE A.idArticle=%s LIMIT 1""",ID)
                    if c.rowcount == 1:
                        article = c.fetchone()
                        if article['images'] is not None:
                            delete_images(img_dir,article['images'])
                        t = (article['url'],article['datetime'],article['title'],article['article_text'],article['keywords'],row['Code'].strip())
                        c.execute("""INSERT INTO rejectedTrainingData(url,datetime,title,text,keywords,code) VALUES (%s,%s,%s,%s,%s,%s)""",t)
                        c.execute("""DELETE FROM article WHERE idArticle=%s LIMIT 1""",(ID,))
            except Exception as e:
                print(e)
                continue
        delete_extra_db(c)

# delete images associated with an irrelevant article
def delete_images(img_dir,img_str_list):
    images = img_str_list.split(",")
    for i in images:
        abs_image_path = img_dir + i
        if os.path.exists(abs_image_path):
            os.remove(abs_image_path)

# deleting articles often results in keywords and entities that don't belong to any articles and just take up space, so deleting them here
def delete_extra_db(c): 
    c.execute("""DELETE FROM article_keywords WHERE idKey NOT IN (SELECT DISTINCT idKey FROM keyword_instances)""")
    c.execute("""DELETE FROM image_entities WHERE idEntity NOT IN (SELECT DISTINCT idEntity FROM entity_instances)""")

def main():
    if len(sys.argv) < 3:
        print("Needs CSV and image folder argument (in that order.")
    else:
        filename = sys.argv[1]
        img_dir = sys.argv[2]
        if img_dir[-1] != "/": img_dir += "/" # accounting for trailing slash
        db = MySQLdb.connect(host=os.environ['DB_HOST'],port=int(os.environ['DB_PORT']),user=os.environ['DB_USER'],password=os.environ['DB_PASSWORD'],db="SupremeCourtApp",use_unicode=True,charset="utf8")
        db.autocommit(True)
        c = db.cursor(MySQLdb.cursors.DictCursor)
        cleanup(c,filename,img_dir)
main()