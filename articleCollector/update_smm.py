# this script handles gathering social media metrics for articles after they are entered into the database - specifically, one day after entry and one week.

import MySQLdb
import MySQLdb.cursors
import os
import sys
from SocialMediaMetrics import *

def updateDB(metrics,n,idArticle,c):
    sql = """UPDATE article SET """ + ','.join([key+"_d"+n+"="+str(metrics[key]) for key in metrics]) + " WHERE idArticle=" + str(idArticle)
    c.execute(sql)

def bulk_metric_update(n,c):
    smm = SocialMediaMetrics()
    c.execute("""SELECT idArticle,url FROM article WHERE DATE(datetime) = DATE_SUB(DATE(NOW()), INTERVAL %s DAY) ORDER BY idArticle DESC""",(n,))
    rows = c.fetchall()
    total = len(rows)
    print(total,"articles - day",n,"metrics\n")
    for r in rows:
        total -= 1
        print(r['idArticle'],'-',r['url'],"["+str(total),"articles remaining]")
        metrics = smm.get_metrics(r['url'])
        updateDB(metrics,n,r['idArticle'],c)
        print(metrics)
        print()
    print("Complete")

def main():
    if len(sys.argv) <= 1 or sys.argv[1] not in ['1','7']: # command line argument dictates whether to gather day 1 or day 7 metrics
        print("Needs command line argument (number of days from current date to go back to and update social media metrics). By default, this argument needs to be either 1 or 7.")
    else:
        # connect to db
        db = MySQLdb.connect(host=os.environ['DB_HOST'],port=int(os.environ['DB_PORT']),user=os.environ['DB_USER'],password=os.environ['DB_PASSWORD'],db="SupremeCourtApp",use_unicode=True,charset="utf8")
        db.autocommit(True)
        c = db.cursor(MySQLdb.cursors.DictCursor)
        n = sys.argv[1]
        bulk_metric_update(n,c)
main()