# script to generate and store .txt files for article text from the entire current database
from tqdm import tqdm
import MySQLdb
import MySQLdb.cursors
import os

# store a .txt file of a newly added article to the /txtfiles/ folder
def write_txt(idArticle,text):
    try:
        path = "/var/www/html/scotusapp/txtfiles/"
        filename = str(idArticle) + ".txt"
        with open(path + filename,"w") as f:
            f.write(text)
    except IOError as e:
        print("Failed to write/store text file:",e)

def main():
    db = MySQLdb.connect(host=os.environ['DB_HOST'],port=int(os.environ['DB_PORT']),user=os.environ['DB_USER'],password=os.environ['DB_PASSWORD'],db="SupremeCourtApp",use_unicode=True,charset="utf8")
    db.autocommit(True)
    c = db.cursor(MySQLdb.cursors.DictCursor)

    print("Populating /txtfiles/...\n")
    c.execute("""SELECT idArticle,article_text FROM article""")
    rows = c.fetchall()
    for r in tqdm(rows):
        write_txt(r['idArticle'],r['article_text'])
    print("\nComplete")

main()