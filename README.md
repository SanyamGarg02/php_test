In order to run, 
install dependencies, pip install sentence-transformers

Run your elastic search instance and get the password, username most probably will be elastic, change if not and add password.
Let ES running.

Run test_index.php to create an index in your es named competitor_offers_test

run upload_batch.php to create embeddings from test.csv file and upsert it to es.

query es to see your document in es index competitor_offers_test to see if data is uploaded successfully,

curl -u elastic:YOURPASSWORD -k -X GET \
"https://localhost:9200/competitor_offers_test/_search?size=5&sort=created_at:desc"  ---> will show the 5 results from updated es

