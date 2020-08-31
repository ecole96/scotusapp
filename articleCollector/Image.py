import requests
from PIL import Image as img
import io
from google.cloud import vision
from google.cloud.vision import types
import os

# class for handling image downloading/saving/analysis
class Image:
    def __init__(self,url):
        self.url = url
        self.entities = {}
        self.rawImage = None
        self.filename = None

    # a sort of "relevancy" check for article images, since publication logos often show up if an article does not have any images
    # checks image link against list of known logo links, or strings that most likely give away that an image is a logo
    def isLogo(self):
        knownLogos = ['https://s4.reutersmedia.net/resources_v2/images/rcom-default.png','https://www.usnews.com/static/images/favicon.ico','http://www.si.com/img/misc/og-default.png'] # usnews and reuters often pop up in the feed, sometimes with these default image links (so we can filter them out)
        genericImageTerms = ['.ico', 'favicon','logo']
        url = self.url.lower()
        if url in knownLogos or any(term in url for term in genericImageTerms):
            return True
        else:
            return False

    # download image and put the image content into the rawImage attribute
    # we do this to check if an image can actually be properly downloaded before we try to save it
    def downloadImage(self):
        user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/602.2.14 (KHTML, like Gecko) Version/10.0.1 Safari/602.2.14'
        try:
            r = requests.get(self.url,headers={'User-Agent':user_agent})
            if r.status_code == 200:
                self.rawImage = r.content
                return True
            else:
                print("Image download failed at",self.url,"- status code",r.status_code)
                return False
        except Exception as e:
            print("Image download failed at",self.url,"- ",e)
            return False
    
    # save an image
    def saveImage(self,filename):
        try:
            path = "/var/www/html/scotusapp/images/" + filename
            with open(path, 'wb') as f: # download image into initial file (we don't know if what format the image is in)
                f.write(self.rawImage)
            i = img.open(path)
            i.convert('RGB').save(path,"JPEG",quality=85,optimize=True) # convert the image to an JPEG (to standardize all images in the database to one format) - also slightly degrade image quality to save space
            #print(self.url,"saved as",filename)
            self.filename = filename
            return True
        except Exception as e:
            print("Failed to save image at",self.url,"-",e)
            return False

    # backs up image to Google Drive
    # returns retcode depending on upload status - adhering to exit code convention, 0 is success and 1 is failure.
    def uploadImage(self,gdrive):
        if not gdrive:
            retcode = 1
        else:
            try:
                if os.path.exists("/var/www/html/scotusapp/images/" + self.filename):
                    db_file = gdrive.CreateFile({"title":self.filename,"parents":  [{"id": os.environ['GDRIVE_PHOTOS_FOLDER']}]})
                    db_file.SetContentFile("/var/www/html/scotusapp/images/" + self.filename)
                    db_file.Upload()
                    retcode = not int(db_file.uploaded) # convert upload status to exit code
                else:
                    retcode = 1
            except Exception as e:
                print("Image upload error:",e)
                retcode = 1
        if retcode != 0:
            print("Image failed to upload to Google Drive.")
        return retcode

    # uses Google Cloud Vision API to detect entities in the image
    # should get entity descriptions and their respective score (higher = more likely to be relevant to the image)
    def analyzeImage(self,c):
        # verify that image analysis is not over 1000 call monthly limit
        c.execute("""SELECT * from analysisCap""")
        row = c.fetchone()
        currentImageRequests = row['currentImageRequests']
        if currentImageRequests + 1 > 1000:
            print("Can't analyze image - API requests exceed limit of 1000")
        else:
            try:
                client = vision.ImageAnnotatorClient() # start API
                # read image and detect web entities on the image
            
                path = "/var/www/html/scotusapp/images/" + self.filename
                with io.open(path,'rb') as f:
                    content = f.read()
                image = vision.types.Image(content=content)
                web_detection = client.web_detection(image=image).web_detection

                # if there are entities, do the appropriate databases inserts one-by-one (this process is very similar to the one done with article keywords)
                if web_detection.web_entities:
                    for entity in web_detection.web_entities:
                        if entity.description.strip() != '':
                            self.entities[entity.description.strip()] = entity.score

                self.updateImageRequests(c)
            except Exception as e:
                print("Image analysis failed for",self.filename,"-",e)
    
    # checks whether a specific image entity is already in the database
    def entityIsDuplicate(self,entity, c):
        c.execute("""SELECT idEntity FROM image_entities WHERE entity = %s""",(entity,))
        if c.rowcount == 0:
            return False
        else:
            return True
    
    # insert image and its entities into the database
    def addImageToDatabase(self,idArticle,c):
        c.execute("""INSERT INTO image(idArticle, path, url) VALUES (%s,%s,%s)""",(idArticle, self.filename, self.url))
        idImage = c.lastrowid
        for entity in self.entities:
            score = self.entities[entity]
            if not self.entityIsDuplicate(entity,c):
                c.execute("""INSERT INTO image_entities(entity) VALUES (%s)""", (entity,))
                idEntity = c.lastrowid
            else:
                c.execute("""SELECT idEntity FROM image_entities WHERE entity = %s""",(entity,))
                row = c.fetchone()
                idEntity = row['idEntity']
            c.execute("""INSERT INTO entity_instances(idEntity,score,idImage) VALUES (%s,%s,%s)""",(idEntity,score,idImage))

    # increment # of image requests in API call table
    def updateImageRequests(self,c):
        c.execute("""UPDATE analysisCap SET currentImageRequests=currentImageRequests+1""")